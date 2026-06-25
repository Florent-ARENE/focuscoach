<?php
/**
 * ============================================
 * CLASSE DATABASE
 * ============================================
 * Singleton pour la connexion PDO MySQL
 * Utilise les constantes DB_* définies dans config.local.php
 */

namespace App;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;
    
    /**
     * Empêcher l'instanciation directe
     */
    private function __construct() {}
    
    /**
     * Empêcher le clonage
     */
    private function __clone() {}
    
    /**
     * Empêcher la désérialisation
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
    
    /**
     * Obtenir l'instance de connexion PDO
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    DB_HOST,
                    DB_PORT,
                    DB_NAME,
                    DB_CHARSET
                );
                
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ];
                
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);

                // Aligner le fuseau MySQL sur celui de PHP (sinon NOW() SQL
                // peut être en UTC pendant que PHP est en Europe/Paris → écart
                // d'1 ou 2 h entre created_at et les calculs DateTime PHP).
                // date('P') donne l'offset numérique courant (+01:00 / +02:00
                // avec DST), accepté par `SET time_zone`.
                self::$instance->exec("SET time_zone = '" . date('P') . "'");

            } catch (PDOException $e) {
                // BACKLOG (dette racine, repérée en 2.7.0) : ce die() n'est
                // PAS rattrapable → toute page touchant la BDD meurt dur si
                // la BDD tombe (y compris siteConfig() via Settings). Le
                // module diagnostic le contourne avec son propre connect
                // résilient (diag_try_pdo). À terme : faire remonter une
                // exception ici (laisser l'appelant décider du dégradé), et
                // en faire un indicateur de santé. Cf. CHANGELOG 2.7.0.
                if (APP_DEBUG) {
                    die('Erreur de connexion : ' . $e->getMessage());
                }
                die('Erreur de connexion à la base de données.');
            }
        }
        
        return self::$instance;
    }
    
    /**
     * Alias pour getInstance()
     */
    public static function getConnection(): PDO
    {
        return self::getInstance();
    }
}
