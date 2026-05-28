<?php
/**
 * TEST API SETTINGS
 * Accès : /api/test-settings.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

use App\Settings;
use App\Database;

header('Content-Type: application/json; charset=utf-8');

echo "<h2>Test Settings</h2>";

// Test connexion BDD
echo "<h3>1. Test connexion BDD</h3>";
try {
    $db = Database::getInstance();
    echo "<p style='color:green'>✅ Connexion BDD OK</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Erreur BDD: " . $e->getMessage() . "</p>";
    exit;
}

// Test lecture settings
echo "<h3>2. Test lecture settings</h3>";
try {
    $settings = new Settings();
    $all = $settings->getAll();
    echo "<pre>";
    print_r($all);
    echo "</pre>";
    echo "<p style='color:green'>✅ Lecture OK</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Erreur lecture: " . $e->getMessage() . "</p>";
}

// Test écriture settings
echo "<h3>3. Test écriture settings</h3>";
try {
    $testValue = 'test_' . time();
    $settings->set('test_key', $testValue);
    $readBack = $settings->get('test_key');
    
    if ($readBack === $testValue) {
        echo "<p style='color:green'>✅ Écriture OK (valeur: $testValue)</p>";
    } else {
        echo "<p style='color:red'>❌ Écriture échouée (attendu: $testValue, reçu: $readBack)</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Erreur écriture: " . $e->getMessage() . "</p>";
}

// Test Google Calendar ID
echo "<h3>4. Valeur google_calendar_id</h3>";
$calId = $settings->get('google_calendar_id', '(non défini)');
echo "<p><strong>ID:</strong> " . htmlspecialchars($calId) . "</p>";

$enabled = $settings->get('google_calendar_enabled', '0');
echo "<p><strong>Enabled:</strong> " . ($enabled === '1' ? '✅ Oui' : '❌ Non') . "</p>";

// Test fichier credentials
echo "<h3>5. Fichier google-credentials.json</h3>";
$credPath = CONFIG_PATH . 'google-credentials.json';
if (file_exists($credPath)) {
    echo "<p style='color:green'>✅ Fichier existe: $credPath</p>";
    $content = json_decode(file_get_contents($credPath), true);
    if ($content && isset($content['client_email'])) {
        echo "<p><strong>Service Account:</strong> " . htmlspecialchars($content['client_email']) . "</p>";
    }
} else {
    echo "<p style='color:orange'>⚠️ Fichier non trouvé: $credPath</p>";
}

echo "<hr><p>Test terminé</p>";
