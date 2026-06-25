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
    <?= diag_render_card($cfgState, 'Configuration requise', $cfgBody) ?>
    <?= diag_render_card($baseOk, 'Fuseaux & URL', $tzBody) ?>
</div>
<?php
echo diag_foot();
