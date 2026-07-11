<?php
/**
 * ============================================
 * FORFAITS (§7) — ACHAT D'UN FORFAIT À JETONS
 * ============================================
 * GET  ?package=<id> : récap du forfait + formulaire (nom / email).
 * POST : crée le package_purchase puis, selon paymentMode() :
 *   - stripe → Checkout Stripe (activation au webhook) ;
 *   - bypass → achat 'active' direct → redirection vers l'espace pack.
 *
 * Même coquille que le tunnel (mobile-first, PWA), CSRF comme process.php.
 */
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/_shell.php';

use App\Package;
use App\Helpers;
use App\Icons;
use App\Mailer;
use App\StripeClient;

$packageModel = new Package();
$pkgId = (int) Helpers::sanitize((string) ($_GET['package'] ?? $_POST['package'] ?? '0'));
$pkg   = $packageModel->getById($pkgId);
if (!$pkg || (int) $pkg['is_active'] !== 1) {
    header('Location: index.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Helpers::verifyCsrfFromRequest($_POST)) {
        http_response_code(403);
        echo 'Jeton CSRF invalide.';
        exit;
    }
    $name  = trim(Helpers::sanitize($_POST['client_name']  ?? ''));
    $email = trim(Helpers::sanitize($_POST['client_email'] ?? ''));

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Merci d\'indiquer votre nom et un email valide.';
    } else {
        $mode = \App\paymentMode($pkg);
        $r    = $packageModel->createPurchase($pkgId, $name, $email, $mode);
        if (!$r['success']) {
            $error = $r['message'] ?? 'Erreur lors de la création du forfait.';
        } elseif ($mode === 'stripe') {
            // Paiement forfait via Stripe Checkout (activation au webhook).
            $session = StripeClient::createCheckoutSession(
                (string) $pkg['stripe_price_id'],
                BASE_URL . 'modules/booking/pack.php?cs={CHECKOUT_SESSION_ID}',
                BASE_URL . 'modules/booking/pack-buy.php?package=' . $pkgId,
                ['type' => 'package', 'purchase_id' => (int) $r['id']]
            );
            if (isset($session['error'])) {
                $error = 'Le paiement n\'a pas pu démarrer. Merci de réessayer.';
            } else {
                $packageModel->attachStripeSession((int) $r['id'], $session['id']);
                header('Location: ' . $session['url']);
                exit;
            }
        } else {
            // Bypass : forfait actif tout de suite → email de confirmation
            // (avec le lien de l'espace pack) puis redirection vers cet espace.
            $created = $packageModel->getByToken($r['token']);
            if ($created) {
                Mailer::notifyPackagePurchase($created);
            }
            header('Location: pack.php?token=' . $r['token']);
            exit;
        }
    }
}

$pageTitle = 'Choisir un forfait';
$priceEur  = number_format((int) $pkg['price_cents'] / 100, 2, ',', ' ');
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
    <?= booking_header('index.php', 'Changer de forfait') ?>

    <main class="booking-main">
        <h1 class="page-title text-center"><?= Helpers::escape($pageTitle) ?></h1>

        <div class="bv3-recap">
            <h2 class="bv3-recap-title"><?= Icons::svg('clipboard-list', 20, 'icon-inline') ?>Votre forfait</h2>
            <dl class="bv3-recap-list">
                <dt>Forfait</dt>
                <dd><?= Helpers::escape($pkg['name']) ?></dd>
                <dt>Contenu</dt>
                <dd><?= Helpers::escape((string) (int) $pkg['sessions_count']) ?> séances de « <?= Helpers::escape($pkg['service_name']) ?> » (<?= Helpers::escape((string) (int) $pkg['duration_min']) ?> min)</dd>
                <dt>Validité</dt>
                <dd><?= Helpers::escape((string) (int) $pkg['validity_days']) ?> jours à partir de l'achat</dd>
                <dt>Tarif</dt>
                <dd><?= Helpers::escape($priceEur) ?> €</dd>
            </dl>
        </div>

        <?php if ($error): ?>
            <p class="bv3-empty" role="alert"><?= Helpers::escape($error) ?></p>
        <?php endif; ?>

        <form action="pack-buy.php" method="post" class="bv3-form" novalidate>
            <?= Helpers::csrfField() ?>
            <input type="hidden" name="package" value="<?= Helpers::escape((string) $pkgId) ?>">

            <div class="form-group">
                <label class="form-label" for="client_name">Nom complet <span class="required">*</span></label>
                <input type="text" class="form-input" id="client_name" name="client_name" required placeholder="Jean Dupont"
                       value="<?= Helpers::escape($_POST['client_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="client_email">Email <span class="required">*</span></label>
                <input type="email" class="form-input" id="client_email" name="client_email" required placeholder="jean.dupont@email.fr"
                       value="<?= Helpers::escape($_POST['client_email'] ?? '') ?>">
                <p class="text-muted bv3-help-note">Vous recevrez le lien de votre espace forfait sur cet email.</p>
            </div>

            <div class="rgpd-notice">
                <p>
                    En achetant ce forfait, vous demandez à être accompagné(e) par
                    <?= Helpers::escape(siteConfig()['site_name']) ?>. Vos données sont traitées sur la base de
                    l'exécution de mesures précontractuelles (art. 6.1.b RGPD) et conservées le temps de la
                    relation. Voir notre <a href="../../confidentialite.php" target="_blank" rel="noopener">Politique de confidentialité</a>.
                </p>
            </div>

            <div class="bv3-actions">
                <a href="index.php" class="btn btn-secondary">Retour</a>
                <button type="submit" class="btn btn-primary">
                    <?= \App\paymentMode($pkg) === 'stripe' ? 'Payer ' . Helpers::escape($priceEur) . ' €' : 'Valider mon forfait' ?>
                    <?= Icons::svg('arrow-right', 16, 'icon-inline') ?>
                </button>
            </div>
        </form>
    </main>

    <?= booking_footer() ?>

    <script src="../../assets/js/icons.js"></script>
    <?= pwaRegister() ?>
</body>
</html>
