<?php
/**
 * Test d'envoi email admin
 * Accès : /api/test-email.php
 */

define('BOOKING_APP', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/init.php';

echo "<h1>Test Email Admin</h1>";

// Récupérer l'email admin effectif (comme le fait Mailer)
$settings = new \App\Settings();
$dbAdminEmail = $settings->get('admin_email');
$effectiveAdminEmail = !empty($dbAdminEmail) ? $dbAdminEmail : ADMIN_EMAIL;

// Infos de configuration
echo "<h2>Configuration</h2>";
echo "<pre>";
echo "EMAIL_ENABLED: " . (EMAIL_ENABLED ? 'true' : 'false') . "\n";
echo "ADMIN_EMAIL (constante): " . ADMIN_EMAIL . "\n";
echo "admin_email (BDD): " . ($dbAdminEmail ?: 'Non défini') . "\n";
echo "<strong>Email effectif utilisé: $effectiveAdminEmail</strong>\n";
echo "EMAIL_FROM: " . EMAIL_FROM . "\n";
echo "EMAIL_FROM_NAME: " . EMAIL_FROM_NAME . "\n";
echo "</pre>";

if (!empty($dbAdminEmail) && $dbAdminEmail !== ADMIN_EMAIL) {
    echo "<p style='color: green;'>✅ L'email en BDD ($dbAdminEmail) sera utilisé pour les notifications admin.</p>";
}

// Test d'envoi
if (isset($_GET['send'])) {
    echo "<h2>Envoi de test</h2>";
    
    $to = $effectiveAdminEmail;
    $subject = "Test email admin - " . date('Y-m-d H:i:s');
    $message = "Ceci est un test d'envoi email vers l'admin.\n\n";
    $message .= "Si vous recevez ce message, les emails fonctionnent correctement.\n";
    $message .= "Date: " . date('Y-m-d H:i:s');
    
    $headers = [
        'From' => EMAIL_FROM_NAME . ' <' . EMAIL_FROM . '>',
        'Reply-To' => EMAIL_FROM,
        'X-Mailer' => 'PHP/' . phpversion(),
        'Content-Type' => 'text/plain; charset=UTF-8',
        'MIME-Version' => '1.0'
    ];
    
    $headerString = '';
    foreach ($headers as $key => $value) {
        $headerString .= "$key: $value\r\n";
    }
    
    echo "<p>Envoi vers: <strong>$to</strong></p>";
    echo "<p>Sujet: $subject</p>";
    
    $result = mail($to, $subject, $message, $headerString);
    
    if ($result) {
        echo "<p style='color: green; font-weight: bold;'>✅ mail() a retourné TRUE</p>";
        echo "<p>Vérifiez votre boîte de réception (et les spams).</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>❌ mail() a retourné FALSE</p>";
        echo "<p>Le serveur n'a pas pu envoyer l'email.</p>";
        
        // Vérifier les erreurs PHP
        $error = error_get_last();
        if ($error) {
            echo "<p>Dernière erreur PHP: " . htmlspecialchars($error['message']) . "</p>";
        }
    }
} else {
    echo "<h2>Test</h2>";
    echo "<p><a href='?send=1' style='padding: 10px 20px; background: #c9a227; color: #1a2744; text-decoration: none; border-radius: 5px;'>Envoyer un email de test à $effectiveAdminEmail</a></p>";
}
