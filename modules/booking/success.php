<?php
/**
 * ============================================
 * BOOKING v3 — CONFIRMATION
 * ============================================
 * Deux chemins :
 *  - Retour Stripe (?cs={CHECKOUT_SESSION_ID}) → on relit le booking par
 *    stripe_session_id : la SOURCE DE VÉRITÉ du paiement est la BDD (donc le
 *    webhook), JAMAIS le simple retour navigateur. Tant que le webhook n'a pas
 *    confirmé, on affiche « paiement en cours ».
 *  - Mode bypass (gratuit / Stripe off) → $_SESSION['booking_result'].
 */
require_once __DIR__ . '/../../includes/init.php';

use App\Helpers;
use App\Icons;
use App\Booking;

$cs          = isset($_GET['cs']) ? Helpers::sanitize((string) $_GET['cs']) : '';
$booking     = null;
$manageToken = null;

if ($cs !== '' && $cs !== '{CHECKOUT_SESSION_ID}') {
    $booking = (new Booking())->getByStripeSession($cs);
    if ($booking) {
        $manageToken = $booking['manage_token'] ?? null;
        $_SESSION['booking_draft'] = [];
    }
} elseif (!empty($_SESSION['booking_result']['booking_id'])) {
    $r = $_SESSION['booking_result'];
    $d = $r['draft'];
    $booking = [
        'service_name'    => $d['service_name']    ?? '',
        'duration_min'    => $d['duration_min']    ?? 0,
        'slot_date'       => $d['slot_date'],
        'slot_time_start' => $d['slot_time_start'],
        'slot_time_end'   => $d['slot_time_end'],
        'status'          => 'pending',
        'payment_status'  => 'none',
    ];
    $manageToken = $r['manage_token'] ?? null;
}

if (!$booking) {
    header('Location: index.php');
    exit;
}

$status = $booking['status'] ?? 'pending';
$paid   = ($status === 'confirmed' && ($booking['payment_status'] ?? '') === 'paid');

if ($paid) {
    $pageTitle = 'Paiement confirmé';
    $subtitle  = 'Votre paiement a été reçu et votre rendez-vous est confirmé. '
               . 'Un email de confirmation vous a été envoyé.';
    $badge     = ['confirmed', 'check-circle', 'Confirmé'];
} elseif ($status === 'pending_payment') {
    $pageTitle = 'Paiement en cours';
    $subtitle  = 'Nous finalisons la confirmation de votre paiement. Votre rendez-vous '
               . 'sera confirmé dans un instant — vous recevrez un email.';
    $badge     = ['pending', 'hourglass', 'En cours de confirmation'];
} else {
    $pageTitle = 'Demande envoyée';
    $subtitle  = 'Votre demande de rendez-vous a été enregistrée. Vous recevrez un email '
               . 'de confirmation dès que votre créneau sera validé.';
    $badge     = ['pending', 'hourglass', 'En attente'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= Helpers::csrfMeta() ?>
    <title><?= Helpers::escape($pageTitle) ?> | <?= Helpers::escape(siteConfig()['site_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/booking-v3.css">
    <?= pwaHead() ?>
</head>
<body>
    <header class="booking-header">
        <div class="booking-header-content">
            <a href="../../" class="booking-logo"><?= brandWordmark() ?></a>
            <a href="../../" class="back-link"><?= Icons::svg('arrow-left', 20, 'icon-inline') ?>Retour au site</a>
        </div>
    </header>

    <main class="booking-main">
        <div class="bv3-success">
            <div class="bv3-success-icon"><?= Icons::svg($paid ? 'check-circle' : 'check', 32) ?></div>
            <h1 class="page-title"><?= Helpers::escape($pageTitle) ?></h1>
            <p class="page-subtitle"><?= Helpers::escape($subtitle) ?></p>

            <dl class="bv3-recap-list">
                <dt>Prestation</dt>
                <dd><?= Helpers::escape((string) ($booking['service_name'] ?? '')) ?> · <?= Helpers::escape((string) (int) ($booking['duration_min'] ?? 0)) ?> min</dd>
                <dt>Date</dt>
                <dd><?= Helpers::escape(Helpers::formatDateFr($booking['slot_date'])) ?></dd>
                <dt>Horaire</dt>
                <dd><?= Helpers::escape(Helpers::formatTimeSlot($booking['slot_time_start'], $booking['slot_time_end'])) ?></dd>
                <dt>Statut</dt>
                <dd>
                    <span class="status-badge <?= Helpers::escape($badge[0]) ?>"><?= Icons::svg($badge[1], 14, 'icon-inline') ?><?= Helpers::escape($badge[2]) ?></span>
                </dd>
            </dl>

            <?php if (!empty($manageToken)): ?>
            <p class="bv3-manage-link">
                <a class="btn btn-secondary" href="manage.php?token=<?= Helpers::escape($manageToken) ?>">
                    Gérer mon rendez-vous
                </a>
            </p>
            <?php endif; ?>
        </div>
    </main>

    <footer class="booking-footer">
        <p>© <?= date('Y') ?> <?= Helpers::escape(siteConfig()['site_name']) ?></p>
    </footer>

    <script src="../../assets/js/icons.js"></script>
    <?= pwaRegister() ?>
</body>
</html>
