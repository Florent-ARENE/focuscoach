<?php
/**
 * ============================================
 * DIAGNOSTIC — SANTÉ RUNTIME (AD-11)
 * ============================================
 * PHP/extensions, BDD joignable, tables présentes, config (configProblems),
 * fuseaux MySQL/PHP, BASE_URL. Lecture seule, secrets jamais affichés.
 * La connexion BDD est tentée en try/catch LOCAL (pas via
 * Database::getInstance() qui die()) pour rester non-fatal si la BDD tombe.
 */

require_once __DIR__ . '/_diagnostic.php';

use App\Helpers;

diag_require_admin();

$states = [];

// ── Runtime PHP ──
$phpOk = version_compare(PHP_VERSION, '7.4', '>=') ? 'ok' : 'fail';
$states[] = $phpOk;
$rtBody = diag_row($phpOk, 'Version PHP (≥ 7.4 requis)', PHP_VERSION);

$required = ['pdo_mysql', 'mbstring', 'json'];
$recommended = ['curl', 'openssl'];
foreach ($required as $ext) {
    $s = extension_loaded($ext) ? 'ok' : 'fail';
    $states[] = $s;
    $rtBody .= diag_row($s, "Extension $ext (requise)", extension_loaded($ext) ? 'chargée' : 'ABSENTE');
}
foreach ($recommended as $ext) {
    $s = extension_loaded($ext) ? 'ok' : 'warn';
    $states[] = $s;
    $rtBody .= diag_row($s, "Extension $ext (recommandée)", extension_loaded($ext) ? 'chargée' : 'absente');
}

// ── Base de données (connexion résiliente, helper partagé) ──
$conn = diag_try_pdo();
$pdo = $conn['pdo'];
$dbState = $pdo ? 'ok' : 'fail';
$dbDetail = $pdo
    ? 'Connexion établie (' . Helpers::escape(DB_NAME) . ')'
    : 'Inaccessible (' . Helpers::escape((string) $conn['error']) . ')';
$states[] = $dbState;
$dbBody = diag_row($dbState, 'Connexion MySQL', $dbDetail);

// Tables attendues (12).
$expectedTables = [
    'admin_login_attempts', 'availability', 'availability_exceptions', 'blocked_dates',
    'bookings', 'package_purchases', 'packages', 'purge_stats', 'rgpd_deletion_log',
    'services', 'settings', 'stripe_events_processed',
];
if ($pdo) {
    try {
        $present = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $missing = array_values(array_diff($expectedTables, $present));
        $tState = $missing === [] ? 'ok' : 'fail';
        $states[] = $tState;
        $dbBody .= diag_row(
            $tState,
            'Tables attendues (12)',
            $missing === [] ? count($present) . ' présentes' : 'Manquantes : ' . implode(', ', $missing)
        );
    } catch (\PDOException $e) {
        $states[] = 'fail';
        $dbBody .= diag_row('fail', 'Tables attendues (12)', 'Lecture impossible');
    }
} else {
    $states[] = 'warn';
    $dbBody .= diag_row('warn', 'Tables attendues (12)', 'Non vérifiable (BDD injoignable)');
}

// ── Configuration requise ──
$problems = \App\configProblems();
$cfgState = $problems === [] ? 'ok' : 'fail';
$states[] = $cfgState;
if ($problems === []) {
    $cfgBody = diag_row('ok', 'Configuration requise', 'Complète et bien formée');
} else {
    $cfgBody = '';
    foreach ($problems as $p) {
        $cfgBody .= diag_row('fail', 'Problème', $p);
    }
}
// DB_PASS vide n'est pas fatal, mais on le surface en warn hors-local/prod.
$pwChk = diag_dbpass_check();
$states[] = $pwChk['state'];
$cfgBody .= diag_row($pwChk['state'], 'DB_PASS (contexte)', $pwChk['detail']);

// ── Fuseaux & URL ──
$phpOffset = date('P');
$tzBody = diag_row('ok', 'Fuseau PHP', date_default_timezone_get() . ' (' . $phpOffset . ')');
if ($pdo) {
    try {
        $row = $pdo->query("SELECT TIMEDIFF(NOW(), UTC_TIMESTAMP()) AS d")->fetch();
        $mysqlOffset = substr((string) ($row['d'] ?? ''), 0, 6); // +HH:MM
        $aligned = ($mysqlOffset === $phpOffset) ? 'ok' : 'warn';
        $states[] = $aligned;
        $tzBody .= diag_row($aligned, 'Fuseau MySQL (aligné sur PHP)', $mysqlOffset ?: 'inconnu');
    } catch (\PDOException $e) {
        $states[] = 'warn';
        $tzBody .= diag_row('warn', 'Fuseau MySQL', 'Non vérifiable');
    }
}
$baseOk = (defined('BASE_URL') && preg_match('#^https?://#', (string) BASE_URL)) ? 'ok' : 'fail';
$states[] = $baseOk;
$tzBody .= diag_row($baseOk, 'BASE_URL définie + absolue', defined('BASE_URL') ? BASE_URL : '(absente)');

// ── Paiement Stripe (surface le mode dégradé / les price_id manquants) ──
// Jamais fatal (le tunnel bascule en bypass) → états 'warn', pas 'fail'.
$stripeOn = \App\stripeEnabled();
$whSecret = defined('STRIPE_WEBHOOK_SECRET') ? (string) STRIPE_WEBHOOK_SECRET : '';
$whOk     = ($whSecret !== '' && strpos($whSecret, 'REPLACE_WITH_') !== 0);

$stripeBody   = diag_row($stripeOn ? 'ok' : 'warn', 'Clés Stripe', $stripeOn ? 'configurées' : 'placeholder/absentes → mode dégradé (bypass, validation admin)');
$stripeBody  .= diag_row($whOk ? 'ok' : 'warn', 'Secret webhook', $whOk ? 'configuré' : 'placeholder/absent → webhook rejeté (400)');
$stripeStates = [$stripeOn ? 'ok' : 'warn', $whOk ? 'ok' : 'warn'];

if ($stripeOn && $pdo) {
    try {
        $missing = (int) $pdo->query(
            "SELECT COUNT(*) FROM services
              WHERE is_active = 1 AND price_cents > 0
                AND (stripe_price_id IS NULL OR stripe_price_id = '')"
        )->fetchColumn();
        $ms = $missing === 0 ? 'ok' : 'warn';
        $stripeStates[] = $ms;
        $stripeBody .= diag_row($ms, 'Services payants sans stripe_price_id', $missing === 0 ? 'aucun' : $missing . ' → tunnel refusé tant que non renseigné');
    } catch (\PDOException $e) {
        $stripeBody .= diag_row('warn', 'Services payants sans stripe_price_id', 'non vérifiable');
    }
}
$stripeState = diag_worst($stripeStates);
$states[] = $stripeState;

// ── Rendu ──
$overall = diag_worst($states);
$summaryLabel = ['ok' => 'Tout est vert.', 'warn' => 'Points d\'attention.', 'fail' => 'Au moins un blocage.'][$overall];

echo diag_head('Santé runtime');
?>
<div class="diag-summary diag-summary--<?= Helpers::escape($overall) ?>">
    <?= \App\Icons::svg(diag_state_icon($overall), 20) ?>
    <span><?= Helpers::escape($summaryLabel) ?></span>
</div>
<div class="diag-grid">
    <?= diag_render_card(diag_worst([$phpOk]), 'Runtime PHP', $rtBody) ?>
    <?= diag_render_card($dbState, 'Base de données', $dbBody) ?>
    <?= diag_render_card(diag_worst([$cfgState, $pwChk['state']]), 'Configuration requise', $cfgBody) ?>
    <?= diag_render_card($baseOk, 'Fuseaux & URL', $tzBody) ?>
    <?= diag_render_card($stripeState, 'Paiement Stripe', $stripeBody) ?>
</div>
<?php
echo diag_foot();
