<?php
/**
 * ============================================
 * FORFAITS (§7) — RÉSERVER UNE SÉANCE PAR JETON
 * ============================================
 * Le client (identifié par son token de forfait) pose une séance : pas de
 * paiement ni de formulaire d'identité (déjà connu). Le créneau consomme 1 jeton.
 *
 * GET  ?service&date&start&end&pack : récap + bouton « Réserver cette séance ».
 * POST : consomme 1 jeton (atomique) → crée la séance (garde anti-double-booking)
 *        → si le créneau est pris, REND le jeton → retour espace pack.
 */
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/_shell.php';

use App\Booking;
use App\Package;
use App\Helpers;
use App\Icons;

$serviceId = isset($_GET['service']) ? (int) Helpers::sanitize($_GET['service']) : (int) ($_POST['service'] ?? 0);
$date      = Helpers::sanitize((string) ($_GET['date']  ?? $_POST['date']  ?? ''));
$start     = Helpers::sanitize((string) ($_GET['start'] ?? $_POST['start'] ?? ''));
$end       = Helpers::sanitize((string) ($_GET['end']   ?? $_POST['end']   ?? ''));
$token     = Helpers::sanitize((string) ($_GET['pack']  ?? $_POST['pack']  ?? ''));

// Garde : token de forfait valide, utilisable, et pour CE service.
$pack = pack_context($token, $serviceId);
if (!$pack
    || !Helpers::isValidDate($date)
    || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $start)
    || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $end)) {
    // Token présent mais achat épuisé/expiré → on renvoie vers l'espace pack.
    if ($token !== '' && strlen($token) === MANAGE_TOKEN_LENGTH) {
        header('Location: pack.php?token=' . urlencode($token));
    } else {
        header('Location: index.php');
    }
    exit;
}

$error   = null;
$slotUrl = 'slot.php?service=' . $serviceId . '&date=' . urlencode($date) . '&pack=' . urlencode($token);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Helpers::verifyCsrfFromRequest($_POST)) {
        http_response_code(403);
        echo 'Jeton CSRF invalide.';
        exit;
    }

    $packageModel = new Package();
    // 1) Consomme 1 jeton (atomique). Échec = épuisé/expiré entre-temps.
    $consumed = $packageModel->consumeCredit((int) $pack['id']);
    if (!$consumed['success']) {
        $error = $consumed['message'];
    } else {
        // 2) Crée la séance (payée par jeton), avec la garde anti-double-booking.
        $data = [
            'visitor_name'         => $pack['client_name'],
            'visitor_email'        => $pack['client_email'],
            'visitor_phone'        => null,
            'visitor_organization' => null,
            'slot_date'            => $date,
            'slot_time_start'      => $start,
            'slot_time_end'        => $end,
            'subject'              => 'Séance forfait — ' . $pack['package_name'],
            'message'              => null,
            'service_id'           => (int) $pack['service_id'],
            'duration_min'         => (int) $pack['duration_min'],
            'buffer_after_min'     => (int) $pack['buffer_after_min'],
        ];
        $result = (new Booking())->createFromPackage($data, (int) $pack['id']);

        if ($result['success']) {
            header('Location: pack.php?token=' . urlencode($token) . '&booked=1');
            exit;
        }
        // 3) Créneau pris entre-temps → on REND le jeton (pas de perte).
        $packageModel->refundCredit((int) $pack['id']);
        $error = $result['message'] ?? 'Ce créneau vient d\'être réservé. Veuillez en choisir un autre.';
    }
}

$pageTitle = 'Confirmer la séance';
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
<body class="booking-page">
    <?= booking_header(Helpers::escape($slotUrl), 'Changer de créneau') ?>

    <main class="booking-main">
        <h1 class="page-title text-center"><?= Helpers::escape($pageTitle) ?></h1>

        <div class="bv3-pack-banner">
            Forfait « <?= Helpers::escape($pack['package_name']) ?> » — <strong><?= (int) $pack['credits_remaining'] ?> jeton<?= (int) $pack['credits_remaining'] > 1 ? 's' : '' ?> restant<?= (int) $pack['credits_remaining'] > 1 ? 's' : '' ?></strong> · cette séance en consommera 1.
        </div>

        <div class="bv3-recap">
            <h2 class="bv3-recap-title"><?= Icons::svg('calendar', 20, 'icon-inline') ?>Votre séance</h2>
            <dl class="bv3-recap-list">
                <dt>Prestation</dt>
                <dd><?= Helpers::escape($pack['service_name']) ?> · <?= Helpers::escape((string) (int) $pack['duration_min']) ?> min</dd>
                <dt>Date</dt>
                <dd><?= Helpers::escape(Helpers::formatDateFr($date)) ?></dd>
                <dt>Horaire</dt>
                <dd><?= Helpers::escape(Helpers::formatTimeSlot($start, $end)) ?></dd>
                <dt>Client</dt>
                <dd><?= Helpers::escape($pack['client_name']) ?></dd>
            </dl>
        </div>

        <?php if ($error): ?>
            <p class="bv3-empty" role="alert"><?= Helpers::escape($error) ?> <a href="<?= Helpers::escape($slotUrl) ?>">Choisir un autre créneau</a>.</p>
        <?php endif; ?>

        <form action="pack-book.php" method="post" class="bv3-form" novalidate>
            <?= Helpers::csrfField() ?>
            <input type="hidden" name="service" value="<?= Helpers::escape((string) $serviceId) ?>">
            <input type="hidden" name="date"    value="<?= Helpers::escape($date) ?>">
            <input type="hidden" name="start"   value="<?= Helpers::escape($start) ?>">
            <input type="hidden" name="end"     value="<?= Helpers::escape($end) ?>">
            <input type="hidden" name="pack"    value="<?= Helpers::escape($token) ?>">

            <div class="bv3-actions">
                <a href="<?= Helpers::escape($slotUrl) ?>" class="btn btn-secondary">Retour</a>
                <button type="submit" class="btn btn-primary">Réserver (1 jeton) <?= Icons::svg('arrow-right', 16, 'icon-inline') ?></button>
            </div>
        </form>
    </main>

    <?= booking_footer() ?>

    <script src="../../assets/js/icons.js"></script>
    <?= pwaRegister() ?>
</body>
</html>
