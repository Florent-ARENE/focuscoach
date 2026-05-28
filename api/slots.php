<?php
/**
 * ============================================
 * API : CRÉNEAUX DISPONIBLES
 * ============================================
 * GET /api/slots.php
 * GET /api/slots.php?date=YYYY-MM-DD
 * GET /api/slots.php?month=YYYY-MM
 * GET /api/slots.php?stats=1  (statistiques publiques)
 */

require_once __DIR__ . '/../includes/init.php';

use App\Slot;
use App\Booking;
use App\Helpers;

// Headers CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Vérifier la méthode
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Helpers::jsonResponse(['error' => 'Méthode non autorisée'], 405);
}

$slot = new Slot();

// Statistiques publiques
if (isset($_GET['stats'])) {
    $booking = new Booking();
    $stats = $booking->getPublicStats();
    
    Helpers::jsonResponse([
        'stats' => $stats
    ]);
}

// Créneaux pour une date spécifique
if (isset($_GET['date'])) {
    $date = Helpers::sanitize($_GET['date']);
    
    if (!Helpers::isValidDate($date)) {
        Helpers::jsonResponse(['error' => 'Format de date invalide (YYYY-MM-DD)'], 400);
    }
    
    // Vérifier la plage autorisée
    $requestedDate = new DateTime($date);
    $minDate = new DateTime('tomorrow');
    $maxDate = (clone $minDate)->modify('+' . BOOKING_ADVANCE_MAX_DAYS . ' days');
    
    if ($requestedDate < $minDate || $requestedDate > $maxDate) {
        Helpers::jsonResponse([
            'date' => $date,
            'slots' => [],
            'message' => 'Date hors plage de réservation'
        ]);
    }
    
    $slots = $slot->getAvailableForDate($date);
    
    Helpers::jsonResponse([
        'date' => $date,
        'formatted_date' => Helpers::formatDateFr($date),
        'day_name' => DAYS_OF_WEEK[(int)$requestedDate->format('N')],
        'slots' => $slots,
        'slots_count' => count($slots)
    ]);
}

// Disponibilités pour un mois
if (isset($_GET['month'])) {
    $month = Helpers::sanitize($_GET['month']);
    
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        Helpers::jsonResponse(['error' => 'Format de mois invalide (YYYY-MM)'], 400);
    }
    
    $year = (int) substr($month, 0, 4);
    $monthNum = (int) substr($month, 5, 2);
    
    $dates = $slot->getAvailabilityForMonth($year, $monthNum);
    
    // Récupérer les stats de réservation par date
    $booking = new Booking();
    $startDate = sprintf('%04d-%02d-01', $year, $monthNum);
    $endDate = date('Y-m-t', strtotime($startDate)); // Dernier jour du mois
    $bookingStats = $booking->getBookingsByDateRange($startDate, $endDate);
    
    Helpers::jsonResponse([
        'month' => $month,
        'dates' => $dates,
        'booking_stats' => $bookingStats
    ]);
}

// Par défaut : toutes les dates disponibles
$availableDates = $slot->getAvailableDates();

Helpers::jsonResponse([
    'available_dates' => $availableDates,
    'total' => count($availableDates)
]);
