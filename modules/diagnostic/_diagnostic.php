<?php
/**
 * ============================================
 * MODULE DIAGNOSTIC — helpers communs (AD-11)
 * ============================================
 * Outil interne, LECTURE SEULE, protégé par auth admin. Aucune action
 * destructive, aucun effet de bord en GET, secrets toujours masqués.
 *
 * Helpers mutualisés (zéro duplication entre les pages) :
 *   - diag_require_admin() : réenforce l'auth en tête de CHAQUE page.
 *   - diag_head()/diag_foot() : coquille HTML (tokens via diagnostic.css).
 *   - diag_render_card()/diag_row()/diag_badge() : rendu d'état ok/warn/fail.
 *   - diag_mask_secret() : statut d'un secret SANS jamais révéler la valeur.
 *
 * Tout passe par BASE_URL (jamais $_SERVER['HTTP_HOST']) — cf. §6.
 */

require_once __DIR__ . '/../../includes/init.php';

use App\Helpers;
use App\Icons;

/**
 * Réenforce l'authentification admin. Réutilise la session posée par
 * admin/index.php ($_SESSION['admin_logged_in']) — pas de second login.
 * Non authentifié → redirection vers le login admin (via BASE_URL strict).
 */
function diag_require_admin(): void
{
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: ' . BASE_URL . 'admin/');
        exit;
    }
}

/** Normalise un état libre en l'une des 3 classes connues. */
function diag_state(string $state): string
{
    return in_array($state, ['ok', 'warn', 'fail'], true) ? $state : 'warn';
}

/** Icône Lucide d'un état (toutes présentes dans les 2 miroirs). */
function diag_state_icon(string $state): string
{
    return ['ok' => 'check-circle', 'warn' => 'alert-triangle', 'fail' => 'x-circle'][diag_state($state)];
}

/** Pire état d'un lot (fail > warn > ok) — pour les synthèses. */
function diag_worst(array $states): string
{
    if (in_array('fail', $states, true)) return 'fail';
    if (in_array('warn', $states, true)) return 'warn';
    return 'ok';
}

/** Badge coloré (icône + libellé) d'un état. */
function diag_badge(string $state): string
{
    $state = diag_state($state);
    $labels = ['ok' => 'OK', 'warn' => 'Attention', 'fail' => 'Bloquant'];
    return '<span class="diag-badge diag-badge--' . $state . '">'
        . Icons::svg(diag_state_icon($state), 16)
        . '<span>' . Helpers::escape($labels[$state]) . '</span></span>';
}

/**
 * Ligne label / valeur avec pastille d'état. Tout est échappé (anti-XSS) :
 * les valeurs viennent de la BDD / config, jamais injectées brutes.
 */
function diag_row(string $state, string $label, string $value = ''): string
{
    $state = diag_state($state);
    return '<div class="diag-row diag-row--' . $state . '">'
        . '<span class="diag-row__icon">' . Icons::svg(diag_state_icon($state), 16) . '</span>'
        . '<span class="diag-row__label">' . Helpers::escape($label) . '</span>'
        . '<span class="diag-row__value">' . Helpers::escape($value) . '</span></div>';
}

/**
 * Carte d'état. $bodyHtml est du HTML DÉJÀ SAFE (construit via diag_row()
 * ou des helpers échappés) — jamais une entrée utilisateur brute.
 */
function diag_render_card(string $state, string $title, string $bodyHtml = ''): string
{
    $state = diag_state($state);
    return '<section class="diag-card diag-card--' . $state . '">'
        . '<header class="diag-card__head">' . diag_badge($state)
        . '<h2 class="diag-card__title">' . Helpers::escape($title) . '</h2></header>'
        . '<div class="diag-card__body">' . $bodyHtml . '</div></section>';
}

/**
 * Statut d'un secret SANS jamais révéler la valeur (même tronquée) :
 * uniquement « défini » (masqué) ou « absent ». Règle AD-11.
 */
function diag_mask_secret($value): string
{
    if ($value === null || $value === '') {
        return '— (absent)';
    }
    return '•••••••• (défini)';
}

/**
 * État de DB_PASS. Politique (cf. configProblems / revue 2.7.0) :
 *   - non défini            → fail ;
 *   - défini non vide       → ok ;
 *   - VIDE en LOCAL          → ok (root EasyPHP sans mot de passe, légitime) ;
 *   - VIDE hors-local/prod   → WARN (surface le risque sans fataliser :
 *     un déploiement prod avec mot de passe vide par accident doit se voir).
 * « Local » = hôte de BASE_URL ∈ {localhost, 127.0.0.1, ::1} ET APP_ENV ≠ production.
 *
 * @return array{state:string, detail:string}
 */
function diag_dbpass_check(): array
{
    if (!defined('DB_PASS')) {
        return ['state' => 'fail', 'detail' => 'non défini'];
    }
    if (DB_PASS !== '') {
        return ['state' => 'ok', 'detail' => 'défini'];
    }
    $host    = defined('BASE_URL') ? (string) parse_url((string) BASE_URL, PHP_URL_HOST) : '';
    $isLocal = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    $isProd  = defined('APP_ENV') && APP_ENV === 'production';
    if ($isProd || !$isLocal) {
        return ['state' => 'warn', 'detail' => 'VIDE en contexte non-local/prod (' . ($host ?: '?') . ') — à vérifier'];
    }
    return ['state' => 'ok', 'detail' => 'vide (toléré en local)'];
}

/** Coquille HTML d'ouverture. $showBack=false pour le hub. */
function diag_head(string $title, bool $showBack = true): string
{
    // ⚠ Pas de siteConfig() ici : il touche la BDD (Settings → Database::
    // getInstance() qui die() si la BDD tombe), ce qui rendrait health/
    // alignment non résilients. On lit la constante SITE_NAME (sans BDD).
    $base = Helpers::escape(BASE_URL);
    $site = Helpers::escape(defined('SITE_NAME') ? SITE_NAME : 'Focus Coach');
    $t    = Helpers::escape($title);

    $html = '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow">'
        . '<title>' . $t . ' · Diagnostic · ' . $site . '</title>'
        . '<link rel="stylesheet" href="' . $base . 'assets/css/main.css">'
        . '<link rel="stylesheet" href="' . $base . 'assets/css/diagnostic.css">'
        . '</head><body class="diag-body"><main class="diag-wrap"><div class="diag-top">';

    if ($showBack) {
        $html .= '<a class="diag-back" href="' . $base . 'modules/diagnostic/">'
            . Icons::svg('arrow-left', 18) . '<span>Hub diagnostic</span></a>';
    }
    return $html . '<h1 class="diag-h1">' . $t . '</h1></div>';
}

/** Coquille HTML de fermeture. */
function diag_foot(): string
{
    return '</main></body></html>';
}

/**
 * Connexion PDO RÉSILIENTE pour le diagnostic : try/catch local qui NE
 * die() PAS (contrairement à Database::getInstance()), pour que health /
 * alignment restent non-fatals si la BDD tombe. Aligne le fuseau comme
 * l'app. Retourne ['pdo' => ?PDO, 'error' => ?string (SQLSTATE, jamais de
 * secret)]. Lecture seule côté appelant.
 */
function diag_try_pdo(): array
{
    try {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $pdo->exec("SET time_zone = '" . date('P') . "'");
        return ['pdo' => $pdo, 'error' => null];
    } catch (\PDOException $e) {
        // Jamais le message brut (host/user) : SQLSTATE seul.
        return ['pdo' => null, 'error' => 'SQLSTATE ' . (string) $e->getCode()];
    }
}

/**
 * Version la plus haute (1ʳᵉ ligne de données) de la table « ## ChangeLog »
 * du changelog CANONIQUE (README_TECHNIQUE.md). Parsing PUR (testable hors
 * fichier) → sert le check d'alignement « VERSION ↔ haut du changelog ».
 *
 * @return ?string "X.Y.Z" ou null si aucune ligne de version trouvée.
 */
function diag_changelog_top_version(string $content): ?string
{
    return preg_match('/^\|\s*\d{2}\/\d{2}\/\d{4}\s*\|\s*([0-9]+\.[0-9]+\.[0-9]+)\s*\|/m', $content, $m)
        ? $m[1]
        : null;
}

/**
 * Statut d'un checkpoint dans le tableau « État d'avancement » de la spec
 * (ligne « | §N | module | statut | »). Parsing PUR → sert le check
 * « spec ↔ versions livrées ».
 *
 * @return ?string Le texte de la cellule statut (ex. "**livré** (v2.8.0)",
 *                 "à venir"), ou null si le checkpoint est absent du tableau.
 */
function diag_spec_status(string $content, string $checkpoint): ?string
{
    $re = '/^\|\s*' . preg_quote($checkpoint, '/') . '\s*\|[^|]*\|\s*([^|]+?)\s*\|/m';
    return preg_match($re, $content, $m) ? trim($m[1]) : null;
}
