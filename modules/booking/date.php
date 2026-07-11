<?php
/**
 * ============================================
 * BOOKING v3 — ÉTAPE 2 : CHOIX DE LA DATE
 * ============================================
 * Liste les jours qui offrent au moins un créneau pour le service
 * sélectionné, sur l'horizon de réservation (MAX_HORIZON_DAYS).
 * Server-rendered (pas de fetch JS) — le user clique sur un jour
 * et passe directement à slot.php?service=…&date=….
 */
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/_shell.php';

use App\Database;
use App\Slot;
use App\Helpers;
use App\Icons;

$serviceId = isset($_GET['service']) ? (int) Helpers::sanitize($_GET['service']) : 0;
if ($serviceId <= 0) {
    header('Location: index.php');
    exit;
}

$db   = Database::getInstance();
$stmt = $db->prepare(
    "SELECT id, slug, segment, name, duration_min, buffer_after_min, price_cents
       FROM services
      WHERE id = :id AND is_active = 1"
);
$stmt->execute([':id' => $serviceId]);
$service = $stmt->fetch();
if (!$service) {
    header('Location: index.php');
    exit;
}

// Contexte forfait (§7) : si on vient de l'espace pack avec un token valide
// pour CE service, on le porte à travers l'étape créneau (→ pack-book.php).
$pack   = pack_context(isset($_GET['pack']) ? Helpers::sanitize((string) $_GET['pack']) : '', $serviceId);
$packQs = $pack ? '&amp;pack=' . Helpers::escape($pack['manage_token']) : '';

// Mémoriser le choix dans le draft
$_SESSION['booking_draft'] = [
    'service_id'       => (int) $service['id'],
    'service_name'     => $service['name'],
    'duration_min'     => (int) $service['duration_min'],
    'buffer_after_min' => (int) $service['buffer_after_min'],
    'price_cents'      => (int) $service['price_cents'],
];

$slot  = new Slot();
$dates = $slot->getServiceAvailableDates((int) $service['id']);

$pageTitle = 'Choisir une date';
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
    <?= $pack
        ? booking_header('pack.php?token=' . Helpers::escape($pack['manage_token']), 'Retour à mon forfait')
        : booking_header('index.php', 'Changer de prestation') ?>

    <main class="booking-main">
        <?= booking_steps(2, [1 => 'index.php']) ?>

        <div class="bv3-summary">
            <strong><?= Helpers::escape($service['name']) ?></strong>
            <span class="bv3-summary-sep">·</span>
            <?= Helpers::escape((string) (int) $service['duration_min']) ?> min
        </div>

        <?php if ($pack): ?>
        <div class="bv3-pack-banner">
            Réservation avec votre forfait « <?= Helpers::escape($pack['package_name']) ?> » —
            <strong><?= (int) $pack['credits_remaining'] ?> jeton<?= (int) $pack['credits_remaining'] > 1 ? 's' : '' ?> restant<?= (int) $pack['credits_remaining'] > 1 ? 's' : '' ?></strong> · aucun paiement, une séance = un jeton.
        </div>
        <?php endif; ?>

        <h1 class="page-title text-center"><?= Helpers::escape($pageTitle) ?></h1>
        <p class="page-subtitle text-center">Choisissez le jour qui vous convient. Les créneaux précis apparaîtront ensuite.</p>

        <?php /* Calendrier (progressive enhancement) : rempli par booking-calendar.js
                 depuis l'API mois. Sans JS, il reste `hidden` et la liste de secours
                 ci-dessous prend le relais. */ ?>
        <div id="bv3-calendar" class="bv3-cal"
             data-service="<?= Helpers::escape((string) (int) $service['id']) ?>"
             data-min-month="<?= Helpers::escape(date('Y-m')) ?>"
             data-max-month="<?= Helpers::escape(date('Y-m', strtotime('+' . (int) MAX_HORIZON_DAYS . ' days'))) ?>"
             data-today="<?= Helpers::escape(date('Y-m-d')) ?>"
             data-pack="<?= $pack ? Helpers::escape($pack['manage_token']) : '' ?>"
             hidden></div>

        <div id="bv3-dates-fallback">
        <?php if (empty($dates)): ?>
            <p class="bv3-empty">Aucune date disponible sur les <?= Helpers::escape((string) (int) MAX_HORIZON_DAYS) ?> prochains jours. Merci de revenir plus tard.</p>
        <?php else: ?>
            <ul class="bv3-dates">
                <?php foreach ($dates as $d): ?>
                    <li class="bv3-date">
                        <a class="bv3-date-link"
                           href="slot.php?service=<?= Helpers::escape((string) (int) $service['id']) ?>&amp;date=<?= Helpers::escape($d['date']) ?><?= $packQs ?>">
                            <span class="bv3-date-day"><?= Helpers::escape($d['day_name']) ?></span>
                            <span class="bv3-date-full"><?= Helpers::escape($d['formatted']) ?></span>
                            <span class="bv3-date-count"><?= Helpers::escape((string) (int) $d['slots_count']) ?> créneaux</span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        </div>
    </main>

    <?= booking_footer() ?>

    <script src="../../assets/js/icons.js"></script>
    <script src="../../assets/js/booking-calendar.js"></script>
    <?= pwaRegister() ?>
</body>
</html>
