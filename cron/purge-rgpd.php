<?php
/**
 * ============================================
 * CRON : PURGE RGPD DES DONNÉES
 * ============================================
 * Script à exécuter quotidiennement via CRON OVH
 * 
 * 3 niveaux de purge :
 * 1. Troncature IP après 90 jours (dernier octet → .0)
 * 2. Suppression IP/UA après 180 jours
 * 3. Suppression complète des RDV après 365 jours
 * 
 * Usage CRON OVH :
 *   php /homez.XXX/votrelogin/www/cron/purge-rgpd.php
 * 
 * Ou via URL protégée (clé = RGPD_CRON_SECRET défini dans config.local.php) :
 *   https://votresite.fr/cron/purge-rgpd.php?key=<RGPD_CRON_SECRET>
 *
 * Dépend de : includes/init.php, Database.php, Helpers::requireCronAuth()
 */

// Bootstrap d'abord : charge config.local (→ RGPD_CRON_SECRET) et les classes.
require_once __DIR__ . '/../includes/init.php';

use App\Database;
use App\Helpers;

// Gate d'accès mutualisée — CLI ou ?key= comparé en temps constant, fail-closed.
// Secret lu depuis config.local (RÈGLE 5.1 : JAMAIS en dur). À définir :
// RGPD_CRON_SECRET dans config.local.php (cf. config.local.php.template).
Helpers::requireCronAuth(defined('RGPD_CRON_SECRET') ? (string) RGPD_CRON_SECRET : '');

// ============================================
// CONFIGURATION DES DURÉES
// ============================================
$TRUNCATE_IP_AFTER_DAYS = 90;   // Troncature IP après 3 mois
$DELETE_IP_UA_AFTER_DAYS = 180; // Suppression IP/UA après 6 mois
$DELETE_BOOKING_AFTER_DAYS = 365; // Suppression complète après 1 an

// ============================================
// FONCTIONS
// ============================================

/**
 * Logger un message (STDOUT en CLI, error_log sinon)
 */
function purgeLog(string $message): void
{
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] [PURGE-RGPD] $message";
    
    if (php_sapi_name() === 'cli') {
        echo $line . PHP_EOL;
    }
    error_log($line);
}

// ============================================
// EXÉCUTION DES PURGES
// ============================================

$db = Database::getInstance();
$totalActions = 0;

purgeLog('=== Début de la purge RGPD ===');


// --- ÉTAPE 1 : Troncature IP après 90 jours ---
// L'IP 192.168.1.45 devient 192.168.1.0
try {
    $stmt = $db->prepare("
        UPDATE bookings 
        SET ip_address = CONCAT(SUBSTRING_INDEX(ip_address, '.', 3), '.0')
        WHERE ip_address IS NOT NULL 
          AND ip_address != '' 
          AND ip_address NOT LIKE '%.0'
          AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
    ");
    $stmt->execute([':days' => $TRUNCATE_IP_AFTER_DAYS]);
    $count = $stmt->rowCount();
    $totalActions += $count;
    purgeLog("Étape 1 — IP tronquées (>{$TRUNCATE_IP_AFTER_DAYS}j) : $count");
} catch (PDOException $e) {
    purgeLog("ERREUR Étape 1 : " . $e->getMessage());
}


// --- ÉTAPE 2 : Suppression IP + User Agent après 180 jours ---
try {
    $stmt = $db->prepare("
        UPDATE bookings 
        SET ip_address = NULL, 
            user_agent = NULL
        WHERE (ip_address IS NOT NULL OR user_agent IS NOT NULL)
          AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
    ");
    $stmt->execute([':days' => $DELETE_IP_UA_AFTER_DAYS]);
    $count = $stmt->rowCount();
    $totalActions += $count;
    purgeLog("Étape 2 — IP/UA supprimés (>{$DELETE_IP_UA_AFTER_DAYS}j) : $count");
} catch (PDOException $e) {
    purgeLog("ERREUR Étape 2 : " . $e->getMessage());
}


// --- ÉTAPE 3 : Suppression complète des RDV après 365 jours ---
// On ne supprime PAS les données de facturation (gérées séparément si elles existent)
try {
    // D'abord, compter pour le log
    $stmtCount = $db->prepare("
        SELECT COUNT(*) as total 
        FROM bookings 
        WHERE slot_date < DATE_SUB(NOW(), INTERVAL :days DAY)
    ");
    $stmtCount->execute([':days' => $DELETE_BOOKING_AFTER_DAYS]);
    $toDelete = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($toDelete > 0) {
        // Optionnel : sauvegarder des stats anonymisées avant suppression
        $stmtStats = $db->prepare("
            INSERT IGNORE INTO purge_stats (purge_date, bookings_deleted, period_start, period_end)
            SELECT 
                CURDATE(),
                COUNT(*),
                MIN(slot_date),
                MAX(slot_date)
            FROM bookings 
            WHERE slot_date < DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        // La table purge_stats est optionnelle — on ignore l'erreur si elle n'existe pas
        try {
            $stmtStats->execute([':days' => $DELETE_BOOKING_AFTER_DAYS]);
        } catch (PDOException $e) {
            // Table purge_stats non créée — ce n'est pas bloquant
            purgeLog("Note : table purge_stats absente (optionnel)");
        }
        
        // Supprimer les réservations
        $stmtDelete = $db->prepare("
            DELETE FROM bookings 
            WHERE slot_date < DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        $stmtDelete->execute([':days' => $DELETE_BOOKING_AFTER_DAYS]);
        $count = $stmtDelete->rowCount();
        $totalActions += $count;
        purgeLog("Étape 3 — RDV supprimés (>{$DELETE_BOOKING_AFTER_DAYS}j) : $count");
    } else {
        purgeLog("Étape 3 — Aucun RDV à supprimer");
    }
} catch (PDOException $e) {
    purgeLog("ERREUR Étape 3 : " . $e->getMessage());
}


// --- ÉTAPE 4 : Nettoyage des demandes de suppression expirées ---
try {
    $stmt = $db->prepare("
        DELETE FROM rgpd_deletion_log 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 3 YEAR)
    ");
    $stmt->execute();
    $count = $stmt->rowCount();
    if ($count > 0) {
        $totalActions += $count;
        purgeLog("Étape 4 — Logs de suppression RGPD nettoyés : $count");
    }
} catch (PDOException $e) {
    // Table non créée — pas bloquant
    purgeLog("Note : table rgpd_deletion_log absente (optionnel)");
}


purgeLog("=== Purge terminée — $totalActions actions effectuées ===");

// Réponse HTTP si appelé via URL
if (!$isCliExecution) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => "Purge RGPD terminée : $totalActions actions",
        'timestamp' => date('c')
    ]);
}
