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
use App\Helpers;

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
$result  = $booking->create([
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
]);

if (!$result['success']) {
    // Conflit créneau (race active_key) ou erreur — retour vers slot
    // pour re-choisir.
    header('Location: slot.php'
        . '?service=' . (int) $draft['service_id']
        . '&date='    . urlencode($draft['slot_date']));
    exit;
}

// Mémoriser le résultat dans la session pour success.php
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
