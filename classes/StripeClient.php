<?php
/**
 * ============================================
 * CLASSE STRIPECLIENT — appels API Stripe en cURL direct
 * ============================================
 * Pas de librairie Stripe (cohérent OVH mutualisé / style GoogleCalendarSync).
 * Timeouts bornés ; tout échec est rendu explicite (jamais de booking fantôme :
 * c'est l'appelant qui libère le hold sur erreur).
 *
 * Sécurité : la clé secrète (STRIPE_SECRET_KEY) ne transite QUE dans l'en-tête
 * Authorization, jamais loggée. Les URLs success/cancel viennent de BASE_URL
 * (jamais $_SERVER['HTTP_HOST']) — cf. §6.
 */

namespace App;

class StripeClient
{
    private const API_BASE        = 'https://api.stripe.com/v1';
    private const CONNECT_TIMEOUT = 10;
    private const TOTAL_TIMEOUT   = 20;

    /**
     * Crée une session Stripe Checkout (mode `payment`) pour un Price donné.
     *
     * @param string $priceId    `stripe_price_id` du service (price_…).
     * @param string $successUrl URL absolue de retour succès (depuis BASE_URL).
     * @param string $cancelUrl  URL absolue de retour annulation (depuis BASE_URL).
     * @param array  $metadata   Métadonnées (ex. ['booking_id' => 42]) — tracées
     *                           côté Stripe ET reprises en client_reference_id.
     *
     * @return array ['id' => session_id, 'url' => checkout_url] en succès,
     *               ['error' => message] en échec (clé invalide, réseau, 0 €…).
     */
    public static function createCheckoutSession(string $priceId, string $successUrl, string $cancelUrl, array $metadata = []): array
    {
        if (!stripeEnabled()) {
            return ['error' => 'Stripe non configuré'];
        }
        if ($priceId === '') {
            return ['error' => 'stripe_price_id manquant'];
        }

        $fields = [
            'mode'                     => 'payment',
            'line_items[0][price]'     => $priceId,
            'line_items[0][quantity]'  => 1,
            'success_url'              => $successUrl,
            'cancel_url'               => $cancelUrl,
        ];
        foreach ($metadata as $key => $value) {
            $fields["metadata[$key]"] = (string) $value;
        }
        if (isset($metadata['booking_id'])) {
            $fields['client_reference_id'] = (string) $metadata['booking_id'];
        }

        $response = self::post('/checkout/sessions', $fields);
        if (isset($response['error'])) {
            return $response;
        }
        if (empty($response['id']) || empty($response['url'])) {
            return ['error' => 'Réponse Stripe inattendue'];
        }
        return ['id' => $response['id'], 'url' => $response['url']];
    }

    /**
     * Vérifie la signature d'un webhook Stripe (en-tête `Stripe-Signature`,
     * schéma `t=timestamp,v1=hmac…`). HMAC-SHA256 sur `timestamp.payload`
     * (LE CORPS BRUT, jamais ré-encodé), comparaison constante (hash_equals),
     * tolérance d'horloge. Pur → testable hors réseau.
     *
     * @param string $payload   Corps HTTP brut (file_get_contents('php://input')).
     * @param string $sigHeader Valeur de l'en-tête Stripe-Signature.
     * @param string $secret    STRIPE_WEBHOOK_SECRET (whsec_…).
     * @param int    $tolerance Fenêtre d'horloge en secondes (défaut 300).
     */
    public static function verifyWebhookSignature(string $payload, string $sigHeader, string $secret, int $tolerance = 300): bool
    {
        if ($secret === '' || strpos($secret, 'REPLACE_WITH_') === 0 || $sigHeader === '') {
            return false;
        }
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $sigHeader) as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) !== 2) {
                continue;
            }
            if ($kv[0] === 't') {
                $timestamp = $kv[1];
            } elseif ($kv[0] === 'v1') {
                $signatures[] = $kv[1];
            }
        }
        if ($timestamp === null || !ctype_digit($timestamp) || $signatures === []) {
            return false;
        }
        if (abs(time() - (int) $timestamp) > $tolerance) {
            return false; // hors fenêtre → rejet (anti-rejeu)
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        foreach ($signatures as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }
        return false;
    }

    /**
     * POST x-www-form-urlencoded vers l'API Stripe. Retourne le JSON décodé,
     * ou ['error' => …] sur échec réseau/HTTP. Ne logge jamais la clé.
     */
    private static function post(string $path, array $fields): array
    {
        $ch = curl_init(self::API_BASE . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($fields),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . STRIPE_SECRET_KEY,
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT        => self::TOTAL_TIMEOUT,
        ]);
        $raw  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $code === 0) {
            error_log('StripeClient: réseau Stripe injoignable - ' . $cerr);
            return ['error' => 'Stripe momentanément injoignable'];
        }
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            return ['error' => 'Réponse Stripe illisible (HTTP ' . $code . ')'];
        }
        if ($code >= 400) {
            $msg = $data['error']['message'] ?? ('HTTP ' . $code);
            error_log('StripeClient: erreur API Stripe - ' . $msg);
            return ['error' => $msg];
        }
        return $data;
    }
}
