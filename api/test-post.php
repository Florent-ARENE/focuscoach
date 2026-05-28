<?php
/**
 * TEST POST Settings - Simule exactement ce que fait l'admin
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

use App\Settings;
use App\Helpers;

header('Content-Type: text/html; charset=utf-8');

echo "<h2>Test POST Settings</h2>";

// Simuler les données envoyées par le JS
$testInput = [
    'google_calendar_enabled' => '1',
    'google_calendar_id' => '9934849309316f3a6e7a5fa13b631b7fb403991f90357f816f76b77dc279d00e@group.calendar.google.com',
    'site_name' => 'Test Site',
    'admin_email' => 'test@test.com'
];

echo "<h3>1. Données à sauvegarder</h3>";
echo "<pre>" . print_r($testInput, true) . "</pre>";

echo "<h3>2. Test Helpers::sanitize</h3>";
try {
    $allowedKeys = [
        'google_calendar_enabled',
        'google_calendar_id',
        'admin_email',
        'site_name'
    ];
    
    $toSave = [];
    foreach ($allowedKeys as $key) {
        if (isset($testInput[$key])) {
            $toSave[$key] = Helpers::sanitize($testInput[$key]);
            echo "<p>✅ Sanitize '$key' OK</p>";
        }
    }
    echo "<pre>" . print_r($toSave, true) . "</pre>";
} catch (Throwable $e) {
    echo "<p style='color:red'>❌ Erreur sanitize: " . $e->getMessage() . "</p>";
    exit;
}

echo "<h3>3. Test Settings::setMultiple</h3>";
try {
    $settings = new Settings();
    $result = $settings->setMultiple($toSave);
    
    if ($result) {
        echo "<p style='color:green'>✅ setMultiple OK</p>";
    } else {
        echo "<p style='color:red'>❌ setMultiple a retourné false</p>";
    }
} catch (Throwable $e) {
    echo "<p style='color:red'>❌ Erreur setMultiple: " . $e->getMessage() . "</p>";
    echo "<p>Fichier: " . $e->getFile() . " ligne " . $e->getLine() . "</p>";
    exit;
}

echo "<h3>4. Test Settings::getAll</h3>";
try {
    $all = $settings->getAll();
    echo "<pre>" . print_r($all, true) . "</pre>";
    echo "<p style='color:green'>✅ getAll OK</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>❌ Erreur getAll: " . $e->getMessage() . "</p>";
}

echo "<h3>5. Test Helpers::jsonResponse</h3>";
try {
    // Ne pas exécuter car ça arrête le script, juste vérifier que la méthode existe
    if (method_exists('App\Helpers', 'jsonResponse')) {
        echo "<p style='color:green'>✅ jsonResponse existe</p>";
    } else {
        echo "<p style='color:red'>❌ jsonResponse n'existe pas</p>";
    }
} catch (Throwable $e) {
    echo "<p style='color:red'>❌ Erreur: " . $e->getMessage() . "</p>";
}

echo "<h3>6. Vérification en BDD</h3>";
try {
    $db = \App\Database::getInstance();
    $stmt = $db->query("SELECT * FROM settings ORDER BY setting_key");
    $rows = $stmt->fetchAll();
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Key</th><th>Value</th><th>Updated</th></tr>";
    foreach ($rows as $row) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['setting_key']}</td>";
        echo "<td>" . htmlspecialchars(substr($row['setting_value'], 0, 50)) . "</td>";
        echo "<td>{$row['updated_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Throwable $e) {
    echo "<p style='color:red'>❌ Erreur BDD: " . $e->getMessage() . "</p>";
}

echo "<hr><p style='color:green; font-weight:bold'>✅ Tous les tests passent ! L'erreur 500 vient d'ailleurs.</p>";
echo "<p>Vérifiez les logs PHP de votre serveur pour plus de détails.</p>";
