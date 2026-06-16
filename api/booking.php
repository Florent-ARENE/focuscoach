<?php
/**
 * ============================================
 * API : CRÉATION DE RÉSERVATION
 * ============================================
 * POST /api/booking.php
 */

require_once __DIR__ . '/../includes/init.php';

use App\Booking;
use App\Mailer;
use App\Helpers;
use App\GoogleCalendarSync;

// Headers CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Vérifier la méthode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Helpers::jsonResponse(['error' => 'Méthode non autorisée'], 405);
}

// Récupérer les données
$input = Helpers::getJsonInput();
if (empty($input)) {
    $input = $_POST;
}

// CSRF obligatoire (AD §4 — socle). Avant toute logique métier.
if (!Helpers::verifyCsrfFromRequest($input)) {
    Helpers::csrfFailure();
}

// Validation
$errors = [];

$requiredFields = ['visitor_name', 'visitor_email', 'slot_date', 'slot_time_start', 'slot_time_end'];
foreach ($requiredFields as $field) {
    if (empty($input[$field])) {
        $errors[$field] = 'Ce champ est requis';
    }
}

if (!empty($input['visitor_email']) && !Helpers::isValidEmail($input['visitor_email'])) {
    $errors['visitor_email'] = 'Email invalide';
}

if (!empty($input['slot_date']) && !Helpers::isValidDate($input['slot_date'])) {
    $errors['slot_date'] = 'Format de date invalide';
}

if (!empty($input['slot_time_start']) && !Helpers::isValidTime($input['slot_time_start'])) {
    $errors['slot_time_start'] = 'Format d\'heure invalide';
}

if (!empty($errors)) {
    Helpers::jsonResponse([
        'success' => false,
        'errors' => $errors,
        'message' => 'Veuillez corriger les erreurs'
    ], 400);
}

// Préparer les données
$bookingData = [
    'visitor_name' => Helpers::sanitize($input['visitor_name']),
    'visitor_email' => Helpers::sanitize($input['visitor_email']),
    'visitor_phone' => !empty($input['visitor_phone']) ? Helpers::sanitize($input['visitor_phone']) : null,
    'visitor_organization' => !empty($input['visitor_organization']) ? Helpers::sanitize($input['visitor_organization']) : null,
    'slot_date' => $input['slot_date'],
    'slot_time_start' => $input['slot_time_start'],
    'slot_time_end' => $input['slot_time_end'],
    'service_type' => !empty($input['service_type']) ? Helpers::sanitize($input['service_type']) : 'autre',
    'subject' => !empty($input['subject']) ? Helpers::sanitize($input['subject']) : null,
    'message' => !empty($input['message']) ? Helpers::sanitize($input['message']) : null
];

// Créer la réservation
$booking = new Booking();
$result = $booking->create($bookingData);

if ($result['success']) {
    $bookingId = $result['booking_id'];
    $bookingData['id'] = $bookingId;
    $bookingData['status'] = 'pending';
    $bookingData['manage_token'] = $result['manage_token']; // Token pour gestion client
    
    // Envoyer les emails
    if (EMAIL_ENABLED) {
        Mailer::notifyNewBooking($bookingData);
    }
    
    // Sync Google Calendar (vérifie si activé en BDD)
    try {
        $gcal = new GoogleCalendarSync();
        if ($gcal->isEnabled()) {
            $eventId = $gcal->createEvent($bookingData);
            if ($eventId) {
                $booking->updateGoogleEventId($bookingId, $eventId);
            }
        }
    } catch (Exception $e) {
        // Log l'erreur mais ne bloque pas la réservation
        error_log('Google Calendar sync error: ' . $e->getMessage());
    }
    
    Helpers::jsonResponse([
        'success' => true,
        'message' => $result['message'],
        'booking_id' => $bookingId
    ], 201);
} else {
    Helpers::jsonResponse([
        'success' => false,
        'message' => $result['message']
    ], 400);
}
