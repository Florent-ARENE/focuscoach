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
        'admin_activity'  => '',
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
 * Wordmark de marque à partir du nom du site (paramétrable depuis /admin).
 * Le dernier mot est mis en accent. Ex : "Focus Coach" => Focus<span>Coach</span>
 * Usage : <span class="nav-brand-text"><?= brandWordmark() ?></span>
 */
function brandWordmark(): string
{
    $name = trim(siteConfig()['site_name'] ?? '');
    if ($name === '') {
        return 'Focus<span class="accent">Coach</span>';
    }
    $parts = preg_split('/\s+/', $name, 2);
    $first = \App\Helpers::escape($parts[0]);
    if (count($parts) > 1 && $parts[1] !== '') {
        return $first . '<span class="accent">' . \App\Helpers::escape($parts[1]) . '</span>';
    }
    return $first;
}
