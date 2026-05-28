<?php
/**
 * ============================================
 * CLASSE HELPERS
 * ============================================
 * Fonctions utilitaires statiques
 */

namespace App;

class Helpers
{
    /**
     * Nettoyer une entrée utilisateur
     */
    /**
     * Nettoyer une entrée utilisateur (sans encodage HTML)
     * L'encodage HTML doit être fait à l'affichage, pas au stockage
     */
    public static function sanitize(string $input): string
    {
        return trim($input);
    }
    
    /**
     * Échapper pour affichage HTML (à utiliser dans les templates)
     */
    public static function escape(string $input): string
    {
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Générer un token CSRF
     */
    public static function generateCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION[CSRF_TOKEN_NAME];
    }
    
    /**
     * Vérifier un token CSRF
     */
    public static function verifyCsrfToken(string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION[CSRF_TOKEN_NAME]) 
            && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }
    
    /**
     * Générer un champ hidden CSRF
     */
    public static function csrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . self::generateCsrfToken() . '">';
    }
    
    /**
     * Formater une date en français
     */
    public static function formatDateFr(string $date): string
    {
        $timestamp = strtotime($date);
        $dayName = DAYS_OF_WEEK[(int)date('N', $timestamp)];
        $day = date('j', $timestamp);
        $month = MONTHS_FR[(int)date('n', $timestamp)];
        $year = date('Y', $timestamp);
        
        return "$dayName $day $month $year";
    }
    
    /**
     * Formater un créneau horaire
     */
    public static function formatTimeSlot(string $start, string $end): string
    {
        return substr($start, 0, 5) . ' - ' . substr($end, 0, 5);
    }
    
    /**
     * Envoyer une réponse JSON
     */
    public static function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Récupérer les données JSON d'une requête
     */
    public static function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }
    
    /**
     * Vérifier si une requête est AJAX
     */
    public static function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Rediriger avec message flash
     */
    public static function redirect(string $url, ?string $message = null, string $type = 'info'): void
    {
        if ($message) {
            $_SESSION['flash'] = ['message' => $message, 'type' => $type];
        }
        header('Location: ' . $url);
        exit;
    }
    
    /**
     * Récupérer et effacer le message flash
     */
    public static function getFlash(): ?array
    {
        if (isset($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $flash;
        }
        return null;
    }
    
    /**
     * Valider un email
     */
    public static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Valider un format de date
     */
    public static function isValidDate(string $date): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1;
    }
    
    /**
     * Valider un format d'heure
     */
    public static function isValidTime(string $time): bool
    {
        return preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time) === 1;
    }
}
