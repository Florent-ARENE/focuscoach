<?php
/**
 * ============================================
 * ACTION : SUPPRIMER MES DONNÉES (RGPD art. 17)
 * ============================================
 * Fichier inclus dans le switch de api/manage.php
 * Hérite des variables locales ($booking) mais PAS des use statements.
 * 
 * Usage dans api/manage.php :
 *   case 'delete_data':
 *       require __DIR__ . '/rgpd-delete-data.php';
 *       break;
 */

// Imports nécessaires (les use de manage.php ne sont pas hérités via require)
use App\Helpers;
use App\Mailer;
use App\GoogleCalendarSync;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Helpers::jsonResponse(['error' => 'Méthode POST requise'], 405);
}

$input = Helpers::getJsonInput();
if (empty($input)) {
    $input = $_POST;
}

$token = $input['token'] ?? '';
$confirmEmail = $input['confirm_email'] ?? '';

if (empty($token) || strlen($token) !== MANAGE_TOKEN_LENGTH) {
    Helpers::jsonResponse(['error' => 'Token invalide'], 400);
}

// Récupérer la réservation
$bookingData = $booking->getByToken($token);
if (!$bookingData) {
    Helpers::jsonResponse(['error' => 'Réservation non trouvée'], 404);
}

// Vérification de sécurité : l'email doit correspondre
if (strtolower(trim($confirmEmail)) !== strtolower(trim($bookingData['visitor_email']))) {
    Helpers::jsonResponse([
        'success' => false,
        'message' => 'L\'adresse email ne correspond pas. Vérifiez votre saisie.'
    ], 400);
}

try {
    $db = \App\Database::getInstance();
    
    // 1. Logger la demande de suppression (accountability, art. 24)
    try {
        $stmtLog = $db->prepare("
            INSERT INTO rgpd_deletion_log 
            (booking_id, visitor_email_hash, deletion_type, data_deleted, data_retained, requested_by, ip_address)
            VALUES 
            (:booking_id, :email_hash, 'right_to_erasure', :deleted, :retained, 'client', :ip)
        ");
        $stmtLog->execute([
            ':booking_id' => $bookingData['id'],
            ':email_hash' => hash('sha256', strtolower($bookingData['visitor_email'])),
            ':deleted' => 'visitor_name, visitor_email, visitor_phone, visitor_organization, subject, message, ip_address, user_agent, manage_token',
            ':retained' => 'slot_date, slot_time_start, slot_time_end, service_id, status (anonymisé)',
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (\PDOException $e) {
        // Table de log absente — pas bloquant, on continue
        error_log('[RGPD] Log table missing: ' . $e->getMessage());
    }
    
    // 2. Anonymiser les données personnelles
    // On conserve les données anonymisées pour les stats agrégées
    $stmtAnon = $db->prepare("
        UPDATE bookings SET
            visitor_name = '[Données supprimées]',
            visitor_email = CONCAT('deleted_', id, '@anonymized.local'),
            visitor_phone = NULL,
            visitor_organization = NULL,
            subject = NULL,
            message = NULL,
            ip_address = NULL,
            user_agent = NULL,
            manage_token = NULL,
            admin_notes = CONCAT(IFNULL(admin_notes, ''), '\n[RGPD] Données effacées le ', NOW(), ' à la demande du client'),
            status = CASE 
                WHEN status IN ('pending', 'confirmed') THEN 'cancelled'
                ELSE status
            END,
            updated_at = NOW()
        WHERE id = :id
    ");
    $stmtAnon->execute([':id' => $bookingData['id']]);
    
    // 3. Supprimer l'événement Google Calendar si existant
    if (!empty($bookingData['google_event_id'])) {
        try {
            $gcal = new GoogleCalendarSync();
            if ($gcal->isEnabled()) {
                $gcal->deleteEvent($bookingData['google_event_id']);
            }
        } catch (\Exception $e) {
            error_log('[RGPD] Google Calendar cleanup error: ' . $e->getMessage());
        }
    }
    
    // 4. Envoyer un email de confirmation de suppression
    if (defined('EMAIL_ENABLED') && EMAIL_ENABLED) {
        $cfg = siteConfig();
        $confirmSubject = "Confirmation de suppression de vos données — " . $cfg['site_name'];
        $confirmMessage = "Bonjour,\n\n";
        $confirmMessage .= "Suite à votre demande, vos données personnelles associées à votre ";
        $confirmMessage .= "rendez-vous du " . Helpers::formatDateFr($bookingData['slot_date']) . " ont été supprimées.\n\n";
        $confirmMessage .= "Données supprimées : nom, email, téléphone, organisation, objet, message, adresse IP.\n";
        $confirmMessage .= "Données conservées (anonymisées) : date du créneau, type de service (à des fins statistiques).\n\n";
        $confirmMessage .= "Si vous n'êtes pas à l'origine de cette demande, contactez-nous immédiatement à " . $cfg['admin_email'] . ".\n\n";
        $confirmMessage .= "Cordialement,\n" . $cfg['full_name'];
        
        Mailer::send($bookingData['visitor_email'], $confirmSubject, $confirmMessage);
    }
    
    Helpers::jsonResponse([
        'success' => true,
        'message' => 'Vos données personnelles ont été supprimées. Un email de confirmation vous a été envoyé.',
        'deleted_fields' => ['nom', 'email', 'téléphone', 'organisation', 'objet', 'message', 'adresse IP', 'user agent'],
        'retained_info' => 'Seules les données anonymisées (date, type de service) sont conservées à des fins statistiques.'
    ]);
    
} catch (\PDOException $e) {
    error_log('[RGPD] Delete data error: ' . $e->getMessage());
    Helpers::jsonResponse([
        'success' => false,
        'message' => 'Une erreur est survenue lors de la suppression.'
    ], 500);
}
