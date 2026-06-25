<?php
/**
 * ============================================
 * INITIALISATION DE L'APPLICATION
 * ============================================
 * À inclure au début de chaque fichier PHP
 */

// Définir la constante de sécurité
define('BOOKING_APP', true);

// Charger la configuration
require_once __DIR__ . '/../config/config.php';

// Autoloader simple pour les classes
spl_autoload_register(function ($class) {
    // Retirer le namespace "App\"
    $class = str_replace('App\\', '', $class);
    
    $file = CLASSES_PATH . $class . '.php';
    
    if (file_exists($file)) {
        require_once $file;
    }
});

// Démarrer la session (cookies durcis : HttpOnly + SameSite + Secure conditionné HTTPS)
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Gestion des erreurs selon l'environnement
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Alias pour les classes fréquemment utilisées
use App\Database;
use App\Booking;
use App\Slot;
use App\Mailer;
use App\Helpers;
use App\GoogleCalendarSync;
use App\Settings;

/**
 * Charger la configuration du site (settings BDD + fallbacks constantes)
 * Usage : $cfg = siteConfig(); echo $cfg['admin_name'];
 * Résultat mis en cache (un seul appel BDD par requête)
 */
function siteConfig(): array
{
    static $config = null;
    
    if ($config !== null) {
        return $config;
    }
    
    // Fallbacks (constantes config.php)
    $defaults = [
        'site_name'     => defined('SITE_NAME') ? SITE_NAME : 'Mon Site',
        'admin_name'    => defined('ADMIN_NAME') ? ADMIN_NAME : '',
        'admin_email'   => defined('ADMIN_EMAIL') ? ADMIN_EMAIL : '',
        'admin_lastname'  => '',
        'admin_phone'     => '',
        'admin_address'   => '',
        'admin_siret'     => '',
        'legal_status'    => '',
    ];
    
    try {
        $settings = new Settings();
        $dbValues = $settings->getAll();
        $config = array_merge($defaults, array_filter($dbValues, fn($v) => $v !== '' && $v !== null));
    } catch (\Exception $e) {
        $config = $defaults;
    }
    
    // Champs dérivés (raccourcis pratiques)
    $config['full_name'] = trim($config['admin_name'] . ' ' . $config['admin_lastname']);
    $config['logo_name'] = $config['admin_name'] ?: 'Renaud';
    
    return $config;
}

/**
 * Afficher un champ de configuration ou un placeholder visuel si vide
 * Usage dans les pages légales : <?= cfgField(siteConfig()['admin_siret']) ?>
 */
function cfgField(string $value, string $placeholder = 'À compléter dans Paramètres'): string
{
    if (!empty($value)) {
        return \App\Helpers::escape($value);
    }
    return '<span class="cfg-missing">[' . \App\Helpers::escape($placeholder) . ']</span>';
}

/**
 * Métadonnées PWA à inclure dans le <head> de chaque page publique.
 * Centralise : manifest, theme-color, apple-touch-icon, viewport déjà
 * géré par les pages. Le service worker est enregistré via pwaRegister().
 * Usage : <?= pwaHead() ?>
 */
function pwaHead(): string
{
    return '<link rel="manifest" href="/manifest.json">'
         . '<meta name="theme-color" content="#2E2A5E">'
         . '<link rel="apple-touch-icon" href="/assets/img/icon-192.png">';
}

/**
 * Bloc <script> qui enregistre le service worker. À placer en fin
 * de <body> (avant </body>) pour ne pas bloquer le rendu.
 * Usage : <?= pwaRegister() ?>
 */
function pwaRegister(): string
{
    return '<script>'
         . "if('serviceWorker' in navigator){"
         . "window.addEventListener('load',function(){"
         . "navigator.serviceWorker.register('/sw.js').catch(function(e){console.warn('SW:',e);});"
         . "});}"
         . '</script>';
}

/**
 * Wordmark bicolore à partir de `site_name` (paramétrable depuis /admin).
 * Première moitié des caractères = .brand-half-a (orange par défaut),
 * seconde moitié = .brand-half-b (navy par défaut). Les couleurs sont
 * gérées par CSS — chaque contexte (fond clair/sombre) peut adapter via
 * un sélecteur descendant (ex: .sidebar .brand-half-b { color: var(--white); }).
 *
 * Algorithme : milieu = round(len/2) avec snap sur un espace à ±2 chars
 * pour éviter de couper un mot quand on peut couper proprement.
 * Usage : <span class="nav-brand-text"><?= brandWordmark() ?></span>
 */
function brandWordmark(): string
{
    $name = trim(siteConfig()['site_name'] ?? '');
    if ($name === '') {
        return '<span class="brand-half-a">Focus</span><span class="brand-half-b">Coach</span>';
    }

    $len = mb_strlen($name);
    if ($len <= 1) {
        return '<span class="brand-half-a">' . \App\Helpers::escape($name) . '</span>';
    }

    $mid = (int) round($len / 2);

    // Snap : si un espace existe à ±2 chars du milieu, on coupe là (plus propre).
    for ($delta = 0; $delta <= 2; $delta++) {
        foreach ([$mid - $delta, $mid + $delta] as $candidate) {
            if ($candidate > 0 && $candidate < $len
                && mb_substr($name, $candidate - 1, 1) === ' ') {
                $mid = $candidate;
                break 2;
            }
        }
    }

    $a = rtrim(mb_substr($name, 0, $mid));
    $b = ltrim(mb_substr($name, $mid));

    return '<span class="brand-half-a">' . \App\Helpers::escape($a) . '</span>'
         . '<span class="brand-half-b">' . \App\Helpers::escape($b) . '</span>';
}

/**
 * Footer du SITE — composant UNIQUE mutualisé (AD-3), fond navy.
 * Utilisé par l'accueil ET le tunnel booking (fini la duplication / le footer
 * minimal divergent). Contenu piloté par siteConfig() (AD-2), liens absolus
 * via BASE_URL. Styles : `.site-footer` dans main.css (chargé partout).
 *
 *   $full = true  → 3 colonnes (marque/légal · Navigation · Informations).
 *   $full = false → version ALLÉGÉE pour le funnel : marque + liens légaux + ©.
 *
 * Usage : <?= siteFooter() ?> (accueil) · <?= siteFooter(false) ?> (booking).
 */
function siteFooter(bool $full = true): string
{
    $cfg  = siteConfig();
    $esc  = static fn($v): string => \App\Helpers::escape((string) $v);
    $base = $esc(BASE_URL);
    $year = date('Y');

    $tagline = 'Architecte de liens · ' . trim((string) ($cfg['admin_activity'] ?? ''));
    if (!empty($cfg['admin_address'])) {
        $tagline .= '. ' . $cfg['admin_address'];
    }
    $tagline .= '.';

    // Colonne marque + légal — présente dans les deux variantes.
    $brand = '<div>'
        . '<div class="footer-brand">'
        . '<img src="' . $base . 'assets/img/logo_Focus_Coach.png" alt="' . $esc($cfg['site_name']) . '">'
        . '<p class="footer-brand-text">' . brandWordmark() . '</p></div>'
        . '<p class="footer-tagline">' . $esc($tagline) . '</p>'
        . '<p class="footer-legal">SIRET : <span class="value">' . cfgField($cfg['admin_siret']) . '</span></p>'
        . '<p class="footer-legal">' . cfgField($cfg['legal_status'], 'Statut juridique à compléter') . '</p>'
        . '</div>';

    $legalLinks = '<a href="' . $base . 'mentions-legales.php">Mentions légales</a>'
        . '<a href="' . $base . 'confidentialite.php">Confidentialité &amp; RGPD</a>';

    if ($full) {
        $nav = '<div><p class="footer-col-title">Navigation</p><div class="footer-links">'
            . '<a href="' . $base . '#services">Accompagnements</a>'
            . '<a href="' . $base . '#philosophie">Philosophie</a>'
            . '<a href="' . $base . '#about">Qui je suis</a>'
            . '<a href="' . $base . '#parcours">Parcours</a>'
            . '<a href="' . $base . 'modules/booking/">Prendre rendez-vous</a>'
            . '</div></div>';
        $infos = '<div><p class="footer-col-title">Informations</p><div class="footer-links">' . $legalLinks;
        if (!empty($cfg['admin_email'])) {
            $infos .= '<a href="mailto:' . $esc($cfg['admin_email']) . '">' . $esc($cfg['admin_email']) . '</a>';
        }
        if (!empty($cfg['admin_phone'])) {
            $infos .= '<a href="tel:' . $esc(preg_replace('/\s+/', '', (string) $cfg['admin_phone'])) . '">' . $esc($cfg['admin_phone']) . '</a>';
        }
        $infos .= '</div></div>';
        $top    = $brand . $nav . $infos;
        $bottom = '<p>© ' . $year . ' ' . brandWordmark() . ' · ' . $esc($cfg['full_name']) . ' · Tous droits réservés</p>'
                . '<p class="footer-note">Membre engagé dans les principes éthiques ICF &amp; EMCC</p>';
        $cls = 'site-footer';
    } else {
        $top    = $brand . '<div class="footer-links footer-links--inline">' . $legalLinks . '</div>';
        $bottom = '<p>© ' . $year . ' ' . brandWordmark() . ' · Tous droits réservés</p>';
        $cls    = 'site-footer site-footer--lite';
    }

    return '<footer class="' . $cls . '">'
        . '<div class="footer-top">' . $top . '</div>'
        . '<div class="footer-bottom">' . $bottom . '</div>'
        . '</footer>';
}
