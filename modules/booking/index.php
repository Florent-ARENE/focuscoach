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
        <p class="page-subtitle text-center">Sélectionnez la prestation qui correspond à votre besoin. La durée du créneau s'adaptera automatiquement.</p>

        <?php if (empty($services)): ?>
            <p class="bv3-empty">Aucune prestation n'est actuellement proposée. Merci de revenir plus tard.</p>
        <?php else: ?>
            <div class="bv3-segments">
                <?php foreach ($bySegment as $segKey => $list): if (empty($list)) continue; ?>
                <section class="bv3-segment">
                    <h2 class="bv3-segment-title"><?= Helpers::escape($segmentLabels[$segKey]) ?></h2>
                    <ul class="bv3-cards">
                        <?php foreach ($list as $s): ?>
                            <li class="bv3-card">
                                <div class="bv3-card-head">
                                    <h3 class="bv3-card-name"><?= Helpers::escape($s['name']) ?></h3>
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
    </main>

    <?= booking_footer() ?>

    <script src="../../assets/js/icons.js"></script>
    <?= pwaRegister() ?>
</body>
</html>
