<?php
/**
 * ============================================
 * API : ADMINISTRATION
 * ============================================
 * GET  /api/admin.php?action=list
 * GET  /api/admin.php?action=get&id=X
 * POST /api/admin.php?action=update
 * POST /api/admin.php?action=delete
 */

// Gestion d'erreurs globale pour toujours retourner du JSON
set_error_handler(function($severity, $message, $file, $line) {
    // Ignorer les warnings Deprecated
    if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
        return true;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function($e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur serveur',
        'debug' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
    exit;
});

require_once __DIR__ . '/../includes/init.php';

use App\Booking;
use App\Slot;
use App\Settings;
use App\Mailer;
use App\Helpers;
use App\GoogleCalendarSync;

header('Content-Type: application/json; charset=utf-8');

// Vérifier l'authentification admin
if (empty($_SESSION['admin_logged_in'])) {
    // En développement, on peut autoriser l'accès
    if (APP_ENV === 'production') {
        Helpers::jsonResponse(['error' => 'Non autorisé'], 401);
    }
}

try {
    $action = $_GET['action'] ?? '';
    $booking = new Booking();
    $slot = new Slot();
    $settings = new Settings();
} catch (Exception $e) {
    Helpers::jsonResponse([
        'success' => false,
        'error' => 'Erreur initialisation',
        'debug' => APP_DEBUG ? $e->getMessage() : null
    ], 500);
}

switch ($action) {
    
    // ============================================
    // LISTER LES RÉSERVATIONS
    // ============================================
    case 'list':
        $filters = [];
        
        if (!empty($_GET['status'])) {
            $filters['status'] = Helpers::sanitize($_GET['status']);
        }
        if (!empty($_GET['date_from'])) {
            $filters['date_from'] = Helpers::sanitize($_GET['date_from']);
        }
        if (!empty($_GET['date_to'])) {
            $filters['date_to'] = Helpers::sanitize($_GET['date_to']);
        }
        
        $bookings = $booking->getAll($filters);
        $stats = $booking->getStats();
        
        Helpers::jsonResponse([
            'bookings' => $bookings,
            'total' => count($bookings),
            'stats' => $stats
        ]);
        break;
    
    // ============================================
    // RÉCUPÉRER UNE RÉSERVATION
    // ============================================
    case 'get':
        $id = (int) ($_GET['id'] ?? 0);
        
        if ($id <= 0) {
            Helpers::jsonResponse(['error' => 'ID invalide'], 400);
        }
        
        $data = $booking->getById($id);
        
        if (!$data) {
            Helpers::jsonResponse(['error' => 'Réservation non trouvée'], 404);
        }
        
        Helpers::jsonResponse(['booking' => $data]);
        break;
    
    // ============================================
    // METTRE À JOUR LE STATUT
    // ============================================
    case 'update':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helpers::jsonResponse(['error' => 'Méthode POST requise'], 405);
        }
        
        $input = Helpers::getJsonInput();
        if (empty($input)) {
            $input = $_POST;
        }
        
        $id = (int) ($input['id'] ?? 0);
        $status = $input['status'] ?? '';
        $adminNotes = $input['admin_notes'] ?? null;
        
        if ($id <= 0) {
            Helpers::jsonResponse(['error' => 'ID invalide'], 400);
        }
        
        if (empty($status)) {
            Helpers::jsonResponse(['error' => 'Statut requis'], 400);
        }
        
        // Récupérer la réservation avant mise à jour
        $bookingData = $booking->getById($id);
        if (!$bookingData) {
            Helpers::jsonResponse(['error' => 'Réservation non trouvée'], 404);
        }
        
        $oldStatus = $bookingData['status'];
        
        // Mettre à jour le statut
        $result = $booking->updateStatus($id, $status, $adminNotes);
        
        if ($result['success']) {
            // Récupérer les données mises à jour
            $bookingData = $booking->getById($id);
            
            // Envoyer les notifications email
            if (EMAIL_ENABLED && $oldStatus !== $status) {
                if ($status === 'confirmed') {
                    Mailer::notifyConfirmation($bookingData);
                } elseif ($status === 'cancelled') {
                    Mailer::notifyCancellation($bookingData);
                }
            }
            
            // Sync Google Calendar
            try {
                $gcal = new GoogleCalendarSync();
                if ($gcal->isEnabled()) {
                    if (!empty($bookingData['google_event_id'])) {
                        // Mise à jour de l'événement existant
                        $gcal->updateEvent($bookingData['google_event_id'], $bookingData);
                    } else {
                        // Créer l'événement s'il n'existe pas encore
                        $eventId = $gcal->createEvent($bookingData);
                        if ($eventId) {
                            $booking->updateGoogleEventId($id, $eventId);
                        }
                    }
                }
            } catch (Exception $e) {
                error_log('Google Calendar update error: ' . $e->getMessage());
            }
            
            Helpers::jsonResponse(['success' => true, 'message' => $result['message']]);
        } else {
            Helpers::jsonResponse(['success' => false, 'message' => $result['message']], 400);
        }
        break;
    
    // ============================================
    // SUPPRIMER UNE RÉSERVATION
    // ============================================
    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helpers::jsonResponse(['error' => 'Méthode POST requise'], 405);
        }
        
        $input = Helpers::getJsonInput();
        if (empty($input)) {
            $input = $_POST;
        }
        
        $id = (int) ($input['id'] ?? 0);
        
        if ($id <= 0) {
            Helpers::jsonResponse(['error' => 'ID invalide'], 400);
        }
        
        // Récupérer avant suppression pour Google Calendar
        $bookingData = $booking->getById($id);
        
        if ($booking->delete($id)) {
            // Supprimer de Google Calendar
            try {
                $gcal = new GoogleCalendarSync();
                if ($gcal->isEnabled() && !empty($bookingData['google_event_id'])) {
                    $gcal->deleteEvent($bookingData['google_event_id']);
                }
            } catch (Exception $e) {
                error_log('Google Calendar delete error: ' . $e->getMessage());
            }
            
            Helpers::jsonResponse(['success' => true, 'message' => 'Réservation supprimée']);
        } else {
            Helpers::jsonResponse(['success' => false, 'message' => 'Réservation non trouvée'], 404);
        }
        break;
    
    // ============================================
    // DÉPLACER UNE RÉSERVATION
    // ============================================
    case 'reschedule':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helpers::jsonResponse(['error' => 'Méthode POST requise'], 405);
        }
        
        $input = Helpers::getJsonInput();
        if (empty($input)) {
            $input = $_POST;
        }
        
        $id = (int) ($input['id'] ?? 0);
        $newDate = $input['new_date'] ?? '';
        $newTimeStart = $input['new_time_start'] ?? '';
        $newTimeEnd = $input['new_time_end'] ?? '';
        
        if ($id <= 0) {
            Helpers::jsonResponse(['error' => 'ID invalide'], 400);
        }
        
        if (empty($newDate) || empty($newTimeStart) || empty($newTimeEnd)) {
            Helpers::jsonResponse(['error' => 'Date et horaires requis'], 400);
        }
        
        // Récupérer l'ancienne réservation pour Google Calendar
        $bookingData = $booking->getById($id);
        
        $result = $booking->reschedule($id, $newDate, $newTimeStart, $newTimeEnd);
        
        if ($result['success']) {
            // Mettre à jour l'événement Google Calendar
            try {
                $gcal = new GoogleCalendarSync();
                if ($gcal->isEnabled()) {
                    // Récupérer les nouvelles données
                    $updatedBooking = $booking->getById($id);
                    
                    if (!empty($updatedBooking['google_event_id'])) {
                        $gcal->updateEvent($updatedBooking['google_event_id'], $updatedBooking);
                    } else {
                        // Créer l'événement s'il n'existe pas
                        $eventId = $gcal->createEvent($updatedBooking);
                        if ($eventId) {
                            $booking->updateGoogleEventId($id, $eventId);
                        }
                    }
                }
            } catch (Exception $e) {
                error_log('Google Calendar reschedule error: ' . $e->getMessage());
            }
            
            // Envoyer un email au visiteur pour l'informer du changement
            if (EMAIL_ENABLED && $bookingData) {
                $updatedBooking = $booking->getById($id);
                Mailer::notifyReschedule($bookingData, $updatedBooking);
            }
            
            Helpers::jsonResponse([
                'success' => true, 
                'message' => $result['message'],
                'details' => $result
            ]);
        } else {
            Helpers::jsonResponse(['success' => false, 'message' => $result['message']], 400);
        }
        break;
    
    // ============================================
    // CRÉNEAUX DISPONIBLES (GESTION)
    // ============================================
    case 'slots':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            Helpers::jsonResponse(['slots_by_day' => $slot->getSlotsByDay()]);
        }
        break;
    
    // ============================================
    // DATES BLOQUÉES
    // ============================================
    case 'blocked_dates':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            Helpers::jsonResponse(['blocked_dates' => $slot->getBlockedDates()]);
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = Helpers::getJsonInput();
            if (empty($input)) {
                $input = $_POST;
            }
            
            $date = $input['date'] ?? '';
            $reason = $input['reason'] ?? '';
            
            if (empty($date)) {
                Helpers::jsonResponse(['error' => 'Date requise'], 400);
            }
            
            if ($slot->blockDate($date, $reason)) {
                Helpers::jsonResponse(['success' => true, 'message' => 'Date bloquée']);
            } else {
                Helpers::jsonResponse(['success' => false, 'message' => 'Erreur ou date déjà bloquée'], 400);
            }
        }
        break;
    
    // ============================================
    // STATISTIQUES
    // ============================================
    case 'stats':
        Helpers::jsonResponse(['stats' => $booking->getStats()]);
        break;
    
    // ============================================
    // PARAMÈTRES - LECTURE
    // ============================================
    case 'settings':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            Helpers::jsonResponse([
                'settings' => $settings->getAll()
            ]);
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = Helpers::getJsonInput();
            if (empty($input)) {
                $input = $_POST;
            }
            
            $allowedKeys = [
                'google_calendar_enabled',
                'google_calendar_id',
                'admin_email',
                'admin_name',
                'admin_lastname',
                'admin_phone',
                'admin_address',
                'admin_siret',
                'legal_status',
                'admin_activity',
                'site_name'
            ];
            
            $toSave = [];
            foreach ($allowedKeys as $key) {
                if (isset($input[$key])) {
                    $toSave[$key] = Helpers::sanitize($input[$key]);
                }
            }
            
            if (empty($toSave)) {
                Helpers::jsonResponse(['error' => 'Aucun paramètre à sauvegarder'], 400);
            }
            
            if ($settings->setMultiple($toSave)) {
                Helpers::jsonResponse([
                    'success' => true,
                    'message' => 'Paramètres sauvegardés',
                    'settings' => $settings->getAll()
                ]);
            } else {
                Helpers::jsonResponse([
                    'success' => false,
                    'message' => 'Erreur lors de la sauvegarde'
                ], 500);
            }
        }
        break;
    
    // ============================================
    // ACTION INCONNUE
    // ============================================
    default:
        Helpers::jsonResponse([
            'error' => 'Action non reconnue',
            'available_actions' => ['list', 'get', 'update', 'delete', 'slots', 'blocked_dates', 'stats']
        ], 400);
}
