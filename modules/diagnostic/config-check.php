<?php
/**
 * ============================================
 * DIAGNOSTIC — CONTRÔLE DE CONFIG (AD-11)
 * ============================================
 * Les constantes par catégorie de sévérité (cf. configProblems) :
 *   - REQUIS (fatal), OPTIONNEL-DÉFAUT, OPTIONNEL-PILOTÉ (jamais fatal).
 * Les secrets ne sont JAMAIS affichés (même tronqués) : statut seulement.
 */

require_once __DIR__ . '/_diagnostic.php';

use App\Helpers;

diag_require_admin();

/** Valeur affichable d'une constante non secrète (ou statut « non défini »). */
function cc_value(string $key): string
{
    return defined($key) ? (string) constant($key) : '(non défini)';
}

$problems = \App\configProblems();
$overall  = $problems === [] ? 'ok' : 'fail';

// ── Requis (fatal si manquant/mal formé) ──
// secret=true → on ne montre que le statut masqué.
$requiredDefs = [
    ['DB_HOST', false], ['DB_NAME', false], ['DB_USER', false],
    ['DB_PASS', true], ['BASE_URL', false], ['ADMIN_PASSWORD_HASH', true],
];
$reqBody = '';
foreach ($requiredDefs as [$key, $secret]) {
    $defined = defined($key);
    if ($key === 'DB_PASS') {
        // Vide toléré en local, mais surfacé en WARN hors-local/prod
        // (sans fataliser : configProblems reste vert). On affiche le
        // statut de contexte, jamais la valeur.
        $chk   = diag_dbpass_check();
        $state = $chk['state'];
        $value = $chk['detail'];
    } else {
        $state = ($defined && (string) constant($key) !== '') ? 'ok' : 'fail';
        $value = $secret ? diag_mask_secret($defined ? constant($key) : null) : cc_value($key);
    }
    $reqBody .= diag_row($state, $key, $value);
}

// ── Optionnel-défaut (valeur par défaut si absent) ──
$defaultDefs = [['DB_PORT', '3306'], ['DB_CHARSET', 'utf8mb4']];
$defBody = '';
foreach ($defaultDefs as [$key, $default]) {
    $defined = defined($key) && (string) constant($key) !== '';
    $state = $defined ? 'ok' : 'warn';
    $defBody .= diag_row($state, $key, $defined ? cc_value($key) : "absent → défaut $default");
}

// ── Optionnel-piloté (JAMAIS fatal : mode dégradé) ──
$optionalDefs = [
    ['STRIPE_SECRET_KEY', true], ['STRIPE_PUBLISHABLE_KEY', true],
    ['STRIPE_WEBHOOK_SECRET', true], ['GOOGLE_CREDENTIALS_PATH', false],
];
$optBody = '';
foreach ($optionalDefs as [$key, $secret]) {
    $present = defined($key) && (string) constant($key) !== ''
        && strpos((string) constant($key), 'REPLACE_WITH_') !== 0;
    $state = $present ? 'ok' : 'warn'; // jamais 'fail'
    if ($secret) {
        $value = $present ? diag_mask_secret(constant($key)) : '— (non configuré, mode dégradé)';
    } else {
        $value = $present ? cc_value($key) : '— (non configuré, mode dégradé)';
    }
    $optBody .= diag_row($state, $key, $value);
}

echo diag_head('Contrôle de config');
?>
<div class="diag-summary diag-summary--<?= Helpers::escape($overall) ?>">
    <?= \App\Icons::svg(diag_state_icon($overall), 20) ?>
    <span><?= $overall === 'ok'
        ? 'Configuration requise complète.'
        : Helpers::escape(count($problems) . ' problème(s) requis — voir ci-dessous.') ?></span>
</div>
<p class="diag-lead">Les secrets (mots de passe, clés, tokens) ne sont jamais affichés : statut « défini / absent » uniquement.</p>
<div class="diag-grid">
    <?= diag_render_card($overall, 'Requis (fatal si manquant)', $reqBody) ?>
    <?= diag_render_card('ok', 'Optionnel — valeur par défaut', $defBody) ?>
    <?= diag_render_card('ok', 'Optionnel — piloté (jamais fatal)', $optBody) ?>
</div>
<?php
echo diag_foot();
