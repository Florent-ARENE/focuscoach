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
                
            } catch (PDOException $e) {
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
