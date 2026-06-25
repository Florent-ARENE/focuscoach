<?php
/**
 * ============================================
 * BOOKING v3 — ÉTAPE POST : CRÉATION DU BOOKING
 * ============================================
 * Reçoit le POST de confirm.php, vérifie CSRF, vérifie cohérence
 * avec le draft session, crée le booking via Booking::create()
 * (mode v3 : service_id, duration_min, buffer_after_min figés).
 *
 * ⚠️ §5 — pas encore de Stripe. Le booking est créé en
 * status='pending', payment_status='none' (mode validation admin,
 * équivalent legacy v2). §6 insérera Stripe Checkout ici.
 */
require_once __DIR__ . '/../../includes/init.php';

use App\Booking;
use App\Database;
use App\Helpers;
use App\StripeClient;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!Helpers::verifyCsrfFromRequest($_POST)) {
    http_response_code(403);
    echo 'Jeton CSRF invalide.';
    exit;
}

$draft = $_SESSION['booking_draft'] ?? [];
$required = ['service_id', 'duration_min', 'buffer_after_min', 'slot_date', 'slot_time_start', 'slot_time_end'];
foreach ($required as $k) {
    if (!isset($draft[$k]) || $draft[$k] === '') {
        header('Location: index.php');
        exit;
    }
}

$name  = trim(Helpers::sanitize($_POST['visitor_name']  ?? ''));
$email = trim(Helpers::sanitize($_POST['visitor_email'] ?? ''));
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // Retour formulaire en erreur — minimal : on renvoie sur confirm.php.
    // Une amélioration (§5b éventuel) afficherait l'erreur inline.
    header('Location: confirm.php'
        . '?service=' . (int) $draft['service_id']
        . '&date='    . urlencode($draft['slot_date'])
        . '&start='   . urlencode($draft['slot_time_start'])
        . '&end='     . urlencode($draft['slot_time_end']));
    exit;
}

$booking = new Booking();

$data = [
    'visitor_name'         => $name,
    'visitor_email'        => $email,
    'visitor_phone'        => Helpers::sanitize($_POST['visitor_phone']        ?? ''),
    'visitor_organization' => Helpers::sanitize($_POST['visitor_organization'] ?? ''),
    'slot_date'            => $draft['slot_date'],
    'slot_time_start'      => $draft['slot_time_start'],
    'slot_time_end'        => $draft['slot_time_end'],
    'subject'              => Helpers::sanitize($_POST['subject'] ?? ''),
    'message'              => Helpers::sanitize($_POST['message'] ?? ''),
    // Mode v3 — service catalogué
    'service_id'           => (int) $draft['service_id'],
    'duration_min'         => (int) $draft['duration_min'],
    'buffer_after_min'     => (int) $draft['buffer_after_min'],
];

$slotBack = 'slot.php?service=' . (int) $draft['service_id'] . '&date=' . urlencode($draft['slot_date']);

// Routage paiement (S1) : on lit le tarif + price_id du service.
$svc = Database::getInstance()->prepare('SELECT price_cents, stripe_price_id FROM services WHERE id = :id');
$svc->execute([':id' => (int) $draft['service_id']]);
$service = $svc->fetch() ?: ['price_cents' => 0, 'stripe_price_id' => null];

if (\App\paymentMode($service) === 'stripe') {
    // 1) Hold pending_payment (15 min) avec garde anti-double-booking (S2).
    $result = $booking->createHold($data, 15);
    if (!$result['success']) {
        header('Location: ' . $slotBack); // créneau déjà pris
        exit;
    }
    $bookingId = (int) $result['booking_id'];

    // 2) Session Checkout (URLs absolues depuis BASE_URL, jamais HTTP_HOST).
    $session = StripeClient::createCheckoutSession(
        (string) $service['stripe_price_id'],
        BASE_URL . 'modules/booking/success.php?cs={CHECKOUT_SESSION_ID}',
        BASE_URL . 'modules/booking/' . $slotBack,
        ['booking_id' => $bookingId]
    );

    // 3) Échec → libérer le hold (pas de booking fantôme) + retour clair.
    if (isset($session['error'])) {
        $booking->releaseHold($bookingId);
        $_SESSION['booking_error'] = 'Le paiement n\'a pas pu démarrer. Merci de réessayer.';
        header('Location: ' . $slotBack);
        exit;
    }

    // 4) Mémoriser la session et rediriger vers Stripe (confirmation via webhook).
    $booking->attachStripeSession($bookingId, $session['id']);
    header('Location: ' . $session['url']);
    exit;
}

// Mode BYPASS (gratuit, Stripe off, ou stripe_price_id absent) : booking direct
// en 'pending' (validation admin), comme avant.
$result = $booking->create($data);
if (!$result['success']) {
    header('Location: ' . $slotBack);
    exit;
}

$_SESSION['booking_result'] = [
    'booking_id'   => $result['booking_id'],
    'manage_token' => $result['manage_token'],
    'draft'        => $draft,
    'visitor_name' => $name,
    'visitor_email'=> $email,
];
$_SESSION['booking_draft'] = []; // vidé après usage

header('Location: success.php');
exit;
