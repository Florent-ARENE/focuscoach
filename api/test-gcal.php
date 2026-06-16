<?php
/**
 * ============================================
 * TEST GOOGLE CALENDAR SYNC
 * ============================================
 * Diagnostic complet de la synchronisation Google Calendar
 * Accès : /api/test-gcal.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

use App\GoogleCalendarSync;
use App\Settings;

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Test Google Calendar Sync</title>
    <link rel="stylesheet" href="../assets/css/main.css">
</head>
<body class="diag">
    <h2>🔍 Diagnostic Google Calendar Sync</h2>
    
    <?php
    // ============================================
    // 1. PRÉREQUIS PHP
    // ============================================
    ?>
    <h3>1. Prérequis PHP</h3>
    <div class="box">
        <?php
        $phpOk = version_compare(PHP_VERSION, '7.4.0', '>=');
        $opensslOk = extension_loaded('openssl');
        $curlOk = extension_loaded('curl');
        $jsonOk = extension_loaded('json');
        ?>
        <p>
            <strong>PHP Version:</strong> <?= PHP_VERSION ?> 
            <span class="<?= $phpOk ? 'success' : 'error' ?>"><?= $phpOk ? '✅' : '❌ (7.4+ requis)' ?></span>
        </p>
        <p>
            <strong>Extension OpenSSL:</strong> 
            <span class="<?= $opensslOk ? 'success' : 'error' ?>"><?= $opensslOk ? '✅ Installée' : '❌ Manquante' ?></span>
        </p>
        <p>
            <strong>Extension cURL:</strong> 
            <span class="<?= $curlOk ? 'success' : 'error' ?>"><?= $curlOk ? '✅ Installée' : '❌ Manquante' ?></span>
        </p>
        <p>
            <strong>Extension JSON:</strong> 
            <span class="<?= $jsonOk ? 'success' : 'error' ?>"><?= $jsonOk ? '✅ Installée' : '❌ Manquante' ?></span>
        </p>
        
        <?php if (!$phpOk || !$opensslOk || !$curlOk || !$jsonOk): ?>
            <p class="error">⚠️ Certaines extensions sont manquantes. Contactez votre hébergeur.</p>
        <?php else: ?>
            <p class="success">✅ Tous les prérequis PHP sont OK</p>
        <?php endif; ?>
    </div>
    
    <?php
    // ============================================
    // 2. CONFIGURATION EN BDD
    // ============================================
    ?>
    <h3>2. Configuration en base de données</h3>
    <div class="box">
        <?php
        $settings = new Settings();
        $enabled = $settings->get('google_calendar_enabled');
        $calendarId = $settings->getGoogleCalendarId();
        ?>
        <p>
            <strong>Synchronisation activée:</strong> 
            <span class="<?= $enabled === '1' ? 'success' : 'warning' ?>">
                <?= $enabled === '1' ? '✅ Oui' : '❌ Non' ?>
            </span>
            <?php if ($enabled !== '1'): ?>
                <br><small class="info">→ Activez dans Admin → Paramètres</small>
            <?php endif; ?>
        </p>
        <p>
            <strong>ID Calendrier:</strong> 
            <?php if (!empty($calendarId)): ?>
                <code><?= htmlspecialchars($calendarId) ?></code>
            <?php else: ?>
                <span class="warning">❌ Non défini</span>
                <br><small class="info">→ Configurez dans Admin → Paramètres</small>
            <?php endif; ?>
        </p>
    </div>
    
    <?php
    // ============================================
    // 3. FICHIER CREDENTIALS
    // ============================================
    ?>
    <h3>3. Fichier Service Account (credentials)</h3>
    <div class="box">
        <?php
        $credPath = GOOGLE_CREDENTIALS_PATH;
        $credExists = file_exists($credPath);
        $credValid = false;
        $serviceEmail = '';
        
        if ($credExists) {
            $credContent = json_decode(file_get_contents($credPath), true);
            if ($credContent && isset($credContent['client_email']) && isset($credContent['private_key'])) {
                $credValid = true;
                $serviceEmail = $credContent['client_email'];
            }
        }
        ?>
        <p>
            <strong>Chemin attendu:</strong> <code><?= htmlspecialchars($credPath) ?></code>
        </p>
        <p>
            <strong>Fichier existe:</strong> 
            <span class="<?= $credExists ? 'success' : 'error' ?>">
                <?= $credExists ? '✅ Oui' : '❌ Non' ?>
            </span>
        </p>
        
        <?php if ($credExists): ?>
            <p>
                <strong>Format valide:</strong> 
                <span class="<?= $credValid ? 'success' : 'error' ?>">
                    <?= $credValid ? '✅ Oui' : '❌ Non (JSON invalide ou champs manquants)' ?>
                </span>
            </p>
            <?php if ($credValid): ?>
                <p>
                    <strong>Email Service Account:</strong><br>
                    <code class="code-email">
                        <?= htmlspecialchars($serviceEmail) ?>
                    </code>
                </p>
                <p class="info">
                    ⚠️ <strong>Important:</strong> Cet email doit avoir accès au calendrier Google.<br>
                    → Allez dans Google Calendar → Paramètres du calendrier → Partager → Ajoutez cet email avec permission "Modifier les événements"
                </p>
            <?php endif; ?>
        <?php else: ?>
            <p class="error">
                ⚠️ Le fichier credentials est manquant. Suivez le tutoriel dans le README.
            </p>
        <?php endif; ?>
    </div>
    
    <?php
    // ============================================
    // 4. TEST DE CONNEXION
    // ============================================
    ?>
    <h3>4. Test de connexion à l'API Google</h3>
    <div class="box">
        <?php
        $allPrerequisites = $phpOk && $opensslOk && $curlOk && $jsonOk && 
                           $enabled === '1' && !empty($calendarId) && $credValid;
        
        if (!$allPrerequisites):
        ?>
            <p class="warning">⚠️ Corrigez les problèmes ci-dessus avant de tester la connexion.</p>
        <?php else: ?>
            <?php
            try {
                $gcal = new GoogleCalendarSync();
                
                if ($gcal->isEnabled()) {
                    $testResult = $gcal->testConnection();
                    
                    if ($testResult['success']): ?>
                        <p class="success">✅ Connexion réussie !</p>
                        <table>
                            <tr>
                                <th>Nom du calendrier</th>
                                <td><?= htmlspecialchars($testResult['calendar_name']) ?></td>
                            </tr>
                            <tr>
                                <th>ID du calendrier</th>
                                <td><code><?= htmlspecialchars($testResult['calendar_id']) ?></code></td>
                            </tr>
                        </table>
                        <p class="success">🎉 La synchronisation Google Calendar est opérationnelle !</p>
                    <?php else: ?>
                        <p class="error">❌ Échec de connexion</p>
                        <p><strong>Erreur:</strong> <?= htmlspecialchars($testResult['error']) ?></p>
                        <p class="info">
                            Vérifiez que:<br>
                            • L'ID du calendrier est correct<br>
                            • Le Service Account a accès au calendrier<br>
                            • L'API Google Calendar est activée dans Google Cloud Console
                        </p>
                    <?php endif;
                } else {
                    echo '<p class="warning">⚠️ La classe GoogleCalendarSync n\'est pas activée.</p>';
                }
            } catch (Throwable $e) {
                echo '<p class="error">❌ Exception: ' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '<p>Fichier: ' . basename($e->getFile()) . ' ligne ' . $e->getLine() . '</p>';
            }
            ?>
        <?php endif; ?>
    </div>
    
    <?php
    // ============================================
    // 5. TEST DE CRÉATION D'ÉVÉNEMENT
    // ============================================
    if ($allPrerequisites && isset($gcal) && $gcal->isEnabled()):
    ?>
    <h3>5. Test de création d'événement</h3>
    <div class="box">
        <?php
        if (isset($_GET['test_create'])):
            $testBooking = [
                'id' => 9999,
                'visitor_name' => 'Test Automatique',
                'visitor_email' => 'test@example.com',
                'visitor_phone' => '0600000000',
                'visitor_organization' => 'Test Organisation',
                'slot_date' => date('Y-m-d', strtotime('+1 day')),
                'slot_time_start' => '14:00:00',
                'slot_time_end' => '14:30:00',
                'service_type' => 'diagnostic',
                'subject' => 'Test de synchronisation',
                'message' => 'Ceci est un événement de test créé automatiquement. Vous pouvez le supprimer.',
                'status' => 'pending'
            ];
            
            try {
                $eventId = $gcal->createEvent($testBooking);
                if ($eventId): ?>
                    <p class="success">✅ Événement créé avec succès !</p>
                    <p><strong>ID de l'événement:</strong> <code><?= htmlspecialchars($eventId) ?></code></p>
                    <p>📅 Vérifiez votre Google Calendar - un événement "Test Automatique" devrait apparaître demain à 14h00.</p>
                    <p><a href="?test_delete=<?= urlencode($eventId) ?>" class="btn btn-danger">🗑️ Supprimer cet événement de test</a></p>
                <?php else: ?>
                    <p class="error">❌ La création a échoué (retour null)</p>
                    <p class="info">Consultez les logs PHP pour plus de détails.</p>
                <?php endif;
            } catch (Throwable $e) {
                echo '<p class="error">❌ Exception: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
        
        elseif (isset($_GET['test_delete']) && !empty($_GET['test_delete'])):
            $eventId = $_GET['test_delete'];
            try {
                $deleted = $gcal->deleteEvent($eventId);
                if ($deleted): ?>
                    <p class="success">✅ Événement supprimé avec succès !</p>
                <?php else: ?>
                    <p class="error">❌ La suppression a échoué</p>
                <?php endif;
            } catch (Throwable $e) {
                echo '<p class="error">❌ Exception: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
        
        else: ?>
            <p>Cliquez pour créer un événement de test dans votre Google Calendar :</p>
            <a href="?test_create=1" class="btn">📅 Créer un événement de test</a>
            <p><small class="info">Un RDV fictif sera créé pour demain 14h00. Vous pourrez le supprimer ensuite.</small></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <hr>
    <p><a href="../admin/?page=settings">← Retour aux paramètres</a></p>
</body>
</html>
