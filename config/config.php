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
// BASE DE DONNEES MySQL + secrets
// ============================================
// Les identifiants vivent dans config.local.php (non versionne, cf. .gitignore,
// non ecrase par les mises a jour). Le fichier est REQUIS — pas de fallback
// clair pour ADMIN_PASSWORD (cf. config.local.php.template). Copier d'abord
// le template puis renseigner les valeurs reelles avant le premier run.
$localConfig = __DIR__ . '/config.local.php';
if (!file_exists($localConfig)) {
    die('Configuration manquante : copier config/config.local.php.template '
        . 'en config/config.local.php et renseigner les valeurs reelles '
        . '(DB_*, ADMIN_PASSWORD_HASH, Stripe...).');
}
require_once $localConfig;

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
// VALIDATION CONFIG — sanction au démarrage (garde-fou runtime, AD-8)
// ============================================
// La config requise (DB_*, BASE_URL, ADMIN_PASSWORD_HASH) vit dans
// config.local.php, propre à chaque serveur. On échoue TÔT et CLAIREMENT
// si elle manque ou est mal formée, plutôt que de servir des pages à
// demi configurées (ex. BASE_URL absente → liens/Stripe cassés).
// configProblems() = source unique (classes/Helpers.php), réutilisée par
// le module diagnostic. require_once explicite : l'autoloader n'est pas
// encore enregistré à ce stade (cf. includes/init.php).
require_once CLASSES_PATH . 'Helpers.php';
$cfgErr = \App\configProblems();
if ($cfgErr !== []) {
    http_response_code(500);
    exit('Configuration manquante/invalide : ' . implode(' ; ', $cfgErr)
        . '. Voir config/config.local.php.template (ex. BASE_URL=https://focuscoach.fr/).');
}

// ============================================
// URLs — BASE_URL STRICTE (jamais dérivée du Host)
// ============================================
// BASE_URL est définie EXCLUSIVEMENT par config.local.php (validée
// ci-dessus : présente + absolue). JAMAIS dérivée de $_SERVER['HTTP_HOST']
// (vecteur XSS Host-header, et absente en contexte webhook/cron). On en
// dérive seulement les URLs filles.
define('ASSETS_URL', BASE_URL . 'assets/');

// ============================================
// INFORMATIONS SITE
// ============================================
define('SITE_NAME', 'Focus Coach');
define('ADMIN_EMAIL', 'renaud@focuscoach.fr');
define('ADMIN_NAME', 'Renaud');

// ============================================
// PARAMETRES RESERVATION (Booking v3)
// ============================================
// BOOKING_STEP     : pas (minutes) du parcours des fenêtres
//                    d'ouverture → granularité d'offre des candidats.
// MIN_NOTICE_MIN   : délai minimum avant un créneau pour qu'il soit
//                    proposé (en minutes). Appliqué au jour J ; sur J+1
//                    et au-delà, toujours OK.
// MAX_HORIZON_DAYS : horizon de réservation (en jours, depuis
//                    aujourd'hui).
// Les constantes legacy SLOT_DURATION / BOOKING_ADVANCE_MIN_HOURS /
// BOOKING_ADVANCE_MAX_DAYS ont été supprimées avec la purge 2.6.0
// (tunnel v2 + api/slots.php + Slot.php legacy retirés).
define('TIMEZONE', 'Europe/Paris');
date_default_timezone_set(TIMEZONE);

define('BOOKING_STEP',     15);
define('MIN_NOTICE_MIN',   120);
define('MAX_HORIZON_DAYS', 60);

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
// STATUTS DE RESERVATION
// ============================================
// SERVICE_TYPES (ancien ENUM de l'offre conseil) a été supprimé
// avec la purge 2.6.0 — les prestations vivent désormais en BDD
// (table `services`, source de vérité, gérée via /admin).
//
// Le champ 'icon' (emoji) de chaque statut a été supprimé en même
// temps : il n'était plus consommé par aucune vue depuis la
// bascule SVG Lucide via `Icons::svg()` (cf. CLAUDE.md §10).
// Les codes couleur Google Calendar restent — ils sont propres
// à l'API GCal, non liés au design system du site.
//
// `pending_payment` et `expired` (nouveaux statuts v3) sont
// affichés via leur clé brute si absents de cette table — ils ne
// sont pas exposés à l'admin par défaut, seulement transitionnels.
define('BOOKING_STATUS', [
    'pending' => [
        'label' => 'En attente',
        'color' => '#f59e0b',
        'google_color' => '5',
    ],
    'confirmed' => [
        'label' => 'Confirme',
        'color' => '#10b981',
        'google_color' => '10',
    ],
    'cancelled' => [
        'label' => 'Annule',
        'color' => '#ef4444',
        'google_color' => '11',
    ],
    'completed' => [
        'label' => 'Termine',
        'color' => '#6b7280',
        'google_color' => '8',
    ],
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
