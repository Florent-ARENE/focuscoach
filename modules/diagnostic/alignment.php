<?php
/**
 * ============================================
 * DIAGNOSTIC — ALIGNEMENT (AD-11)
 * ============================================
 * Vue HTTP des cohérences mono-source (AD-2) :
 *   (a) version : VERSION ↔ stamps README/CLAUDE/README_TECHNIQUE/sw.js ;
 *   (b) statuts : enum bookings.status ⇄ CASE active_key ⇄ BOOKING_STATUS ;
 *   (c) catalogue : services de seed.sql ⇄ migration-3.0.0.sql ;
 *   (d) docs & cadrage (AD-2/AD-7, ajout 2.8.5 — anti-dérive de la doc) :
 *       VERSION ↔ haut du changelog canonique ; CHANGELOG.md mono-source
 *       (redirection) ; spec ↔ versions livrées ; exception inter-règles
 *       AD-4↔AD-11 (diagnostic.css) tracée.
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

// Forfaits (§7) : chaque forfait actif doit référencer une prestation ACTIVE.
if ($pdo) {
    try {
        $pkgActive = (int) $pdo->query("SELECT COUNT(*) FROM packages WHERE is_active = 1")->fetchColumn();
        $pkgOrphan = (int) $pdo->query(
            "SELECT COUNT(*) FROM packages p
              LEFT JOIN services s ON s.id = p.service_id AND s.is_active = 1
              WHERE p.is_active = 1 AND s.id IS NULL"
        )->fetchColumn();
        $pkgOk = $pkgOrphan === 0 ? 'ok' : 'fail';
        $states[] = $pkgOk;
        $catBody .= diag_row($pkgOk, 'Forfaits actifs → prestation active',
            $pkgOrphan === 0 ? $pkgActive . ' forfait(s), 0 orphelin' : $pkgOrphan . ' sans prestation active');
    } catch (\PDOException $e) {
        $states[] = 'warn';
        $catBody .= diag_row('warn', 'Forfaits', 'Non vérifiable');
    }
}

// ── (d) Documentation & cadrage (anti-dérive doc — AD-2 / AD-7) ──
// d1 — VERSION ↔ haut du changelog CANONIQUE (README_TECHNIQUE §ChangeLog).
$rtTxt = (string) @file_get_contents(ROOT_PATH . 'README_TECHNIQUE.md');
$clTop = diag_changelog_top_version($rtTxt);
$d1 = ($clTop === $version && $version !== '') ? 'ok' : 'fail';
$docBody = diag_row($d1, 'VERSION ↔ haut du ChangeLog (canonique)',
    $clTop === $version ? (string) $clTop : ($clTop ?? '(absent)') . ' ≠ ' . $version);

// d2 — Mono-source : CHANGELOG.md racine gelé + redirigé (pas de 2ᵉ journal vivant).
$clMd = (string) @file_get_contents(ROOT_PATH . 'CHANGELOG.md');
$d2 = (stripos($clMd, 'SOURCE UNIQUE DU CHANGELOG') !== false) ? 'ok' : 'fail';
$docBody .= diag_row($d2, 'CHANGELOG.md = redirection (mono-source AD-2)',
    $d2 === 'ok' ? 'gelé + redirige vers README_TECHNIQUE' : 'bandeau de redirection ABSENT (deux journaux ?)');

// d3 — spec ↔ versions livrées : un checkpoint dont le code existe doit être « livré ».
$specTxt = (string) @file_get_contents(ROOT_PATH . 'docs/booking-v3-spec.md');
$proxies = [
    '§6' => 'classes/StripeClient.php',
    '§9' => 'modules/diagnostic/health.php',
    '§7' => 'modules/booking/pack.php',
];
$specDrift = [];
foreach ($proxies as $cp => $proxyFile) {
    $status = (string) diag_spec_status($specTxt, $cp);
    if (file_exists(ROOT_PATH . $proxyFile) && stripos($status, 'livré') === false) {
        $specDrift[] = $cp . ' livré (code présent) mais marqué « ' . $status . ' »';
    }
}
$d3 = $specDrift === [] ? 'ok' : 'fail';
$docBody .= diag_row($d3, 'spec ↔ versions livrées',
    $specDrift === [] ? 'tableau d\'avancement cohérent avec le code' : implode(' ; ', $specDrift));

// d4 — Exception inter-règles tracée (AD-4 ↔ AD-11 : diagnostic.css séparé, voulu).
$claudeTxt = (string) @file_get_contents(ROOT_PATH . 'CLAUDE.md');
$d4 = (stripos($claudeTxt, 'diagnostic.css') !== false
       && stripos($claudeTxt, 'ne doit JAMAIS redéfinir') !== false) ? 'ok' : 'fail';
$docBody .= diag_row($d4, 'Exception cadrage tracée (AD-4↔AD-11 : diagnostic.css)',
    $d4 === 'ok' ? 'documentée (CLAUDE.md §2.3)' : 'NON tracée → contradiction silencieuse');

// d5 — Footer mutualisé : aucune page publique ne déclare un <footer> EN DUR
// (le markup vit dans siteFooter()/booking_footer()). Garde contre l'oubli
// d'une page — cf. dérive .legal-footer repérée à l'œil en 2.8.8.
$pageFiles = [
    'index.php', 'mentions-legales.php', 'confidentialite.php',
    'modules/booking/index.php', 'modules/booking/date.php', 'modules/booking/slot.php',
    'modules/booking/confirm.php', 'modules/booking/success.php', 'modules/booking/manage.php',
];
$inlineFooter = [];
foreach ($pageFiles as $pf) {
    $c = (string) @file_get_contents(ROOT_PATH . $pf);
    if ($c !== '' && diag_has_inline_footer($c)) { $inlineFooter[] = $pf; }
}
$d5 = $inlineFooter === [] ? 'ok' : 'fail';
$docBody .= diag_row($d5, 'Footer mutualisé sur toutes les pages (AD-3)',
    $inlineFooter === [] ? count($pageFiles) . ' pages via siteFooter()/booking_footer()' : 'footer EN DUR : ' . implode(', ', $inlineFooter));

$docCard = diag_worst([$d1, $d2, $d3, $d4, $d5]);
array_push($states, $d1, $d2, $d3, $d4, $d5);

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
    <?= diag_render_card($docCard, 'Documentation, cadrage & pages (AD-2/3/7)', $docBody) ?>
</div>
<?php
echo diag_foot();
