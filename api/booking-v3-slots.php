<?php
/**
 * ============================================
 * API : CRÉNEAUX DISPONIBLES — Booking v3 (service-aware)
 * ============================================
 * GET /api/booking-v3-slots.php?service=<id>
 *     → dates disponibles sur l'horizon (MAX_HORIZON_DAYS)
 * GET /api/booking-v3-slots.php?service=<id>&date=YYYY-MM-DD
 *     → créneaux du jour pour le service
 * GET /api/booking-v3-slots.php?service=<id>&month=YYYY-MM
 *     → disponibilités du mois (vue calendrier)
 *
 * Seul endpoint slots du projet depuis la purge 2.6.0 (l'ancien
 * `api/slots.php` et le tunnel legacy `booking/index.php` ont été
 * retirés). Consommé par les 3 pages d'index/date/slot du tunnel
 * v3 sous `modules/booking/`.
 */

require_once __DIR__ . '/../includes/init.php';

use App\Slot;
use App\Helpers;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Helpers::jsonResponse(['error' => 'Méthode non autorisée'], 405);
}

$serviceId = isset($_GET['service']) ? (int) Helpers::sanitize($_GET['service']) : 0;
if ($serviceId <= 0) {
    Helpers::jsonResponse(['error' => 'Paramètre `service` requis (id de prestation)'], 400);
}

$slot = new Slot();

if (isset($_GET['date'])) {
    $date = Helpers::sanitize($_GET['date']);
    if (!Helpers::isValidDate($date)) {
        Helpers::jsonResponse(['error' => 'Format de date invalide (YYYY-MM-DD)'], 400);
    }
    $slots = $slot->computeSlotsForService($serviceId, $date);
    Helpers::jsonResponse([
        'service_id'     => $serviceId,
        'date'           => $date,
        'formatted_date' => Helpers::formatDateFr($date),
        'slots'          => $slots,
        'slots_count'    => count($slots),
    ]);
}

if (isset($_GET['month'])) {
    $month = Helpers::sanitize($_GET['month']);
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        Helpers::jsonResponse(['error' => 'Format de mois invalide (YYYY-MM)'], 400);
    }
    $year     = (int) substr($month, 0, 4);
    $monthNum = (int) substr($month, 5, 2);
    $dates    = $slot->getServiceAvailabilityForMonth($serviceId, $year, $monthNum);
    Helpers::jsonResponse([
        'service_id' => $serviceId,
        'month'      => $month,
        'dates'      => $dates,
    ]);
}

$dates = $slot->getServiceAvailableDates($serviceId);
Helpers::jsonResponse([
    'service_id'      => $serviceId,
    'available_dates' => $dates,
    'total'           => count($dates),
]);
