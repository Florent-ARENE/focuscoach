<?php
/**
 * ============================================
 * BOOKING v3 — ÉTAPE 1 : CHOIX DE LA PRESTATION
 * ============================================
 * Première étape du tunnel multi-pages. Le visiteur choisit la
 * prestation, ce qui fixe la durée + le buffer du créneau qui
 * sera calculé à l'étape 2.
 *
 * État du tunnel : $_SESSION['booking_draft']. Cette page le
 * RÉINITIALISE (un nouveau choix de prestation rebat les cartes).
 */
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/_shell.php';

use App\Database;
use App\Helpers;
use App\Icons;

$pageTitle = 'Choisir une prestation';
$db        = Database::getInstance();
$stmt      = $db->query(
    "SELECT id, slug, segment, name, description, duration_min, price_cents
       FROM services
      WHERE is_active = 1
      ORDER BY segment, sort_order, name"
);
$services = $stmt->fetchAll();

// Regrouper par segment pour le rendu en colonnes
$bySegment = ['sportif' => [], 'dirigeant' => [], 'particulier' => []];
foreach ($services as $s) {
    if (isset($bySegment[$s['segment']])) {
        $bySegment[$s['segment']][] = $s;
    }
}
$segmentLabels = [
    'sportif'     => 'Sportifs',
    'dirigeant'   => 'Dirigeants',
    'particulier' => 'Particuliers',
];

// Forfaits (jetons) — groupés par segment, affichés SOUS les séances à l'unité.
$packages      = (new \App\Package())->getActivePackages();
$packBySegment = ['sportif' => [], 'dirigeant' => [], 'particulier' => []];
foreach ($packages as $p) {
    if (isset($packBySegment[$p['segment']])) {
        $packBySegment[$p['segment']][] = $p;
    }
}

// Réinit du draft sur cette étape
$_SESSION['booking_draft'] = [];
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
    <?= booking_header(Helpers::escape(BASE_URL), 'Retour au site') ?>

    <main class="booking-main">
        <ol class="bv3-steps" aria-label="Étapes de la réservation">
            <li class="bv3-step is-current" aria-current="step"><span class="bv3-step-num">1</span> Prestation</li>
            <li class="bv3-step"><span class="bv3-step-num">2</span> Date</li>
            <li class="bv3-step"><span class="bv3-step-num">3</span> Créneau</li>
            <li class="bv3-step"><span class="bv3-step-num">4</span> Vos informations</li>
        </ol>

        <h1 class="page-title text-center"><?= Helpers::escape($pageTitle) ?></h1>
        <p class="page-subtitle text-center">Une <strong>séance à l'unité</strong> pour un besoin ponctuel, ou un <strong>forfait</strong> (plusieurs séances prépayées, à jetons) pour un accompagnement dans la durée.</p>

        <?php if (empty($services) && empty($packages)): ?>
            <p class="bv3-empty">Aucune prestation n'est actuellement proposée. Merci de revenir plus tard.</p>
        <?php endif; ?>

        <?php if (!empty($services)): ?>
            <h2 class="bv3-zone-title">Séances à l'unité <span class="bv3-zone-sub">— une séance, un créneau</span></h2>
            <div class="bv3-segments">
                <?php foreach ($bySegment as $segKey => $list): if (empty($list)) continue; ?>
                <section class="bv3-segment">
                    <h3 class="bv3-segment-title"><?= Helpers::escape($segmentLabels[$segKey]) ?></h3>
                    <ul class="bv3-cards">
                        <?php foreach ($list as $s): ?>
                            <li class="bv3-card">
                                <div class="bv3-card-head">
                                    <h4 class="bv3-card-name"><?= Helpers::escape($s['name']) ?></h4>
                                    <span class="bv3-card-duration"><?= Helpers::escape((string) (int) $s['duration_min']) ?> min</span>
                                </div>
                                <?php if (!empty($s['description'])): ?>
                                    <p class="bv3-card-desc"><?= Helpers::escape($s['description']) ?></p>
                                <?php endif; ?>
                                <div class="bv3-card-foot">
                                    <span class="bv3-card-price">
                                        <?php $priceCents = (int) $s['price_cents']; ?>
                                        <?php if ($priceCents === 0): ?>
                                            Gratuit
                                        <?php else: ?>
                                            <?= Helpers::escape(number_format($priceCents / 100, 0, ',', ' ')) ?> €
                                        <?php endif; ?>
                                    </span>
                                    <a class="btn btn-primary bv3-card-cta"
                                       href="date.php?service=<?= Helpers::escape((string) (int) $s['id']) ?>">
                                        Choisir <?= Icons::svg('arrow-right', 16, 'icon-inline') ?>
                                    </a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($packages)): ?>
            <h2 class="bv3-zone-title bv3-zone-title--packs">Forfaits <span class="bv3-zone-sub">— plusieurs séances prépayées, à jetons</span></h2>
            <div class="bv3-segments">
                <?php foreach ($packBySegment as $segKey => $list): if (empty($list)) continue; ?>
                <section class="bv3-segment">
                    <h3 class="bv3-segment-title"><?= Helpers::escape($segmentLabels[$segKey]) ?></h3>
                    <ul class="bv3-cards">
                        <?php foreach ($list as $p): ?>
                            <li class="bv3-card bv3-card--pack">
                                <div class="bv3-card-head">
                                    <h4 class="bv3-card-name"><?= Helpers::escape($p['name']) ?></h4>
                                    <span class="bv3-card-duration"><?= Helpers::escape((string) (int) $p['sessions_count']) ?> jetons</span>
                                </div>
                                <p class="bv3-card-desc">
                                    <?= Helpers::escape((string) (int) $p['sessions_count']) ?> séances de « <?= Helpers::escape($p['service_name']) ?> »
                                    (<?= Helpers::escape((string) (int) $p['duration_min']) ?> min) · valable <?= Helpers::escape((string) (int) $p['validity_days']) ?> jours.
                                </p>
                                <div class="bv3-card-foot">
                                    <span class="bv3-card-price"><?= Helpers::escape(number_format((int) $p['price_cents'] / 100, 0, ',', ' ')) ?> €</span>
                                    <a class="btn btn-primary bv3-card-cta"
                                       href="pack-buy.php?package=<?= Helpers::escape((string) (int) $p['id']) ?>">
                                        Choisir <?= Icons::svg('arrow-right', 16, 'icon-inline') ?>
                                    </a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <?= booking_footer() ?>

    <script src="../../assets/js/icons.js"></script>
    <?= pwaRegister() ?>
</body>
</html>
