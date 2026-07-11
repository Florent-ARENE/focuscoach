<?php
/**
 * ============================================
 * ESPACE CLIENT UNIFIÉ (§7.b)
 * ============================================
 * Vue d'ensemble d'un client, agrégée par EMAIL : ses forfaits (jetons), ses
 * séances à venir, son historique. NE REMPLACE PAS manage.php / pack.php : il
 * les AGRÈGE et renvoie vers eux pour les actions (annuler / déplacer / réserver
 * une séance de forfait). Réutilise n'importe quel token du client (résa OU
 * forfait) comme entrée : espace.php?token=… → email → agrégation.
 */
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/_shell.php';

use App\Booking;
use App\Package;
use App\Helpers;
use App\Icons;

$token = isset($_GET['token']) ? Helpers::sanitize((string) $_GET['token']) : '';

// Résoudre l'email du client depuis SON token (résa ou forfait).
$email = null;
$clientName = null;
$bookingModel = new Booking();
$packageModel = new Package();

if ($token !== '' && strlen($token) === MANAGE_TOKEN_LENGTH) {
    $b = $bookingModel->getByToken($token);
    if ($b) {
        $email = $b['visitor_email'];
        $clientName = $b['visitor_name'];
    } else {
        $p = $packageModel->getByToken($token);
        if ($p) {
            $email = $p['client_email'];
            $clientName = $p['client_name'];
        }
    }
}

$pageTitle = 'Mon espace';

$bookings = $email ? $bookingModel->getByEmail($email) : [];
$forfaits = $email ? $packageModel->getPurchasesByEmail($email) : [];

$today    = date('Y-m-d');
$isFuture = static function (array $b) use ($today): bool {
    return $b['slot_date'] >= $today && in_array($b['status'], ['pending', 'confirmed'], true);
};
$upcoming = array_values(array_filter($bookings, $isFuture));
$upcoming = array_reverse($upcoming); // getByEmail est DESC → à venir le plus proche d'abord
$history  = array_values(array_filter($bookings, static fn($b) => !$isFuture($b)));
$doneCount = count(array_filter($bookings, static fn($b) => $b['status'] === 'completed'));

// [icône, classe badge main.css] par statut de résa.
$bkBadge = [
    'pending'   => ['hourglass',    'pending'],
    'confirmed' => ['circle-check', 'confirmed'],
    'cancelled' => ['circle-x',     'cancelled'],
    'completed' => ['check',        'completed'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Helpers::escape($pageTitle) ?> | <?= Helpers::escape(siteConfig()['site_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/booking-v3.css">
    <link rel="stylesheet" href="../../assets/css/manage.css">
    <?= pwaHead() ?>
</head>
<body class="booking-page">
    <?= booking_header(Helpers::escape(BASE_URL), 'Retour au site') ?>

    <main class="booking-main">
        <h1 class="page-title text-center"><?= Helpers::escape($pageTitle) ?></h1>

        <?php if (!$email): ?>
            <div class="bv3-empty">Lien invalide ou expiré. <a href="index.php">Prendre rendez-vous</a>.</div>
        <?php else: ?>
            <p class="page-subtitle text-center">
                Bonjour <?= Helpers::escape($clientName ?: '') ?><?php if ($doneCount > 0): ?> — <strong><?= $doneCount ?></strong> séance<?= $doneCount > 1 ? 's' : '' ?> déjà réalisée<?= $doneCount > 1 ? 's' : '' ?><?php endif; ?>.
            </p>

            <!-- ── Forfaits actifs ── -->
            <?php $activeForfaits = array_filter($forfaits, static fn($f) => in_array($f['status'], ['active', 'pending_payment'], true)); ?>
            <?php if (!empty($activeForfaits)): ?>
            <h2 class="bv3-zone-title">Mes forfaits</h2>
            <?php foreach ($activeForfaits as $f): ?>
                <div class="booking-card">
                    <div class="booking-card-header">
                        <h3><?= Helpers::escape($f['package_name']) ?></h3>
                        <span class="status-badge <?= $f['is_usable'] ? 'confirmed' : 'completed' ?>">
                            <?= Icons::svg($f['is_usable'] ? 'circle-check' : 'hourglass', 14, 'icon-inline') ?>
                            <strong><?= (int) $f['credits_remaining'] ?></strong>&nbsp;/ <?= (int) $f['credits_total'] ?> jetons
                        </span>
                    </div>
                    <div class="booking-card-body">
                        <p class="text-muted"><?= Helpers::escape($f['service_name']) ?> · valable jusqu'au <?= $f['expires_at'] ? Helpers::escape(Helpers::formatDateFr(substr($f['expires_at'], 0, 10))) : '—' ?></p>
                    </div>
                    <div class="booking-card-actions">
                        <a class="btn btn-primary" href="pack.php?token=<?= Helpers::escape($f['manage_token']) ?>">
                            <?= Icons::svg('calendar', 18, 'icon-inline') ?><?= $f['is_usable'] ? 'Réserver une séance' : 'Voir mon forfait' ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php endif; ?>

            <!-- ── Séances à venir ── -->
            <h2 class="bv3-zone-title">Séances à venir</h2>
            <?php if (empty($upcoming)): ?>
                <div class="bv3-empty">Aucune séance à venir. <a href="index.php">Prendre rendez-vous</a>.</div>
            <?php else: ?>
                <?php foreach ($upcoming as $b): [$icn, $cls] = $bkBadge[$b['status']] ?? ['circle', 'pending']; ?>
                <div class="booking-card">
                    <div class="booking-card-header">
                        <h3><?= Helpers::escape($b['service_name'] ?? 'Séance') ?><?= $b['package_purchase_id'] ? ' <span class="text-muted">(forfait)</span>' : '' ?></h3>
                        <span class="status-badge <?= Helpers::escape($cls) ?>"><?= Icons::svg($icn, 14, 'icon-inline') ?><?= Helpers::escape($b['status_info']['label'] ?? ucfirst($b['status'])) ?></span>
                    </div>
                    <div class="booking-card-body">
                        <p><strong><?= Helpers::escape(Helpers::formatDateFr($b['slot_date'])) ?></strong> · <?= Helpers::escape(Helpers::formatTimeSlot($b['slot_time_start'], $b['slot_time_end'])) ?></p>
                    </div>
                    <div class="booking-card-actions">
                        <a class="btn btn-secondary" href="manage.php?token=<?= Helpers::escape($b['manage_token']) ?>">Gérer cette séance</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- ── Historique ── -->
            <?php if (!empty($history)): ?>
            <h2 class="bv3-zone-title">Historique</h2>
            <div class="booking-card">
                <div class="booking-card-body">
                    <ul class="bv3-dates">
                        <?php foreach ($history as $b): [$icn, $cls] = $bkBadge[$b['status']] ?? ['circle', 'pending']; ?>
                        <li class="bv3-date">
                            <span class="bv3-date-day"><?= Helpers::escape(Helpers::formatDateFr($b['slot_date'])) ?></span>
                            <span class="bv3-date-full"><?= Helpers::escape($b['service_name'] ?? 'Séance') ?></span>
                            <span class="status-badge <?= Helpers::escape($cls) ?>"><?= Icons::svg($icn, 12, 'icon-inline') ?><?= Helpers::escape($b['status_info']['label'] ?? ucfirst($b['status'])) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <p class="text-center bv3-help-note">
                <a href="index.php">Prendre un nouveau rendez-vous</a> · <a href="index.php#forfaits">Voir les forfaits</a>
            </p>
        <?php endif; ?>
    </main>

    <?= booking_footer() ?>

    <script src="../../assets/js/icons.js"></script>
    <?= pwaRegister() ?>
</body>
</html>
