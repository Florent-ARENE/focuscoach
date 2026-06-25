<?php
/**
 * ============================================
 * DIAGNOSTIC — SMOKE (AD-11)
 * ============================================
 * Affiche le corpus de non-régression (tests/smoke.php) dans le navigateur.
 * On EMBARQUE la page web du smoke (déjà lisible en <pre> depuis 2.6.6) via
 * un <iframe> : le navigateur la charge directement (pas de loopback
 * serveur→Apache, non fiable en imbriqué ici). Lecture seule : le smoke ne
 * touche pas la BDD.
 */

require_once __DIR__ . '/_diagnostic.php';

use App\Helpers;
use App\Icons;

diag_require_admin();

$smokeUrl = BASE_URL . 'tests/smoke.php';

echo diag_head('Smoke');
?>
<p class="diag-lead">
    Corpus de non-régression rejoué en direct (lecture seule, aucune écriture BDD).
    Source : <code>tests/smoke.php</code>.
</p>

<div class="diag-form">
    <a class="diag-btn" href="smoke.php"><?= Icons::svg('rotate-ccw', 16) ?><span>Relancer</span></a>
    <a class="diag-btn" href="<?= Helpers::escape($smokeUrl) ?>" target="_blank" rel="noopener">
        <?= Icons::svg('terminal', 16) ?><span>Ouvrir en plein écran</span>
    </a>
</div>

<iframe class="diag-frame" src="<?= Helpers::escape($smokeUrl) ?>" title="Corpus smoke" loading="lazy"></iframe>
<?php
echo diag_foot();
