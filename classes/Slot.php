<?php
/**
 * ============================================
 * CLASSE SLOT
 * ============================================
 * Gestion des créneaux disponibles
 */

namespace App;

use PDO;
use DateTime;

class Slot
{
    private PDO $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Récupérer les créneaux disponibles pour une date
     */
    public function getAvailableForDate(string $date): array
    {
        // Vérifier si la date n'est pas bloquée
        if ($this->isDateBlocked($date)) {
            return [];
        }
        
        // Récupérer le jour de la semaine (1=Lundi, 7=Dimanche)
        $dayOfWeek = (int) date('N', strtotime($date));
        
        // Récupérer les créneaux définis pour ce jour
        $stmt = $this->db->prepare("
            SELECT time_start, time_end 
            FROM available_slots 
            WHERE day_of_week = :day AND is_active = 1
            ORDER BY time_start
        ");
        $stmt->execute([':day' => $dayOfWeek]);
        $definedSlots = $stmt->fetchAll();
        
        if (empty($definedSlots)) {
            return [];
        }
        
        // Récupérer les réservations existantes
        $stmt = $this->db->prepare("
            SELECT slot_time_start, slot_time_end 
            FROM bookings 
            WHERE slot_date = :date AND status IN ('pending', 'confirmed')
        ");
        $stmt->execute([':date' => $date]);
        $bookedSlots = $stmt->fetchAll();
        $bookedTimes = array_column($bookedSlots, 'slot_time_start');
        
        // Filtrer les créneaux disponibles
        $availableSlots = [];
        $now = new DateTime();
        $minBookingTime = (clone $now)->modify('+' . BOOKING_ADVANCE_MIN_HOURS . ' hours');
        
        foreach ($definedSlots as $slot) {
            $slotStart = new DateTime($date . ' ' . $slot['time_start']);
            
            // Vérifier que le créneau est dans le futur
            if ($slotStart < $minBookingTime) {
                continue;
            }
            
            // Vérifier que le créneau n'est pas déjà réservé
            if (in_array($slot['time_start'], $bookedTimes)) {
                continue;
            }
            
            $availableSlots[] = [
                'time_start' => $slot['time_start'],
                'time_end' => $slot['time_end'],
                'label' => Helpers::formatTimeSlot($slot['time_start'], $slot['time_end'])
            ];
        }
        
        return $availableSlots;
    }
    
    /**
     * Récupérer les dates disponibles pour les X prochains jours
     */
    public function getAvailableDates(?int $days = null): array
    {
        $days = $days ?? BOOKING_ADVANCE_MAX_DAYS;
        $availableDates = [];
        
        $startDate = new DateTime('tomorrow');
        $endDate = (clone $startDate)->modify('+' . $days . ' days');
        
        $currentDate = clone $startDate;
        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $slots = $this->getAvailableForDate($dateStr);
            
            if (!empty($slots)) {
                $availableDates[] = [
                    'date' => $dateStr,
                    'formatted' => Helpers::formatDateFr($dateStr),
                    'day_name' => DAYS_OF_WEEK[(int)$currentDate->format('N')],
                    'slots_count' => count($slots)
                ];
            }
            
            $currentDate->modify('+1 day');
        }
        
        return $availableDates;
    }
    
    /**
     * Récupérer les disponibilités pour un mois
     */
    public function getAvailabilityForMonth(int $year, int $month): array
    {
        $startDate = new DateTime("$year-$month-01");
        $endDate = (clone $startDate)->modify('last day of this month');
        
        $minDate = new DateTime('tomorrow');
        $maxDate = (clone $minDate)->modify('+' . BOOKING_ADVANCE_MAX_DAYS . ' days');
        
        // Ajuster les bornes
        if ($startDate < $minDate) {
            $startDate = clone $minDate;
        }
        if ($endDate > $maxDate) {
            $endDate = clone $maxDate;
        }
        
        $availableDates = [];
        $currentDate = clone $startDate;
        
        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $slots = $this->getAvailableForDate($dateStr);
            
            $availableDates[$dateStr] = [
                'available' => !empty($slots),
                'slots_count' => count($slots)
            ];
            
            $currentDate->modify('+1 day');
        }
        
        return $availableDates;
    }
    
    /**
     * Vérifier si une date est bloquée
     */
    public function isDateBlocked(string $date): bool
    {
        $stmt = $this->db->prepare("SELECT id FROM blocked_dates WHERE blocked_date = :date");
        $stmt->execute([':date' => $date]);
        return (bool) $stmt->fetch();
    }
    
    /**
     * Bloquer une date
     */
    public function blockDate(string $date, ?string $reason = null): bool
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO blocked_dates (blocked_date, reason) VALUES (:date, :reason)
            ");
            $stmt->execute([':date' => $date, ':reason' => $reason]);
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }
    
    /**
     * Débloquer une date
     */
    public function unblockDate(string $date): bool
    {
        $stmt = $this->db->prepare("DELETE FROM blocked_dates WHERE blocked_date = :date");
        $stmt->execute([':date' => $date]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Récupérer toutes les dates bloquées
     */
    public function getBlockedDates(): array
    {
        $stmt = $this->db->query("SELECT * FROM blocked_dates ORDER BY blocked_date");
        return $stmt->fetchAll();
    }
    
    /**
     * Récupérer les créneaux par jour
     */
    public function getSlotsByDay(): array
    {
        $stmt = $this->db->query("
            SELECT * FROM available_slots 
            ORDER BY day_of_week, time_start
        ");
        $slots = $stmt->fetchAll();
        
        $slotsByDay = [];
        foreach ($slots as $slot) {
            $dayNum = $slot['day_of_week'];
            if (!isset($slotsByDay[$dayNum])) {
                $slotsByDay[$dayNum] = [
                    'day_number' => $dayNum,
                    'day_name' => DAYS_OF_WEEK[$dayNum],
                    'slots' => []
                ];
            }
            $slot['label'] = Helpers::formatTimeSlot($slot['time_start'], $slot['time_end']);
            $slotsByDay[$dayNum]['slots'][] = $slot;
        }
        
        return array_values($slotsByDay);
    }
    
    /**
     * Activer/désactiver un créneau
     */
    public function toggleSlot(int $id, bool $active): bool
    {
        try {
            $stmt = $this->db->prepare("UPDATE available_slots SET is_active = :active WHERE id = :id");
            $stmt->execute([':active' => $active ? 1 : 0, ':id' => $id]);
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    // ════════════════════════════════════════════════════════════════
    //                  BOOKING v3 §4 — ALGO CRÉNEAUX
    // ════════════════════════════════════════════════════════════════
    // Les méthodes ci-dessous remplacent à terme la logique
    // pre-découpée d'`available_slots` : elles travaillent contre
    // `availability` (récurrent hebdo), `availability_exceptions`
    // (overrides par date), `services` (durée + buffer) et
    // `bookings` (intervalles occupés étendus du buffer).
    //
    // Le tunnel v2 (booking/index.php + api/slots.php) consomme
    // toujours `getAvailableForDate()` etc. — la bascule se fera au
    // checkpoint §5. Co-existence assumée.

    /**
     * Résout les fenêtres d'ouverture du jour selon le nouveau modèle.
     *   1. blocked_dates : journée fermée → [].
     *   2. availability_exceptions[date] : prime sur le récurrent.
     *      - is_available = 0 (peu importe window_*) → journée fermée.
     *      - is_available = 1 + window_* renseignées → fenêtre(s) de
     *        l'exception (plusieurs lignes possibles sur la même date).
     *   3. sinon → availability[day_of_week] actives.
     *
     * @return array<int, array{start: string, end: string}>
     */
    public function resolveDayWindows(string $date): array
    {
        if ($this->isDateBlocked($date)) {
            return [];
        }

        $stmt = $this->db->prepare(
            "SELECT is_available, window_start, window_end
               FROM availability_exceptions
              WHERE exception_date = :date
              ORDER BY window_start"
        );
        $stmt->execute([':date' => $date]);
        $exceptions = $stmt->fetchAll();

        if (!empty($exceptions)) {
            // is_available = 0 sur une exception → journée fermée
            // (peu importe les autres lignes — règle simple, choisie
            // côté admin v3).
            foreach ($exceptions as $e) {
                if ((int) $e['is_available'] === 0) {
                    return [];
                }
            }
            $windows = [];
            foreach ($exceptions as $e) {
                if ($e['window_start'] !== null && $e['window_end'] !== null) {
                    $windows[] = [
                        'start' => $e['window_start'],
                        'end'   => $e['window_end'],
                    ];
                }
            }
            return $windows;
        }

        $dayOfWeek = (int) date('N', strtotime($date));
        $stmt = $this->db->prepare(
            "SELECT window_start, window_end
               FROM availability
              WHERE day_of_week = :day AND is_active = 1
              ORDER BY window_start"
        );
        $stmt->execute([':day' => $dayOfWeek]);

        $windows = [];
        foreach ($stmt->fetchAll() as $r) {
            $windows[] = [
                'start' => $r['window_start'],
                'end'   => $r['window_end'],
            ];
        }
        return $windows;
    }

    /**
     * Bookings actifs du jour (intervalles à éviter), étendus du buffer
     * post-séance. Inclut les holds 'pending_payment' non expirés
     * (lazy expiry : un hold dont payment_expires_at est dépassé
     * NE TIENT PLUS le créneau côté lecture — cf. spec §4).
     *
     * Anciens bookings sans duration_min/buffer_after_min : on
     * déduit la durée de (slot_time_end - slot_time_start) et le
     * buffer à 0.
     *
     * @return array<int, array{start: string, end_with_buffer: string}>
     */
    public function getActiveBookingsForDate(string $date): array
    {
        $stmt = $this->db->prepare(
            "SELECT slot_time_start, slot_time_end,
                    duration_min, buffer_after_min,
                    status, payment_expires_at
               FROM bookings
              WHERE slot_date = :date
                AND status IN ('pending', 'confirmed', 'pending_payment')
              ORDER BY slot_time_start"
        );
        $stmt->execute([':date' => $date]);

        $now = date('Y-m-d H:i:s');
        $out = [];
        foreach ($stmt->fetchAll() as $b) {
            // Lazy-expiry des holds (cf. spec §4 collision sur hold
            // expiré). Le cron cron/expire-holds.php (§6) repassera
            // le statut en 'expired' de manière persistante ;
            // entre-temps, on ne considère pas la ligne ici.
            if ($b['status'] === 'pending_payment'
                && !empty($b['payment_expires_at'])
                && $b['payment_expires_at'] < $now) {
                continue;
            }

            $buffer = isset($b['buffer_after_min']) ? (int) $b['buffer_after_min'] : 0;
            $endStr = $b['slot_time_end'];
            if ($buffer > 0) {
                $endStr = self::addBufferToTime($endStr, $buffer);
            }
            $out[] = [
                'start'           => $b['slot_time_start'],
                'end_with_buffer' => $endStr,
            ];
        }
        return $out;
    }

    /**
     * Algo principal v3 : créneaux disponibles pour un (service, date).
     * Retourne une liste de `{time_start, time_end, label}` triée par
     * `time_start` croissant. Liste vide si :
     *   - service inactif / inconnu,
     *   - date hors horizon (passée ou > MAX_HORIZON_DAYS),
     *   - jour bloqué / fermé,
     *   - aucune fenêtre de la journée n'admet de candidat valide.
     */
    public function computeSlotsForService(int $serviceId, string $date): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, duration_min, buffer_after_min
               FROM services
              WHERE id = :id AND is_active = 1"
        );
        $stmt->execute([':id' => $serviceId]);
        $service = $stmt->fetch();
        if (!$service) {
            return [];
        }
        $duration = (int) $service['duration_min'];
        $buffer   = (int) $service['buffer_after_min'];

        $today   = date('Y-m-d');
        $maxDate = date('Y-m-d', strtotime('+' . MAX_HORIZON_DAYS . ' days'));
        if ($date < $today || $date > $maxDate) {
            return [];
        }

        $windows = $this->resolveDayWindows($date);
        if (empty($windows)) {
            return [];
        }
        $bookings = $this->getActiveBookingsForDate($date);

        // MIN_NOTICE_MIN : appliqué à J seulement. Sur J+N, toujours OK.
        // Si le cutoff (now + MIN_NOTICE) bascule sur le lendemain →
        // plus aucun candidat aujourd'hui.
        $earliestStart = null;
        if ($date === $today) {
            $cutoff = new \DateTime();
            $cutoff->modify('+' . MIN_NOTICE_MIN . ' minutes');
            if ($cutoff->format('Y-m-d') !== $today) {
                return [];
            }
            $earliestStart = $cutoff->format('H:i:s');
        }

        $candidates = self::computeCandidates(
            $windows,
            $bookings,
            $duration,
            $buffer,
            (int) BOOKING_STEP,
            $earliestStart
        );

        $out = [];
        foreach ($candidates as $c) {
            $out[] = [
                'time_start' => $c['time_start'],
                'time_end'   => $c['time_end'],
                'label'      => Helpers::formatTimeSlot($c['time_start'], $c['time_end']),
            ];
        }
        return $out;
    }

    /**
     * Dates qui offrent au moins un créneau pour le service, sur
     * l'horizon de réservation.
     */
    public function getServiceAvailableDates(int $serviceId, ?int $days = null): array
    {
        $days   = $days ?? (int) MAX_HORIZON_DAYS;
        $cursor = new \DateTime('today');
        $end    = (clone $cursor)->modify("+{$days} days");

        $dates = [];
        while ($cursor <= $end) {
            $d     = $cursor->format('Y-m-d');
            $slots = $this->computeSlotsForService($serviceId, $d);
            if (!empty($slots)) {
                $dates[] = [
                    'date'        => $d,
                    'formatted'   => Helpers::formatDateFr($d),
                    'day_name'    => DAYS_OF_WEEK[(int) $cursor->format('N')],
                    'slots_count' => count($slots),
                ];
            }
            $cursor->modify('+1 day');
        }
        return $dates;
    }

    /**
     * Disponibilités du mois pour le service (vue calendrier).
     */
    public function getServiceAvailabilityForMonth(int $serviceId, int $year, int $month): array
    {
        $start   = new \DateTime(sprintf('%04d-%02d-01', $year, $month));
        $end     = (clone $start)->modify('last day of this month');
        $today   = new \DateTime('today');
        $maxDate = (clone $today)->modify('+' . MAX_HORIZON_DAYS . ' days');

        if ($start < $today)  { $start = clone $today; }
        if ($end   > $maxDate){ $end   = clone $maxDate; }

        $out    = [];
        $cursor = clone $start;
        while ($cursor <= $end) {
            $d     = $cursor->format('Y-m-d');
            $slots = $this->computeSlotsForService($serviceId, $d);
            $out[$d] = [
                'available'   => !empty($slots),
                'slots_count' => count($slots),
            ];
            $cursor->modify('+1 day');
        }
        return $out;
    }

    /**
     * Algo de positionnement des candidats — FONCTION PURE,
     * testable sans BDD (smoke AD-9). Reproduit la spec §4 :
     *
     *   pour chaque fenêtre [W_start, W_end] :
     *     pour t = W_start, W_start + step, … tant que t + duration ≤ W_end :
     *       candidat occupe [t, t + duration + buffer)
     *       valide si t ≥ earliestStart (si fourni) ET
     *                 [t, t + duration + buffer) ∩ chaque booking = ∅
     *
     * Convention d'intervalle : `[start, end)` semi-ouvert (touche
     * autorisée).
     *
     * @param array<int, array{start: string, end: string}>            $windows
     * @param array<int, array{start: string, end_with_buffer: string}> $bookings
     * @return array<int, array{time_start: string, time_end: string}>
     */
    public static function computeCandidates(
        array $windows,
        array $bookings,
        int $duration,
        int $buffer,
        int $step,
        ?string $earliestStart = null
    ): array {
        if ($duration <= 0 || $step <= 0) {
            return [];
        }
        $occupyDuration = $duration + max(0, $buffer);
        $earliestMin = $earliestStart !== null ? self::timeToMinutes($earliestStart) : null;

        $candidates = [];
        foreach ($windows as $w) {
            $ws = self::timeToMinutes($w['start']);
            $we = self::timeToMinutes($w['end']);
            for ($t = $ws; $t + $duration <= $we; $t += $step) {
                if ($earliestMin !== null && $t < $earliestMin) {
                    continue;
                }
                $tEnd = $t + $occupyDuration; // [t, tEnd) — intervalle occupé

                $clash = false;
                foreach ($bookings as $b) {
                    $bs = self::timeToMinutes($b['start']);
                    $be = self::timeToMinutes($b['end_with_buffer']);
                    // chevauchement strict : intersection non vide
                    //   [t, tEnd) ∩ [bs, be) ≠ ∅  ⇔  t < be ET tEnd > bs
                    if ($t < $be && $tEnd > $bs) {
                        $clash = true;
                        break;
                    }
                }
                if ($clash) {
                    continue;
                }
                $candidates[] = [
                    'time_start' => self::minutesToTime($t),
                    'time_end'   => self::minutesToTime($t + $duration),
                ];
            }
        }
        return $candidates;
    }

    /** 'HH:MM[:SS]' → minutes depuis 00:00. */
    private static function timeToMinutes(string $t): int
    {
        $parts = explode(':', $t);
        return ((int) $parts[0]) * 60 + (int) ($parts[1] ?? 0);
    }

    /** minutes → 'HH:MM:00'. */
    private static function minutesToTime(int $m): string
    {
        $m = max(0, $m);
        return sprintf('%02d:%02d:00', intdiv($m, 60), $m % 60);
    }

    /** 'HH:MM[:SS]' + N minutes → 'HH:MM:00' (clampé à 24:00:00). */
    private static function addBufferToTime(string $time, int $bufferMin): string
    {
        $total = self::timeToMinutes($time) + max(0, $bufferMin);
        $total = min($total, 24 * 60);
        return self::minutesToTime($total);
    }
}
