<?php
/**
 * ============================================
 * MODULE DIAGNOSTIC — HUB (AD-11)
 * ============================================
 * Point d'entrée : auth admin, bandeau de synthèse, liens vers les pages
 * de test (santé, config, alignement, console API, smoke). Lecture seule.
 */

require_once __DIR__ . '/_diagnostic.php';

use App\Icons;
use App\Helpers;

diag_require_admin();

// Synthèse rapide : la config requise est-elle complète et bien formée ?
$cfgProblems = \App\configProblems();
$summaryState = $cfgProblems === [] ? 'ok' : 'fail';
$summaryText  = $cfgProblems === []
    ? 'Configuration requise complète. Ouvrir une page pour le détail.'
    : count($cfgProblems) . ' problème(s) de configuration — voir « Contrôle de config ».';

// Pages du module (icônes vérifiées présentes dans les 2 miroirs).
$pages = [
    ['health.php',      'activity',       'Santé runtime',     'PHP, extensions, BDD, tables, config, fuseaux, BASE_URL.'],
    ['config-check.php','settings',       'Contrôle de config','Les constantes par catégorie, secrets masqués.'],
    ['alignment.php',   'scale',          'Alignement',        'Version ↔ docs, statuts, catalogue seed ↔ migration.'],
    ['api-console.php', 'terminal',       'Console API',       'Interroge les endpoints JSON en lecture seule.'],
    ['smoke.php',       'check-circle',   'Smoke',             'Rejoue le corpus de non-régression, lisible ici.'],
];

echo diag_head('Diagnostic', false);
?>
<p class="diag-lead">Outil interne en lecture seule. Aucune action ne modifie de données.</p>

<div class="diag-summary diag-summary--<?= Helpers::escape($summaryState) ?>">
    <?= Icons::svg(diag_state_icon($summaryState), 20) ?>
    <span><?= Helpers::escape($summaryText) ?></span>
</div>

<ul class="diag-links">
    <?php foreach ($pages as [$href, $icon, $title, $desc]): ?>
        <li>
            <a class="diag-link" href="<?= Helpers::escape($href) ?>">
                <span class="diag-link__icon"><?= Icons::svg($icon, 22) ?></span>
                <span class="diag-link__body">
                    <span class="diag-link__title"><?= Helpers::escape($title) ?></span>
                    <span class="diag-link__desc"><?= Helpers::escape($desc) ?></span>
                </span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
<?php
echo diag_foot();
