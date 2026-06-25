<?php
/**
 * ============================================
 * BOOKING v3 — ÉTAPE 4 : FORMULAIRE IDENTITÉ + CONFIRMATION
 * ============================================
 * Le visiteur saisit son identité. Le bouton de validation soumet
 * vers process.php.
 *
 * ⚠️ §5 livre le tunnel SANS paiement (mode validation admin) — le
 * booking est créé en `status = 'pending'`, `payment_status = 'none'`.
 * §6 réécrira ce flux pour insérer Stripe Checkout entre confirm
 * et success (court-circuit si clés absentes ou price_cents == 0).
 */
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/_shell.php';

use App\Database;
use App\Helpers;
use App\Icons;

$serviceId = isset($_GET['service']) ? (int) Helpers::sanitize($_GET['service']) : 0;
$date      = isset($_GET['date'])    ? Helpers::sanitize($_GET['date'])         : '';
$start     = isset($_GET['start'])   ? Helpers::sanitize($_GET['start'])        : '';
$end       = isset($_GET['end'])     ? Helpers::sanitize($_GET['end'])          : '';

if ($serviceId <= 0
    || !Helpers::isValidDate($date)
    || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $start)
    || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $end)) {
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

// Compléter le draft (créneau choisi)
$_SESSION['booking_draft'] = array_merge($_SESSION['booking_draft'] ?? [], [
    'service_id'       => (int) $service['id'],
    'service_name'     => $service['name'],
    'duration_min'     => (int) $service['duration_min'],
    'buffer_after_min' => (int) $service['buffer_after_min'],
    'price_cents'      => (int) $service['price_cents'],
    'slot_date'        => $date,
    'slot_time_start'  => $start,
    'slot_time_end'    => $end,
]);

$pageTitle = 'Vos informations';
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
    <?= booking_header('slot.php?service=' . (int) $service['id'] . '&amp;date=' . Helpers::escape($date), 'Changer de créneau') ?>

    <main class="booking-main">
        <ol class="bv3-steps" aria-label="Étapes de la réservation">
            <li class="bv3-step is-done"><span class="bv3-step-num">1</span> Prestation</li>
            <li class="bv3-step is-done"><span class="bv3-step-num">2</span> Date</li>
            <li class="bv3-step is-done"><span class="bv3-step-num">3</span> Créneau</li>
            <li class="bv3-step is-current" aria-current="step"><span class="bv3-step-num">4</span> Vos informations</li>
        </ol>

        <div class="bv3-recap">
            <h2 class="bv3-recap-title"><?= Icons::svg('calendar', 20, 'icon-inline') ?>Récapitulatif</h2>
            <dl class="bv3-recap-list">
                <dt>Prestation</dt>
                <dd><?= Helpers::escape($service['name']) ?> · <?= Helpers::escape((string) (int) $service['duration_min']) ?> min</dd>
                <dt>Date</dt>
                <dd><?= Helpers::escape(Helpers::formatDateFr($date)) ?></dd>
                <dt>Horaire</dt>
                <dd><?= Helpers::escape(Helpers::formatTimeSlot($start, $end)) ?></dd>
                <dt>Tarif</dt>
                <dd>
                    <?php $priceCents = (int) $service['price_cents']; ?>
                    <?php if ($priceCents === 0): ?>
                        Gratuit
                    <?php else: ?>
                        <?= Helpers::escape(number_format($priceCents / 100, 2, ',', ' ')) ?> €
                    <?php endif; ?>
                </dd>
            </dl>
        </div>

        <h1 class="page-title text-center"><?= Helpers::escape($pageTitle) ?></h1>

        <form action="process.php" method="post" class="bv3-form" novalidate>
            <?= Helpers::csrfField() ?>

            <div class="form-group">
                <label class="form-label" for="visitor_name">Nom complet <span class="required">*</span></label>
                <input type="text" class="form-input" id="visitor_name" name="visitor_name" required placeholder="Jean Dupont">
            </div>

            <div class="form-group">
                <label class="form-label" for="visitor_email">Email <span class="required">*</span></label>
                <input type="email" class="form-input" id="visitor_email" name="visitor_email" required placeholder="jean.dupont@email.fr">
            </div>

            <div class="form-group">
                <label class="form-label" for="visitor_phone">Téléphone</label>
                <input type="tel" class="form-input" id="visitor_phone" name="visitor_phone" placeholder="06 12 34 56 78">
            </div>

            <div class="form-group">
                <label class="form-label" for="visitor_organization">Organisation</label>
                <input type="text" class="form-input" id="visitor_organization" name="visitor_organization" placeholder="Nom de votre structure (optionnel)">
            </div>

            <div class="form-group">
                <label class="form-label" for="subject">Objet du rendez-vous</label>
                <input type="text" class="form-input" id="subject" name="subject" placeholder="Ex: Préparation événement, suivi performance…">
            </div>

            <div class="form-group">
                <label class="form-label" for="message">Message (optionnel)</label>
                <textarea class="form-textarea" id="message" name="message" placeholder="Décrivez brièvement votre contexte ou vos attentes…"></textarea>
            </div>

            <div class="rgpd-notice">
                <p>
                    En soumettant ce formulaire, vous demandez à être recontacté(e) dans le cadre de votre demande de rendez-vous.
                    Vos données sont traitées par <?= Helpers::escape(siteConfig()['site_name']) ?> sur la base de l'exécution de mesures
                    précontractuelles (art. 6.1.b RGPD). Elles sont conservées 365 jours.
                    Pour en savoir plus, consultez notre <a href="../../confidentialite.php" target="_blank" rel="noopener">Politique de confidentialité</a>.
                </p>
            </div>

            <div class="bv3-actions">
                <a href="slot.php?service=<?= Helpers::escape((string) (int) $service['id']) ?>&amp;date=<?= Helpers::escape($date) ?>" class="btn btn-secondary">Retour</a>
                <button type="submit" class="btn btn-primary">Confirmer le rendez-vous <?= Icons::svg('arrow-right', 16, 'icon-inline') ?></button>
            </div>
        </form>
    </main>

    <?= booking_footer() ?>

    <script src="../../assets/js/icons.js"></script>
    <?= pwaRegister() ?>
</body>
</html>
