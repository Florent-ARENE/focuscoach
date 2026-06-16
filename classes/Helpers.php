<?php
/**
 * ============================================
 * CLASSE HELPERS
 * ============================================
 * Fonctions utilitaires statiques
 */

namespace App;

use PDO;

class Helpers
{
    // Politique rate limit login admin — fenêtre plate, deux constantes.
    public const LOGIN_MAX_ATTEMPTS = 5;
    public const LOGIN_WINDOW_MIN   = 15;

    /**
     * IP du client telle qu'observée côté serveur. Pas de prise en compte
     * des en-têtes proxy (X-Forwarded-For) : on est sur OVH mutualisé,
     * pas de reverse proxy de confiance — sinon vecteur de spoofing.
     */
    public static function clientIp(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    /**
     * Vrai si l'IP est verrouillée (≥ LOGIN_MAX_ATTEMPTS échecs sur la
     * fenêtre LOGIN_WINDOW_MIN). Les succès remettent le compteur à zéro
     * via recordLoginAttempt(true).
     */
    public static function isLoginLocked(string $ip): bool
    {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare(
                "SELECT COUNT(*) FROM admin_login_attempts
                 WHERE ip_address = :ip
                   AND success = 0
                   AND attempted_at > (NOW() - INTERVAL :win MINUTE)"
            );
            $stmt->bindValue(':ip', $ip);
            $stmt->bindValue(':win', self::LOGIN_WINDOW_MIN, PDO::PARAM_INT);
            $stmt->execute();
            return ((int) $stmt->fetchColumn()) >= self::LOGIN_MAX_ATTEMPTS;
        } catch (\PDOException $e) {
            // En cas d'erreur BDD, on ne bloque pas (fail-open sur la lecture,
            // l'auth bcrypt reste la dernière ligne de défense).
            return false;
        }
    }

    /**
     * Enregistre une tentative (succès ou échec). En cas de succès, purge
     * les échecs antérieurs de l'IP pour relâcher le verrou immédiatement.
     */
    public static function recordLoginAttempt(string $ip, bool $success): void
    {
        try {
            $db = Database::getInstance();
            if ($success) {
                $purge = $db->prepare(
                    "DELETE FROM admin_login_attempts WHERE ip_address = :ip AND success = 0"
                );
                $purge->execute([':ip' => $ip]);
            }
            $stmt = $db->prepare(
                "INSERT INTO admin_login_attempts (ip_address, success) VALUES (:ip, :s)"
            );
            $stmt->bindValue(':ip', $ip);
            $stmt->bindValue(':s', $success ? 1 : 0, PDO::PARAM_INT);
            $stmt->execute();
        } catch (\PDOException $e) {
            // Échec de log = pas bloquant pour l'auth.
        }
    }

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
     * Vérifier le token CSRF d'une requête (header X-CSRF-Token > body csrf_token).
     * Retourne true si valide, false sinon. Préfère le header pour les requêtes
     * JSON ; le body est gardé comme fallback pour les forms multipart.
     */
    public static function verifyCsrfFromRequest(array $body = []): bool
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN']
              ?? $body['csrf_token']
              ?? $_POST['csrf_token']
              ?? '';
        if (!is_string($token) || $token === '') {
            return false;
        }
        return self::verifyCsrfToken($token);
    }

    /**
     * Renvoie un 403 JSON et exit (à appeler quand verifyCsrfFromRequest = false).
     */
    public static function csrfFailure(): void
    {
        self::jsonResponse([
            'success' => false,
            'error'   => 'CSRF token invalide ou absent',
            'code'    => 'CSRF_INVALID'
        ], 403);
    }

    /**
     * Générer un champ hidden CSRF
     */
    public static function csrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . self::generateCsrfToken() . '">';
    }

    /**
     * Meta tag CSRF pour le <head> — lu côté JS par les helpers fetch.
     * Usage : <?= Helpers::csrfMeta() ?>
     */
    public static function csrfMeta(): string
    {
        return '<meta name="csrf-token" content="' . self::generateCsrfToken() . '">';
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
     * Récupérer les données JSON d'une requête (cache statique : `php://input`
     * peut être lu plusieurs fois en PHP ≥ 5.6, mais on évite l'I/O répété).
     */
    public static function getJsonInput(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $input = file_get_contents('php://input');
        $cached = json_decode((string) $input, true) ?? [];
        return $cached;
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
