<?php
/**
 * ============================================
 * CLASSE SLOT
 * ============================================
 * Calcul des créneaux disponibles (Booking v3).
 * Depuis la purge legacy 2.6.0, ne consomme plus `available_slots`
 * (table droppée) — uniquement `availability` (récurrent hebdo) +
 * `availability_exceptions` (overrides par date) + `services`
 * (durée + buffer) + `bookings` (intervalles occupés étendus du
 * buffer).
 */

namespace App;

use PDO;

class Slot
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ════════════════════════════════════════════════════════════════
    //                   DATES BLOQUÉES (congés, exceptions binaires)
    // ════════════════════════════════════════════════════════════════
    // Conservé tel quel — l'admin v3 (§8) utilisera toujours cette
    // table pour les fermetures complètes (jour OFF, vacances).

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
            $stmt = $this->db->prepare(
                "INSERT INTO blocked_dates (blocked_date, reason) VALUES (:date, :reason)"
            );
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

    // ════════════════════════════════════════════════════════════════
    //               BOOKING v3 §4 — ALGO CRÉNEAUX
    // ════════════════════════════════════════════════════════════════
    // Méthodes calculant les créneaux contre le nouveau modèle :
    //   - availability (récurrent hebdo en fenêtres),
    //   - availability_exceptions (overrides par date),
    //   - services (durée + buffer),
    //   - bookings (intervalles occupés étendus du buffer).
    //
    // Avant la purge 2.6.0, `getAvailableForDate`,
    // `getAvailableDates`, `getAvailabilityForMonth`,
    // `getSlotsByDay`, `toggleSlot` cohabitaient ici en mode legacy
    // contre `available_slots`. Ces méthodes ont été supprimées avec
    // le tunnel v2 (`booking/index.php`, `api/slots.php`,
    // `api/booking.php`) et la table `available_slots` (DROP TABLE
    // dans `sql/migration-3.0.0.sql`).

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
     * Liste les fenêtres d'ouverture récurrentes hebdomadaires
     * groupées par jour de la semaine. Sert au back-office (§8 à
     * venir : CRUD availability) pour afficher le planning courant.
     *
     * @return array<int, array{day_number:int, day_name:string,
     *                          windows: array<int, array<string, mixed>>}>
     */
    public function getAvailabilityByDay(): array
    {
        $stmt = $this->db->query(
            "SELECT id, day_of_week, window_start, window_end, is_active
               FROM availability
              ORDER BY day_of_week, window_start"
        );
        $windows = $stmt->fetchAll();

        $byDay = [];
        foreach ($windows as $w) {
            $d = (int) $w['day_of_week'];
            if (!isset($byDay[$d])) {
                $byDay[$d] = [
                    'day_number' => $d,
                    'day_name'   => DAYS_OF_WEEK[$d] ?? (string) $d,
                    'windows'    => [],
                ];
            }
            $w['label'] = Helpers::formatTimeSlot($w['window_start'], $w['window_end']);
            $byDay[$d]['windows'][] = $w;
        }
        return array_values($byDay);
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

    /** 'HH:MM[:SS]' → minutes depuis 00:00. Public : réutilisé par Booking
     *  pour la vérif transactionnelle de chevauchement (même convention). */
    public static function timeToMinutes(string $t): int
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
