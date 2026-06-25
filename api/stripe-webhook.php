<?php
/**
 * ============================================
 * API : WEBHOOK STRIPE (§6 / S4)
 * ============================================
 * Reçoit les événements Stripe. Source de vérité du paiement (le retour
 * success_url côté navigateur NE prouve PAS le paiement).
 *
 * Sécurité :
 *   - CSRF-EXEMPT volontairement : pas de session navigateur. L'authenticité
 *     est garantie par la SIGNATURE HMAC (Stripe-Signature) vérifiée sur le
 *     CORPS BRUT (php://input), jamais sur du JSON ré-encodé.
 *   - Signature invalide / absente → 400, rien d'autre.
 *
 * Idempotence :
 *   - INSERT IGNORE de l'event_id dans stripe_events_processed ; si déjà vu
 *     (rowCount == 0) → 200 no-op (Stripe rejoue sans effet double).
 *   - Effets de bord rendus idempotents séparément : email (claim atomique
 *     sur confirmation_email_sent_at), GCal (créé seulement si event absent).
 *   - Un échec email/GCal ne fait PAS échouer le webhook (sinon Stripe
 *     rejouerait à l'infini) : on log et on répond 200.
 */

require_once __DIR__ . '/../includes/init.php';

use App\Booking;
use App\Database;
use App\GoogleCalendarSync;
use App\Mailer;
use App\StripeClient;

// Corps BRUT (pour le HMAC) — surtout pas Helpers::getJsonInput() qui décode.
$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$secret = defined('STRIPE_WEBHOOK_SECRET') ? (string) STRIPE_WEBHOOK_SECRET : '';

if (!StripeClient::verifyWebhookSignature((string) $payload, $sigHeader, $secret)) {
    http_response_code(400);
    echo 'Signature invalide';
    exit;
}

$event = json_decode((string) $payload, true);
$eventId = is_array($event) ? ($event['id'] ?? '') : '';
if ($eventId === '') {
    http_response_code(400);
    echo 'Event invalide';
    exit;
}

// Idempotence : un event déjà traité → 200 no-op.
$db = Database::getInstance();
$ins = $db->prepare("INSERT IGNORE INTO stripe_events_processed (event_id) VALUES (:id)");
$ins->execute([':id' => $eventId]);
if ($ins->rowCount() === 0) {
    http_response_code(200);
    echo 'Déjà traité';
    exit;
}

// Seul type consommé pour l'instant : paiement Checkout abouti.
if (($event['type'] ?? '') === 'checkout.session.completed') {
    $session   = $event['data']['object'] ?? [];
    $sessionId = (string) ($session['id'] ?? '');
    $amount    = isset($session['amount_total']) ? (int) $session['amount_total'] : null;

    $booking = new Booking();
    $res = $booking->fulfillStripePayment($sessionId, $amount);

    if (!empty($res['ok']) && !empty($res['newly_confirmed'])) {
        $bk = $res['booking'];
        $id = (int) $bk['id'];

        // Email de confirmation — une seule fois (claim atomique).
        if (defined('EMAIL_ENABLED') && EMAIL_ENABLED && $booking->claimConfirmationEmail($id)) {
            try {
                Mailer::notifyConfirmation($bk);
            } catch (\Throwable $e) {
                error_log('stripe-webhook email: ' . $e->getMessage());
            }
        }

        // Google Calendar — idempotent (seulement si activé et event absent).
        try {
            $gcal = new GoogleCalendarSync();
            if ($gcal->isEnabled() && empty($bk['google_event_id'])) {
                $eventGid = $gcal->createEvent($bk);
                if ($eventGid) {
                    $booking->updateGoogleEventId($id, $eventGid);
                }
            }
        } catch (\Throwable $e) {
            error_log('stripe-webhook gcal: ' . $e->getMessage());
        }
    }
}

http_response_code(200);
echo 'OK';
