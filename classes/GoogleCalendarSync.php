<?php
/**
 * ============================================
 * CLASSE GOOGLE CALENDAR SYNC (STANDALONE)
 * ============================================
 * 
 * Synchronisation avec Google Calendar SANS librairie externe.
 * Utilise directement l'API REST de Google avec un Service Account.
 * 
 * FONCTIONNEMENT :
 * 1. Charge le fichier JSON du Service Account
 * 2. Génère un JWT (JSON Web Token) signé
 * 3. Échange le JWT contre un Access Token
 * 4. Utilise l'Access Token pour appeler l'API Calendar
 * 
 * PRÉREQUIS :
 * - PHP 7.4+ avec extensions openssl et curl
 * - Fichier google-credentials.json (Service Account)
 * - Calendrier partagé avec le Service Account
 */

namespace App;

class GoogleCalendarSync
{
    private bool $enabled = false;
    private string $calendarId = '';
    private ?string $accessToken = null;
    private ?array $credentials = null;
    private int $tokenExpiry = 0;
    
    // URLs API Google
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const CALENDAR_API_BASE = 'https://www.googleapis.com/calendar/v3';
    
    public function __construct()
    {
        // Charger la config depuis la BDD
        $settings = new Settings();
        $this->enabled = $settings->isGoogleCalendarEnabled();
        $this->calendarId = $settings->getGoogleCalendarId();
        
        if ($this->enabled && !empty($this->calendarId)) {
            $this->loadCredentials();
        } else {
            $this->enabled = false;
        }
    }
    
    /**
     * Vérifier si la sync est activée et fonctionnelle
     */
    public function isEnabled(): bool
    {
        return $this->enabled && $this->credentials !== null;
    }
    
    /**
     * Récupérer l'ID du calendrier
     */
    public function getCalendarId(): string
    {
        return $this->calendarId;
    }
    
    /**
     * Charger les credentials du Service Account
     */
    private function loadCredentials(): void
    {
        $credPath = GOOGLE_CREDENTIALS_PATH;
        
        if (!file_exists($credPath)) {
            error_log('GoogleCalendarSync: Fichier credentials manquant: ' . $credPath);
            $this->enabled = false;
            return;
        }
        
        $content = file_get_contents($credPath);
        $this->credentials = json_decode($content, true);
        
        if (!$this->credentials || 
            !isset($this->credentials['client_email']) || 
            !isset($this->credentials['private_key'])) {
            error_log('GoogleCalendarSync: Fichier credentials invalide');
            $this->credentials = null;
            $this->enabled = false;
        }
    }
    
    /**
     * Générer un JWT pour l'authentification Service Account
     */
    private function generateJWT(): string
    {
        $now = time();
        $expiry = $now + 3600; // 1 heure
        
        // Header
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT'
        ];
        
        // Payload (claims)
        $payload = [
            'iss' => $this->credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/calendar',
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $expiry
        ];
        
        // Encoder en Base64 URL-safe
        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
        
        // Signer avec la clé privée
        $dataToSign = $headerEncoded . '.' . $payloadEncoded;
        $signature = '';
        
        $privateKey = openssl_pkey_get_private($this->credentials['private_key']);
        if (!$privateKey) {
            throw new \Exception('Impossible de charger la clé privée');
        }
        
        openssl_sign($dataToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        
        $signatureEncoded = $this->base64UrlEncode($signature);
        
        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }
    
    /**
     * Encoder en Base64 URL-safe (sans padding)
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Obtenir un Access Token valide
     */
    private function getAccessToken(): ?string
    {
        // Utiliser le token en cache s'il est encore valide
        if ($this->accessToken && time() < $this->tokenExpiry - 60) {
            return $this->accessToken;
        }
        
        try {
            $jwt = $this->generateJWT();
            
            $postData = [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ];
            
            $ch = curl_init(self::TOKEN_URL);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($postData),
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_TIMEOUT => 30
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                error_log('GoogleCalendarSync: Erreur token - HTTP ' . $httpCode . ' - ' . $response);
                return null;
            }
            
            $data = json_decode($response, true);
            
            if (isset($data['access_token'])) {
                $this->accessToken = $data['access_token'];
                $this->tokenExpiry = time() + ($data['expires_in'] ?? 3600);
                return $this->accessToken;
            }
            
            error_log('GoogleCalendarSync: Réponse token invalide - ' . $response);
            return null;
            
        } catch (\Exception $e) {
            error_log('GoogleCalendarSync: Exception token - ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Faire une requête à l'API Google Calendar
     */
    private function apiRequest(string $method, string $endpoint, ?array $data = null): ?array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return null;
        }
        
        $url = self::CALENDAR_API_BASE . $endpoint;
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'PUT':
            case 'PATCH':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log('GoogleCalendarSync: Erreur CURL - ' . $error);
            return null;
        }
        
        // DELETE retourne 204 No Content
        if ($httpCode === 204) {
            return ['success' => true];
        }
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true) ?: ['success' => true];
        }
        
        error_log('GoogleCalendarSync: Erreur API - HTTP ' . $httpCode . ' - ' . $response);
        return null;
    }
    
    /**
     * Créer un événement pour une nouvelle réservation
     */
    public function createEvent(array $booking): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }
        
        $statusPrefix = ($booking['status'] ?? 'pending') === 'confirmed' ? '[OK]' : '[ATTENTE]';
        
        // Construire les dates au format ISO 8601
        $startDateTime = $booking['slot_date'] . 'T' . $booking['slot_time_start'];
        $endDateTime = $booking['slot_date'] . 'T' . $booking['slot_time_end'];
        
        // S'assurer que les heures sont au bon format
        if (strlen($booking['slot_time_start']) === 5) {
            $startDateTime .= ':00';
        }
        if (strlen($booking['slot_time_end']) === 5) {
            $endDateTime .= ':00';
        }
        
        $event = [
            'summary' => "{$statusPrefix} RDV - {$booking['visitor_name']}",
            'description' => $this->buildDescription($booking),
            'start' => [
                'dateTime' => $startDateTime,
                'timeZone' => TIMEZONE
            ],
            'end' => [
                'dateTime' => $endDateTime,
                'timeZone' => TIMEZONE
            ],
            'colorId' => $this->getColorId($booking['status'] ?? 'pending'),
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'popup', 'minutes' => 60],
                    ['method' => 'popup', 'minutes' => 15]
                ]
            ]
        ];
        
        $calendarIdEncoded = urlencode($this->calendarId);
        $result = $this->apiRequest('POST', "/calendars/{$calendarIdEncoded}/events", $event);
        
        if ($result && isset($result['id'])) {
            return $result['id'];
        }
        
        return null;
    }
    
    /**
     * Mettre à jour un événement existant
     */
    public function updateEvent(string $eventId, array $booking): bool
    {
        if (!$this->isEnabled() || empty($eventId)) {
            return false;
        }
        
        // Déterminer le préfixe selon le statut
        switch ($booking['status'] ?? 'pending') {
            case 'confirmed':
                $prefix = '[OK]';
                break;
            case 'cancelled':
                $prefix = '[ANNULÉ]';
                break;
            case 'completed':
                $prefix = '[TERMINÉ]';
                break;
            default:
                $prefix = '[ATTENTE]';
        }
        
        // Construire les dates
        $startDateTime = $booking['slot_date'] . 'T' . $booking['slot_time_start'];
        $endDateTime = $booking['slot_date'] . 'T' . $booking['slot_time_end'];
        
        if (strlen($booking['slot_time_start']) === 5) {
            $startDateTime .= ':00';
        }
        if (strlen($booking['slot_time_end']) === 5) {
            $endDateTime .= ':00';
        }
        
        $event = [
            'summary' => "{$prefix} RDV - {$booking['visitor_name']}",
            'description' => $this->buildDescription($booking),
            'start' => [
                'dateTime' => $startDateTime,
                'timeZone' => TIMEZONE
            ],
            'end' => [
                'dateTime' => $endDateTime,
                'timeZone' => TIMEZONE
            ],
            'colorId' => $this->getColorId($booking['status'] ?? 'pending')
        ];
        
        $calendarIdEncoded = urlencode($this->calendarId);
        $eventIdEncoded = urlencode($eventId);
        $result = $this->apiRequest('PUT', "/calendars/{$calendarIdEncoded}/events/{$eventIdEncoded}", $event);
        
        return $result !== null;
    }
    
    /**
     * Supprimer un événement
     */
    public function deleteEvent(string $eventId): bool
    {
        if (!$this->isEnabled() || empty($eventId)) {
            return false;
        }
        
        $calendarIdEncoded = urlencode($this->calendarId);
        $eventIdEncoded = urlencode($eventId);
        $result = $this->apiRequest('DELETE', "/calendars/{$calendarIdEncoded}/events/{$eventIdEncoded}");
        
        return $result !== null;
    }
    
    /**
     * Obtenir l'ID de couleur Google Calendar selon le statut
     * Couleurs disponibles : 1-11
     * 1=Lavande, 2=Sauge, 3=Raisin, 4=Flamant, 5=Banane
     * 6=Mandarine, 7=Paon, 8=Graphite, 9=Myrtille, 10=Basilic, 11=Tomate
     */
    private function getColorId(string $status): string
    {
        $colors = [
            'pending' => '6',    // Mandarine (orange) - En attente
            'confirmed' => '10', // Basilic (vert) - Confirmé
            'cancelled' => '8',  // Graphite (gris) - Annulé
            'completed' => '9'   // Myrtille (bleu) - Terminé
        ];
        
        return $colors[$status] ?? '6';
    }
    
    /**
     * Construire la description de l'événement
     */
    private function buildDescription(array $booking): string
    {
        $lines = [];
        
        $lines[] = "📧 Email : {$booking['visitor_email']}";
        
        if (!empty($booking['visitor_phone'])) {
            $lines[] = "📞 Tél : {$booking['visitor_phone']}";
        }
        
        if (!empty($booking['visitor_organization'])) {
            $lines[] = "🏢 Organisation : {$booking['visitor_organization']}";
        }
        
        $serviceName = SERVICE_TYPES[$booking['service_type']] ?? $booking['service_type'] ?? 'Non spécifié';
        $lines[] = "📋 Service : {$serviceName}";
        
        if (!empty($booking['subject'])) {
            $lines[] = "📝 Objet : {$booking['subject']}";
        }
        
        $status = $booking['status'] ?? 'pending';
        $statusInfo = BOOKING_STATUS[$status] ?? null;
        if ($statusInfo) {
            $lines[] = "";
            $lines[] = "Statut : {$statusInfo['icon']} {$statusInfo['label']}";
        }
        
        if (!empty($booking['message'])) {
            $lines[] = "";
            $lines[] = "💬 Message :";
            $lines[] = $booking['message'];
        }
        
        $lines[] = "";
        $lines[] = "---";
        $lines[] = "⚠️ NE PAS MODIFIER depuis Google Calendar";
        $lines[] = "Gérer depuis : " . BASE_URL . "admin/";
        
        return implode("\n", $lines);
    }
    
    /**
     * Tester la connexion à l'API Google Calendar
     */
    public function testConnection(): array
    {
        if (!$this->credentials) {
            return [
                'success' => false,
                'error' => 'Fichier credentials non chargé'
            ];
        }
        
        $token = $this->getAccessToken();
        if (!$token) {
            return [
                'success' => false,
                'error' => 'Impossible d\'obtenir un access token'
            ];
        }
        
        // Tester l'accès au calendrier
        $calendarIdEncoded = urlencode($this->calendarId);
        $result = $this->apiRequest('GET', "/calendars/{$calendarIdEncoded}");
        
        if ($result && isset($result['id'])) {
            return [
                'success' => true,
                'calendar_name' => $result['summary'] ?? 'Inconnu',
                'calendar_id' => $result['id']
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Accès au calendrier refusé. Vérifiez le partage.'
        ];
    }
}
