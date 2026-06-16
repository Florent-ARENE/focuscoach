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

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
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
