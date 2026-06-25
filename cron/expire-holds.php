<?php
/**
 * ============================================
 * CRON : EXPIRATION DES HOLDS STRIPE (§6 / S5)
 * ============================================
 * Passe en `status='expired'` les réservations `pending_payment` dont le hold
 * (payment_expires_at) est dépassé → libère leur `active_key`, rendant le
 * créneau de nouveau réservable. Idempotent, bornable.
 *
 * Usage CRON OVH (toutes les ~5 min) :
 *   php /home/.../www/cron/expire-holds.php
 *
 * Ou via URL protégée par token (clé en config.local.php, PAS de placeholder
 * codé en dur ici) :
 *   https://focuscoach.fr/cron/expire-holds.php?key=<CRON_SECRET>
 *
 * En CLI : toujours autorisé. En HTTP : refusé si CRON_SECRET n'est pas défini
 * (ou clé fournie incorrecte) → on ne laisse jamais un endpoint ouvert.
 */

require_once __DIR__ . '/../includes/init.php';

use App\Booking;
use App\Helpers;

// Gate mutualisée (CLI ou ?key= en temps constant, fail-closed) — cf. Helpers::requireCronAuth.
Helpers::requireCronAuth(defined('CRON_SECRET') ? (string) CRON_SECRET : '');

$expired = (new Booking())->expireStaleHolds();

$message = sprintf('[expire-holds] %s — %d hold(s) expiré(s).', date('Y-m-d H:i:s'), $expired);
error_log($message);
echo $message . "\n";
