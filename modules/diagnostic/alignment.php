<?php
/**
 * ============================================
 * DIAGNOSTIC — ALIGNEMENT (AD-11)
 * ============================================
 * Vue HTTP des cohérences mono-source (AD-2) :
 *   (a) version : VERSION ↔ stamps README/CLAUDE/README_TECHNIQUE/sw.js ;
 *   (b) statuts : enum bookings.status ⇄ CASE active_key ⇄ BOOKING_STATUS ;
 *   (c) catalogue : services de seed.sql ⇄ migration-3.0.0.sql.
 * Lecture seule (fichiers + information_schema).
 */

require_once __DIR__ . '/_diagnostic.php';

use App\Helpers;

diag_require_admin();

$states = [];

// ── (a) Alignement de version ──
$version = trim((string) @file_get_contents(ROOT_PATH . 'VERSION'));
$stamps = [
    'README.md'           => ['#\*\*Version:\*\*\s+([0-9.]+)#', ROOT_PATH . 'README.md'],
    'CLAUDE.md'           => ['#État courant\s+—\s+v([0-9.]+)#u', ROOT_PATH . 'CLAUDE.md'],
    'README_TECHNIQUE.md' => ['#\*\*Version:\*\*\s+([0-9.]+)#', ROOT_PATH . 'README_TECHNIQUE.md'],
    'sw.js'               => ['#CACHE_VERSION\s*=\s*[\'"]v([0-9.]+)[\'"]#', ROOT_PATH . 'sw.js'],
];
$verBody = diag_row($version !== '' ? 'ok' : 'fail', 'VERSION (source unique)', $version ?: '(illisible)');
foreach ($stamps as $name => [$re, $path]) {
    $content = (string) @file_get_contents($path);
    $found = preg_match($re, $content, $m) ? $m[1] : '(absent)';
    $s = ($found === $version && $version !== '') ? 'ok' : 'fail';
    $states[] = $s;
    $verBody .= diag_row($s, $name, $found === $version ? $found : "$found ≠ $version");
}

// ── (b) Cohérence des statuts ──
$conn = diag_try_pdo();
$pdo = $conn['pdo'];
$statusBody = '';
if ($pdo) {
    try {
        $enumType = (string) $pdo->query(
            "SELECT COLUMN_TYPE FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'bookings' AND column_name = 'status'"
        )->fetchColumn();
        preg_match_all("/'([a-z_]+)'/", $enumType, $em);
        $enum = $em[1];

        $genExpr = (string) $pdo->query(
            "SELECT GENERATION_EXPRESSION FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'bookings' AND column_name = 'active_key'"
        )->fetchColumn();
        preg_match_all("/'([a-z_]+)'/", $genExpr, $gm);
        // Le séparateur '_' est aussi capturé → on ne garde que ce qui est un statut connu.
        $activeStatuses = array_values(array_intersect($gm[1], $enum));

        $bookingStatusKeys = defined('BOOKING_STATUS') ? array_keys(BOOKING_STATUS) : [];

        $statusBody .= diag_row('ok', 'enum bookings.status', implode(', ', $enum));

        $activeOk = array_diff($activeStatuses, $enum) === [] ? 'ok' : 'fail';
        $states[] = $activeOk;
        $statusBody .= diag_row($activeOk, 'CASE active_key ⊆ enum', implode(', ', $activeStatuses));

        $cfgMissing = array_diff($bookingStatusKeys, $enum);
        $cfgOk = $cfgMissing === [] ? 'ok' : 'fail';
        $states[] = $cfgOk;
        $statusBody .= diag_row(
            $cfgOk,
            'BOOKING_STATUS ⊆ enum',
            $cfgMissing === [] ? implode(', ', $bookingStatusKeys) : 'hors enum : ' . implode(', ', $cfgMissing)
        );
    } catch (\PDOException $e) {
        $states[] = 'warn';
        $statusBody = diag_row('warn', 'Statuts', 'Lecture information_schema impossible');
    }
} else {
    $states[] = 'warn';
    $statusBody = diag_row('warn', 'Statuts', 'Non vérifiable (BDD injoignable)');
}

// ── (c) Catalogue seed ⇄ migration ──
$slugRe = "/\\(\\s*'([a-z0-9-]+)',\\s*'(?:sportif|dirigeant|particulier)'/";
$seedTxt = (string) @file_get_contents(ROOT_PATH . 'sql/seed.sql');
$migTxt  = (string) @file_get_contents(ROOT_PATH . 'sql/migration-3.0.0.sql');
preg_match_all($slugRe, $seedTxt, $sm);
preg_match_all($slugRe, $migTxt, $mm);
$seedSlugs = array_values(array_unique($sm[1]));
$migSlugs  = array_values(array_unique($mm[1]));
sort($seedSlugs);
sort($migSlugs);
$catOk = ($seedSlugs === $migSlugs && $seedSlugs !== []) ? 'ok' : 'warn';
$states[] = $catOk;
$catBody  = diag_row('ok', 'services dans seed.sql', (string) count($seedSlugs));
$catBody .= diag_row('ok', 'services dans migration-3.0.0.sql', (string) count($migSlugs));
$diffA = array_diff($seedSlugs, $migSlugs);
$diffB = array_diff($migSlugs, $seedSlugs);
$catBody .= diag_row(
    $catOk,
    'Slugs identiques',
    ($diffA === [] && $diffB === []) ? 'oui (' . count($seedSlugs) . ')'
        : 'écart : ' . implode(', ', array_merge($diffA, $diffB))
);

$overall = diag_worst($states);
echo diag_head('Alignement');
?>
<div class="diag-summary diag-summary--<?= Helpers::escape($overall) ?>">
    <?= \App\Icons::svg(diag_state_icon($overall), 20) ?>
    <span><?= Helpers::escape(['ok' => 'Toutes les sources sont alignées.', 'warn' => 'Vérifications partielles.', 'fail' => 'Dérive détectée.'][$overall]) ?></span>
</div>
<div class="diag-grid">
    <?= diag_render_card(diag_worst(array_slice($states, 0, 4)), 'Version ↔ docs', $verBody) ?>
    <?= diag_render_card('ok', 'Statuts (enum ⇄ active_key ⇄ BOOKING_STATUS)', $statusBody) ?>
    <?= diag_render_card($catOk, 'Catalogue seed ⇄ migration', $catBody) ?>
</div>
<?php
echo diag_foot();
