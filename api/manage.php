<?php
/**
 * ============================================
 * API : GESTION CLIENT (ESPACE PERSONNEL)
 * ============================================
 * GET  /api/manage.php?token=xxx           - Récupérer les détails du RDV
 * POST /api/manage.php?action=reschedule   - Demander un déplacement
 * POST /api/manage.php?action=cancel       - Annuler le RDV
 */

require_once __DIR__ . '/../includes/init.php';

use App\Booking;
use App\Mailer;
use App\Helpers;
use App\GoogleCalendarSync;

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$booking = new Booking();
$action = $_GET['action'] ?? 'get';

switch ($action) {
    // ============================================
    // RÉCUPÉRER LES DÉTAILS DU RDV
    // ============================================
    case 'get':
        $token = $_GET['token'] ?? '';
        
        if (empty($token) || strlen($token) !== 64) {
            Helpers::jsonResponse(['error' => 'Token invalide'], 400);
        }
        
        $bookingData = $booking->getByToken($token);
        
        if (!$bookingData) {
            Helpers::jsonResponse(['error' => 'Réservation non trouvée'], 404);
        }
        
        // Ne pas exposer certaines données sensibles
        unset($bookingData['ip_address']);
        unset($bookingData['user_agent']);
        unset($bookingData['admin_notes']);
        unset($bookingData['google_event_id']);
        unset($bookingData['google_calendar_id']);
        
        Helpers::jsonResponse([
            'success' => true,
            'booking' => $bookingData
        ]);
        break;
    
    // ============================================
    // DEMANDER UN DÉPLACEMENT
    // ============================================
    case 'reschedule':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helpers::jsonResponse(['error' => 'Méthode POST requise'], 405);
        }
        
        $input = Helpers::getJsonInput();
        if (empty($input)) {
            $input = $_POST;
        }
        
        $token = $input['token'] ?? '';
        $newDate = $input['new_date'] ?? '';
        $newTimeStart = $input['new_time_start'] ?? '';
        $newTimeEnd = $input['new_time_end'] ?? '';
        
        if (empty($token) || strlen($token) !== 64) {
            Helpers::jsonResponse(['error' => 'Token invalide'], 400);
        }
        
        if (empty($newDate) || empty($newTimeStart) || empty($newTimeEnd)) {
            Helpers::jsonResponse(['error' => 'Date et horaires requis'], 400);
        }
        
        // Récupérer l'ancienne réservation pour les emails
        $oldBooking = $booking->getByToken($token);
        if (!$oldBooking) {
            Helpers::jsonResponse(['error' => 'Réservation non trouvée'], 404);
        }
        
        $oldDate = Helpers::formatDateFr($oldBooking['slot_date']);
        $oldTime = Helpers::formatTimeSlot($oldBooking['slot_time_start'], $oldBooking['slot_time_end']);
        
        // Effectuer le déplacement
        $result = $booking->clientReschedule($token, $newDate, $newTimeStart, $newTimeEnd);

        if ($result['success']) {
            // Idempotence : créneau identique → pas de sync, pas de mail.
            if (!empty($result['unchanged'])) {
                Helpers::jsonResponse([
                    'success' => true,
                    'message' => $result['message']
                ]);
            }
            // Mettre à jour Google Calendar si activé
            try {
                $gcal = new GoogleCalendarSync();
                if ($gcal->isEnabled() && !empty($oldBooking['google_event_id'])) {
                    $updatedBooking = $booking->getByToken($token);
                    $gcal->updateEvent($oldBooking['google_event_id'], $updatedBooking);
                }
            } catch (Exception $e) {
                error_log('Google Calendar update error: ' . $e->getMessage());
            }
            
            // Envoyer les emails
            if (EMAIL_ENABLED) {
                $updatedBooking = $booking->getByToken($token);
                Mailer::notifyClientRescheduleRequest($updatedBooking, $oldDate, $oldTime);
            }
            
            Helpers::jsonResponse([
                'success' => true,
                'message' => $result['message']
            ]);
        } else {
            Helpers::jsonResponse(['success' => false, 'message' => $result['message']], 400);
        }
        break;
    
    // ============================================
    // ANNULER LE RDV
    // ============================================
    case 'cancel':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helpers::jsonResponse(['error' => 'Méthode POST requise'], 405);
        }
        
        $input = Helpers::getJsonInput();
        if (empty($input)) {
            $input = $_POST;
        }
        
        $token = $input['token'] ?? '';
        
        if (empty($token) || strlen($token) !== 64) {
            Helpers::jsonResponse(['error' => 'Token invalide'], 400);
        }
        
        // Récupérer la réservation pour les emails et Google Calendar
        $bookingData = $booking->getByToken($token);
        if (!$bookingData) {
            Helpers::jsonResponse(['error' => 'Réservation non trouvée'], 404);
        }
        
        // Effectuer l'annulation
        $result = $booking->clientCancel($token);
        
        if ($result['success']) {
            // Supprimer de Google Calendar si activé
            try {
                $gcal = new GoogleCalendarSync();
                if ($gcal->isEnabled() && !empty($bookingData['google_event_id'])) {
                    $gcal->deleteEvent($bookingData['google_event_id']);
                }
            } catch (Exception $e) {
                error_log('Google Calendar delete error: ' . $e->getMessage());
            }
            
            // Envoyer les emails
            if (EMAIL_ENABLED) {
                Mailer::notifyClientCancellation($bookingData);
            }
            
            Helpers::jsonResponse([
                'success' => true,
                'message' => $result['message']
            ]);
        } else {
            Helpers::jsonResponse(['success' => false, 'message' => $result['message']], 400);
        }
        break;
    
    // ============================================
    // SUPPRIMER MES DONNÉES (RGPD art. 17)
    // ============================================
    case 'delete_data':
        require __DIR__ . '/rgpd-delete-data.php';
        break;
    
    default:
        Helpers::jsonResponse(['error' => 'Action non reconnue'], 400);
}
