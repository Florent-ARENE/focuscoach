<?php
/**
 * ============================================
 * CONFIGURATION PRINCIPALE
 * ============================================
 * Ce fichier peut etre ecrase par les mises a jour.
 * Les identifiants sensibles sont dans config.local.php
 */

// Empecher l'acces direct
if (!defined('BOOKING_APP')) {
    die('Acces direct interdit');
}

// ============================================
// BASE DE DONNEES MySQL
// ============================================
// Les identifiants sont dans config.local.php (non ecrase par les mises a jour)
$localConfig = __DIR__ . '/config.local.php';
if (file_exists($localConfig)) {
    require_once $localConfig;
} else {
    // Valeurs par defaut si config.local.php n'existe pas encore
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'virtualburenaud');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_CHARSET', 'utf8mb4');
    define('DB_PORT', 3306);
    
    // Mot de passe admin par defaut
    define('ADMIN_PASSWORD', 'renaud2026');
}

// ============================================
// ENVIRONNEMENT
// ============================================
define('APP_ENV', 'development'); // 'development' ou 'production'
define('APP_DEBUG', APP_ENV === 'development');
define('APP_VERSION', '1.0.0');

// ============================================
// CHEMINS
// ============================================
define('ROOT_PATH', dirname(__DIR__) . '/');
define('CONFIG_PATH', ROOT_PATH . 'config/');
define('CLASSES_PATH', ROOT_PATH . 'classes/');
define('INCLUDES_PATH', ROOT_PATH . 'includes/');
define('TEMPLATES_PATH', ROOT_PATH . 'templates/');
define('ASSETS_PATH', ROOT_PATH . 'assets/');

// ============================================
// URLs (detection automatique)
// ============================================
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Calculer le chemin de base - méthode simplifiée pour OVH
$scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
// Retirer le nom du fichier et les sous-dossiers connus (api/, admin/, booking/)
$basePath = dirname($scriptPath);
$basePath = preg_replace('#/(api|admin|booking)$#', '', $basePath);
$basePath = ($basePath === '/' || $basePath === '\\' || $basePath === '.') ? '' : $basePath;

define('BASE_URL', $protocol . '://' . $host . $basePath . '/');
define('ASSETS_URL', BASE_URL . 'assets/');

// ============================================
// INFORMATIONS SITE
// ============================================
define('SITE_NAME', 'Focus Coach');
define('ADMIN_EMAIL', 'renaud@focuscoach.fr');
define('ADMIN_NAME', 'Renaud');

// ============================================
// PARAMETRES RESERVATION
// ============================================
define('SLOT_DURATION', 30); // Duree en minutes
define('BOOKING_ADVANCE_MIN_HOURS', 24); // Minimum 24h a l'avance
define('BOOKING_ADVANCE_MAX_DAYS', 60); // Maximum 60 jours a l'avance
define('TIMEZONE', 'Europe/Paris');

date_default_timezone_set(TIMEZONE);

// ============================================
// GOOGLE CALENDAR (synchronisation miroir)
// ============================================
// Le site pousse vers Google Calendar (vue + alertes uniquement)
// Toute la gestion se fait depuis l'admin du site
define('GOOGLE_SYNC_ENABLED', false); // Passer a true pour activer
define('GOOGLE_CALENDAR_ID', ''); // ID du calendrier dedie
define('GOOGLE_CREDENTIALS_PATH', CONFIG_PATH . 'google-credentials.json');
define('GOOGLE_TOKEN_PATH', CONFIG_PATH . 'google-token.json');

// ============================================
// EMAIL
// ============================================
define('EMAIL_ENABLED', true);
define('EMAIL_FROM', ADMIN_EMAIL);
define('EMAIL_FROM_NAME', ADMIN_NAME);

// ============================================
// SECURITE
// ============================================
define('CSRF_TOKEN_NAME', 'booking_csrf_token');
define('SESSION_NAME', 'renaud_booking_session');

// ============================================
// TYPES DE SERVICES
// ============================================
define('SERVICE_TYPES', [
    'diagnostic' => 'Diagnostic organisationnel',
    'optimisation' => 'Optimisation des process',
    'changement' => 'Accompagnement au changement',
    'pilotage' => 'Pilotage de la performance',
    'cohesion' => 'Cohesion d\'equipe',
    'certification' => 'Certification & Labels',
    'autre' => 'Autre demande'
]);

// ============================================
// STATUTS DE RESERVATION
// ============================================
define('BOOKING_STATUS', [
    'pending' => [
        'label' => 'En attente',
        'color' => '#f59e0b',
        'icon' => '🟡',
        'google_color' => '5'
    ],
    'confirmed' => [
        'label' => 'Confirme',
        'color' => '#10b981',
        'icon' => '🟢',
        'google_color' => '10'
    ],
    'cancelled' => [
        'label' => 'Annule',
        'color' => '#ef4444',
        'icon' => '🔴',
        'google_color' => '11'
    ],
    'completed' => [
        'label' => 'Termine',
        'color' => '#6b7280',
        'icon' => '⚫',
        'google_color' => '8'
    ]
]);

// ============================================
// JOURS ET MOIS
// ============================================
define('DAYS_OF_WEEK', [
    1 => 'Lundi',
    2 => 'Mardi',
    3 => 'Mercredi',
    4 => 'Jeudi',
    5 => 'Vendredi',
    6 => 'Samedi',
    7 => 'Dimanche'
]);

define('MONTHS_FR', [
    1 => 'janvier', 2 => 'fevrier', 3 => 'mars', 4 => 'avril',
    5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'aout',
    9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'decembre'
]);
