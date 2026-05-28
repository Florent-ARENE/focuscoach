<?php
/**
 * TEST API ADMIN - Debug erreur 500
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Debug API Admin</h2>";

// Étape 1 : Init
echo "<h3>1. Chargement init.php</h3>";
try {
    require_once __DIR__ . '/../includes/init.php';
    echo "<p style='color:green'>✅ init.php OK</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>❌ Erreur init: " . $e->getMessage() . "</p>";
    exit;
}

// Étape 2 : Use statements
echo "<h3>2. Chargement des classes</h3>";

try {
    $booking = new \App\Booking();
    echo "<p style='color:green'>✅ Booking OK</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>❌ Erreur Booking: " . $e->getMessage() . "</p>";
}

try {
    $slot = new \App\Slot();
    echo "<p style='color:green'>✅ Slot OK</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>❌ Erreur Slot: " . $e->getMessage() . "</p>";
}

try {
    $settings = new \App\Settings();
    echo "<p style='color:green'>✅ Settings OK</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>❌ Erreur Settings: " . $e->getMessage() . "</p>";
}

try {
    $mailer = new \App\Mailer();
    echo "<p style='color:green'>✅ Mailer OK</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>❌ Erreur Mailer: " . $e->getMessage() . "</p>";
}

try {
    $helpers = new \App\Helpers();
    echo "<p style='color:green'>✅ Helpers OK</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>❌ Erreur Helpers: " . $e->getMessage() . "</p>";
}

try {
    $gcal = new \App\GoogleCalendarSync();
    echo "<p style='color:green'>✅ GoogleCalendarSync OK</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>❌ Erreur GoogleCalendarSync: " . $e->getMessage() . "<br>";
    echo "Fichier: " . $e->getFile() . " ligne " . $e->getLine() . "</p>";
}

// Étape 3 : Test setMultiple
echo "<h3>3. Test Settings::setMultiple</h3>";
try {
    $testData = [
        'google_calendar_enabled' => '1',
        'google_calendar_id' => 'test@group.calendar.google.com'
    ];
    $result = $settings->setMultiple($testData);
    echo "<p style='color:green'>✅ setMultiple OK (résultat: " . ($result ? 'true' : 'false') . ")</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>❌ Erreur setMultiple: " . $e->getMessage() . "</p>";
}

// Étape 4 : Test Helpers::jsonResponse
echo "<h3>4. Test Helpers::getJsonInput</h3>";
try {
    $input = \App\Helpers::getJsonInput();
    echo "<p style='color:green'>✅ getJsonInput OK</p>";
    echo "<pre>" . print_r($input, true) . "</pre>";
} catch (Throwable $e) {
    echo "<p style='color:red'>❌ Erreur getJsonInput: " . $e->getMessage() . "</p>";
}

echo "<hr><p>Test terminé</p>";
