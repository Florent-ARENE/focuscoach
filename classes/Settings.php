<?php
/**
 * ============================================
 * CLASSE SETTINGS
 * ============================================
 * Gestion des paramètres stockés en BDD
 */

namespace App;

use PDO;

class Settings
{
    private PDO $db;
    private static array $cache = [];
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Récupérer une valeur
     */
    public function get(string $key, $default = null)
    {
        // Cache en mémoire
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        
        try {
            $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = :key");
            $stmt->execute([':key' => $key]);
            $result = $stmt->fetch();
            
            if ($result) {
                self::$cache[$key] = $result['setting_value'];
                return $result['setting_value'];
            }
        } catch (\PDOException $e) {
            // Table peut ne pas exister encore
        }
        
        return $default;
    }
    
    /**
     * Définir une valeur
     */
    public function set(string $key, $value): bool
    {
        try {
            // Vérifier si la clé existe
            $stmt = $this->db->prepare("SELECT id FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            
            if ($stmt->fetch()) {
                // UPDATE
                $stmt = $this->db->prepare("UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            } else {
                // INSERT
                $stmt = $this->db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
                $stmt->execute([$key, $value]);
            }
            
            self::$cache[$key] = $value;
            return true;
            
        } catch (\PDOException $e) {
            error_log('Settings::set error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Récupérer tous les paramètres
     */
    public function getAll(): array
    {
        try {
            $stmt = $this->db->query("SELECT setting_key, setting_value FROM settings ORDER BY setting_key");
            $results = $stmt->fetchAll();
            
            $settings = [];
            foreach ($results as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
                self::$cache[$row['setting_key']] = $row['setting_value'];
            }
            
            return $settings;
            
        } catch (\PDOException $e) {
            return [];
        }
    }
    
    /**
     * Mettre à jour plusieurs paramètres
     */
    public function setMultiple(array $settings): bool
    {
        foreach ($settings as $key => $value) {
            if (!$this->set($key, $value)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Vérifier si Google Calendar est activé
     */
    public function isGoogleCalendarEnabled(): bool
    {
        return $this->get('google_calendar_enabled', '0') === '1';
    }
    
    /**
     * Récupérer l'ID du calendrier Google
     */
    public function getGoogleCalendarId(): string
    {
        return $this->get('google_calendar_id', '');
    }
}
