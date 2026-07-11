<?php
/**
 * ============================================
 * BOOKING v3 — ÉTAPE 3 : CHOIX DU CRÉNEAU
 * ============================================
 * Liste les créneaux calculés pour (service, date). Server-rendered.
 * Clic sur un créneau → confirm.php (formulaire identité).
 */
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/_shell.php';

use App\Database;
use App\Slot;
use App\Helpers;
use App\Icons;

$serviceId = isset($_GET['service']) ? (int) Helpers::sanitize($_GET['service']) : 0;
$date      = isset($_GET['date'])    ? Helpers::sanitize($_GET['date'])         : '';

if ($serviceId <= 0 || !Helpers::isValidDate($date)) {
    header('Location: index.php');
    exit;
}

$db   = Database::getInstance();
$stmt = $db->prepare(
    "SELECT id, name, duration_min, buffer_after_min, price_cents
       FROM services
      WHERE id = :id AND is_active = 1"
);
$stmt->execute([':id' => $serviceId]);
$service = $stmt->fetch();
if (!$service) {
    header('Location: index.php');
    exit;
}

$slot  = new Slot();
$slots = $slot->computeSlotsForService((int) $service['id'], $date);

// Mémoriser dans le draft (au cas où le draft ait été perdu)
$_SESSION['booking_draft']['service_id']       = (int) $service['id'];
$_SESSION['booking_draft']['service_name']     = $service['name'];
$_SESSION['booking_draft']['duration_min']     = (int) $service['duration_min'];
$_SESSION['booking_draft']['buffer_after_min'] = (int) $service['buffer_after_min'];
$_SESSION['booking_draft']['price_cents']      = (int) $service['price_cents'];

// Contexte forfait (§7) : porté depuis date.php si réservation par jeton.
$pack   = pack_context(isset($_GET['pack']) ? Helpers::sanitize((string) $_GET['pack']) : '', $serviceId);
$packQs = $pack ? '&amp;pack=' . Helpers::escape($pack['manage_token']) : '';

$pageTitle = 'Choisir un créneau';
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
    <?= booking_header('date.php?service=' . (int) $service['id'] . $packQs, 'Changer de date') ?>

    <main class="booking-main">
        <?= booking_steps(3, [
            1 => 'index.php',
            2 => 'date.php?service=' . (int) $service['id'] . $packQs,
        ]) ?>

        <div class="bv3-summary">
            <strong><?= Helpers::escape($service['name']) ?></strong>
            <span class="bv3-summary-sep">·</span>
            <?= Helpers::escape(Helpers::formatDateFr($date)) ?>
        </div>

        <?php if ($pack): ?>
        <div class="bv3-pack-banner">
            Forfait « <?= Helpers::escape($pack['package_name']) ?> » — <strong><?= (int) $pack['credits_remaining'] ?> jeton<?= (int) $pack['credits_remaining'] > 1 ? 's' : '' ?></strong> · le créneau choisi consommera 1 jeton.
        </div>
        <?php endif; ?>

        <h1 class="page-title text-center"><?= Helpers::escape($pageTitle) ?></h1>

        <?php if (empty($slots)): ?>
            <p class="bv3-empty">Aucun créneau disponible ce jour-là. <a href="date.php?service=<?= Helpers::escape((string) (int) $service['id']) ?>">Choisir une autre date</a>.</p>
        <?php else: ?>
            <ul class="bv3-slots">
                <?php foreach ($slots as $s): ?>
                    <li class="bv3-slot">
                        <a class="bv3-slot-link"
                           href="<?= $pack ? 'pack-book.php' : 'confirm.php' ?>?service=<?= Helpers::escape((string) (int) $service['id']) ?>&amp;date=<?= Helpers::escape($date) ?>&amp;start=<?= Helpers::escape($s['time_start']) ?>&amp;end=<?= Helpers::escape($s['time_end']) ?><?= $packQs ?>">
                            <?= Helpers::escape($s['label']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </main>

    <?= booking_footer() ?>

    <script src="../../assets/js/icons.js"></script>
    <?= pwaRegister() ?>
</body>
</html>
