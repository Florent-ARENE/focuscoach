<?php
/**
 * ============================================
 * CLASSE BOOKING
 * ============================================
 * Gestion des réservations
 */

namespace App;

use PDO;

class Booking
{
    private PDO $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Créer une nouvelle réservation (v3 — service catalogué)
     *
     * Mode v3 obligatoire depuis la purge legacy 2.6.0 :
     * `$data['service_id']` (FK services) + `duration_min` +
     * `buffer_after_min` figés à la création (indépendants d'une
     * édition ultérieure du catalogue). status = 'pending' tant que
     * §6 n'a pas branché Stripe — `pending_payment` viendra alors.
     */
    public function create(array $data): array
    {
        return $this->insertWithIntegrity($data, 'pending', 'none', 0);
    }

    /**
     * Crée un HOLD `pending_payment` (réservation provisoire pendant le tunnel
     * Stripe). Le créneau est tenu `payment_expires_at = NOW() + $holdMinutes`,
     * et libéré automatiquement à expiration (lazy-expiry / cron). Même garde
     * d'intégrité transactionnelle que create().
     */
    public function createHold(array $data, int $holdMinutes = 15): array
    {
        return $this->insertWithIntegrity($data, 'pending_payment', 'pending', $holdMinutes);
    }

    /**
     * Insertion d'une réservation avec garde d'intégrité TRANSACTIONNELLE.
     *
     * L'`active_key UNIQUE` ne protège QUE contre le même `slot_time_start`.
     * Pour les durées variables qui se chevauchent à start différent (ex.
     * 10:00–11:00 puis 10:30–11:30), il faut une vérif d'intervalle sous
     * verrou. Convention IDENTIQUE à Slot::computeCandidates : chaque
     * réservation occupe [start, end + buffer) (semi-ouvert) ; deux
     * réservations entrent en conflit si leurs intervalles se chevauchent.
     * Les holds `pending_payment` échus (payment_expires_at < NOW()) sont
     * traités comme LIBRES (lazy-expiry).
     *
     * Collision `active_key` (23000) sur un hold échu de MÊME start : la
     * colonne générée ne peut pas tester NOW(), donc l'INSERT lève 23000
     * tant que le hold n'est pas passé `expired`. On le re-qualifie alors et
     * on réessaie une fois.
     */
    private function insertWithIntegrity(array $data, string $status, string $paymentStatus, int $holdMinutes): array
    {
        if (empty($data['service_id']) || (int) $data['service_id'] <= 0) {
            return $this->bookingResult(false, 'Prestation requise (service_id manquant).');
        }

        $manageToken = $this->generateManageToken();
        $holdMinutes = max(0, $holdMinutes);
        $expiresExpr = $holdMinutes > 0
            ? 'DATE_ADD(NOW(), INTERVAL ' . (int) $holdMinutes . ' MINUTE)'
            : 'NULL';

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $this->db->beginTransaction();

                // 1. Verrouille les réservations actives du jour.
                $lock = $this->db->prepare(
                    "SELECT slot_time_start, slot_time_end, buffer_after_min, status, payment_expires_at
                       FROM bookings
                      WHERE slot_date = :date
                        AND status IN ('pending', 'confirmed', 'pending_payment')
                      FOR UPDATE"
                );
                $lock->execute([':date' => $data['slot_date']]);

                // 2. Vérif de chevauchement (lazy-expiry sur les holds échus).
                if ($this->overlapsActive($lock->fetchAll(), $data)) {
                    $this->db->rollBack();
                    return $this->bookingResult(false, 'Ce créneau vient d\'être réservé. Veuillez en choisir un autre.');
                }

                // 3. INSERT (le slot reste tenu jusqu'au COMMIT).
                $stmt = $this->db->prepare(
                    "INSERT INTO bookings (
                        visitor_name, visitor_email, visitor_phone, visitor_organization,
                        slot_date, slot_time_start, slot_time_end,
                        service_id, duration_min, buffer_after_min,
                        subject, message,
                        status, payment_status, payment_expires_at,
                        manage_token, ip_address, user_agent
                    ) VALUES (
                        :name, :email, :phone, :organization,
                        :date, :time_start, :time_end,
                        :service_id, :duration_min, :buffer_after_min,
                        :subject, :message,
                        :status, :pstatus, $expiresExpr,
                        :token, :ip, :ua
                    )"
                );
                $stmt->execute([
                    ':name'             => $data['visitor_name'],
                    ':email'            => $data['visitor_email'],
                    ':phone'            => $data['visitor_phone']        ?? null,
                    ':organization'     => $data['visitor_organization'] ?? null,
                    ':date'             => $data['slot_date'],
                    ':time_start'       => $data['slot_time_start'],
                    ':time_end'         => $data['slot_time_end'],
                    ':service_id'       => (int) $data['service_id'],
                    ':duration_min'     => (int) ($data['duration_min']     ?? 0),
                    ':buffer_after_min' => (int) ($data['buffer_after_min'] ?? 0),
                    ':subject'          => $data['subject'] ?? null,
                    ':message'          => $data['message'] ?? null,
                    ':status'           => $status,
                    ':pstatus'          => $paymentStatus,
                    ':token'            => $manageToken,
                    ':ip'               => $_SERVER['REMOTE_ADDR']      ?? null,
                    ':ua'               => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ]);

                $bookingId = (int) $this->db->lastInsertId();
                $this->db->commit();

                return [
                    'success'      => true,
                    'message'      => 'Votre demande de rendez-vous a été enregistrée.',
                    'booking_id'   => $bookingId,
                    'manage_token' => $manageToken,
                ];

            } catch (\PDOException $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                if ($e->getCode() === '23000') {
                    // Collision active_key (même start). Hold échu → re-qualifier + réessayer une fois.
                    if ($attempt === 1
                        && $this->expireStaleHoldAt($data['slot_date'], $data['slot_time_start'])) {
                        continue;
                    }
                    return $this->bookingResult(false, 'Ce créneau vient d\'être réservé. Veuillez en choisir un autre.');
                }
                $msg = APP_DEBUG ? 'Erreur : ' . $e->getMessage() : 'Une erreur est survenue.';
                return $this->bookingResult(false, $msg);
            }
        }

        return $this->bookingResult(false, 'Ce créneau vient d\'être réservé. Veuillez en choisir un autre.');
    }

    /**
     * Le nouveau créneau chevauche-t-il une réservation active verrouillée ?
     * Convention semi-ouverte [start, end+buffer) des deux côtés (= Slot).
     * Ignore les holds `pending_payment` échus (lazy-expiry).
     *
     * @param array<int, array<string,mixed>> $activeRows lignes verrouillées
     */
    private function overlapsActive(array $activeRows, array $data): bool
    {
        $now       = date('Y-m-d H:i:s');
        $newStart  = Slot::timeToMinutes((string) $data['slot_time_start']);
        $newEndBuf = Slot::timeToMinutes((string) $data['slot_time_end'])
                   + (int) ($data['buffer_after_min'] ?? 0);

        foreach ($activeRows as $b) {
            if ($b['status'] === 'pending_payment'
                && !empty($b['payment_expires_at'])
                && $b['payment_expires_at'] < $now) {
                continue; // hold échu = libre
            }
            $exStart  = Slot::timeToMinutes((string) $b['slot_time_start']);
            $exEndBuf = Slot::timeToMinutes((string) $b['slot_time_end'])
                      + (int) $b['buffer_after_min'];
            if ($newStart < $exEndBuf && $exStart < $newEndBuf) {
                return true;
            }
        }
        return false;
    }

    /**
     * Passe en `expired` un hold `pending_payment` ÉCHU de ce créneau (même
     * start) pour libérer son active_key. Retourne true si une ligne a été
     * re-qualifiée (→ il vaut la peine de réessayer l'INSERT).
     */
    private function expireStaleHoldAt(string $date, string $timeStart): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE bookings SET status = 'expired'
              WHERE slot_date = :date AND slot_time_start = :start
                AND status = 'pending_payment'
                AND payment_expires_at IS NOT NULL
                AND payment_expires_at < NOW()"
        );
        $stmt->execute([':date' => $date, ':start' => $timeStart]);
        return $stmt->rowCount() > 0;
    }

    /** Résultat normalisé d'une tentative de création. */
    private function bookingResult(bool $success, string $message): array
    {
        return [
            'success'      => $success,
            'message'      => $message,
            'booking_id'   => null,
            'manage_token' => null,
        ];
    }

    /**
     * Générer un token unique pour la gestion client
     */
    private function generateManageToken(): string
    {
        return bin2hex(random_bytes(32)); // 64 caractères hex
    }
    
    /**
     * Récupérer une réservation par token
     */
    public function getByToken(string $token): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT b.*, s.name AS service_name, s.slug AS service_slug
               FROM bookings b
               LEFT JOIN services s ON s.id = b.service_id
              WHERE b.manage_token = :token"
        );
        $stmt->execute([':token' => $token]);
        $booking = $stmt->fetch();

        if ($booking) {
            $booking = $this->enrichBooking($booking);
        }

        return $booking ?: null;
    }
    
    /**
     * Déplacement demandé par le client (remet en pending pour validation)
     */
    public function clientReschedule(string $token, string $newDate, string $newTimeStart, string $newTimeEnd): array
    {
        // Récupérer la réservation
        $booking = $this->getByToken($token);
        if (!$booking) {
            return ['success' => false, 'message' => 'Réservation non trouvée'];
        }
        
        // Vérifier que la réservation peut être modifiée (pas annulée ni terminée)
        if (!in_array($booking['status'], ['pending', 'confirmed'])) {
            return ['success' => false, 'message' => 'Cette réservation ne peut plus être modifiée'];
        }

        // Idempotence : double POST / re-clic → on évite de repasser en pending
        // et de spammer un mail « demande de modification » pour rien.
        if ($booking['slot_date'] === $newDate
            && substr($booking['slot_time_start'], 0, 5) === substr($newTimeStart, 0, 5)
            && substr($booking['slot_time_end'], 0, 5)   === substr($newTimeEnd, 0, 5)) {
            return [
                'success'   => true,
                'unchanged' => true,
                'message'   => 'Aucun changement de créneau.',
                'booking_id'=> $booking['id'],
            ];
        }

        // Vérifier que le nouveau créneau est disponible
        if (!$this->isSlotAvailable($newDate, $newTimeStart, $booking['id'])) {
            return ['success' => false, 'message' => 'Ce créneau n\'est plus disponible'];
        }
        
        // Vérifier que la date est dans le futur
        $newDateTime = new \DateTime("$newDate $newTimeStart");
        if ($newDateTime < new \DateTime()) {
            return ['success' => false, 'message' => 'Impossible de choisir une date passée'];
        }
        
        try {
            // Sauvegarder l'ancien créneau pour l'email
            $oldDate = $booking['slot_date'];
            $oldTimeStart = $booking['slot_time_start'];
            $oldTimeEnd = $booking['slot_time_end'];
            $wasConfirmed = ($booking['status'] === 'confirmed');
            
            // Mettre à jour avec le nouveau créneau et repasser en pending
            $stmt = $this->db->prepare("
                UPDATE bookings 
                SET slot_date = :date, 
                    slot_time_start = :time_start, 
                    slot_time_end = :time_end,
                    status = 'pending',
                    confirmed_at = NULL,
                    updated_at = NOW()
                WHERE manage_token = :token
            ");
            $stmt->execute([
                ':date' => $newDate,
                ':time_start' => $newTimeStart,
                ':time_end' => $newTimeEnd,
                ':token' => $token
            ]);
            
            return [
                'success' => true, 
                'message' => 'Votre demande de modification a été enregistrée. Elle est en attente de validation.',
                'old_date' => $oldDate,
                'old_time_start' => $oldTimeStart,
                'old_time_end' => $oldTimeEnd,
                'new_date' => $newDate,
                'new_time_start' => $newTimeStart,
                'new_time_end' => $newTimeEnd,
                'was_confirmed' => $wasConfirmed,
                'booking_id' => $booking['id']
            ];
            
        } catch (\PDOException $e) {
            return ['success' => false, 'message' => 'Erreur lors de la modification'];
        }
    }
    
    /**
     * Annulation par le client
     */
    public function clientCancel(string $token): array
    {
        // Récupérer la réservation
        $booking = $this->getByToken($token);
        if (!$booking) {
            return ['success' => false, 'message' => 'Réservation non trouvée'];
        }
        
        // Vérifier que la réservation peut être annulée
        if (!in_array($booking['status'], ['pending', 'confirmed'])) {
            return ['success' => false, 'message' => 'Cette réservation ne peut plus être annulée'];
        }
        
        try {
            $stmt = $this->db->prepare("
                UPDATE bookings 
                SET status = 'cancelled',
                    cancelled_at = NOW(),
                    updated_at = NOW()
                WHERE manage_token = :token
            ");
            $stmt->execute([':token' => $token]);
            
            return [
                'success' => true, 
                'message' => 'Votre rendez-vous a été annulé.',
                'booking_id' => $booking['id'],
                'booking' => $booking
            ];
            
        } catch (\PDOException $e) {
            return ['success' => false, 'message' => 'Erreur lors de l\'annulation'];
        }
    }
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT b.*, s.name AS service_name, s.slug AS service_slug
               FROM bookings b
               LEFT JOIN services s ON s.id = b.service_id
              WHERE b.id = :id"
        );
        $stmt->execute([':id' => $id]);
        $booking = $stmt->fetch();

        if ($booking) {
            $booking = $this->enrichBooking($booking);
        }

        return $booking ?: null;
    }
    
    /**
     * Récupérer toutes les réservations avec filtres
     */
    public function getAll(array $filters = []): array
    {
        $where = [];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = 'b.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'b.slot_date >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'b.slot_date <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }
        
        $sql = "SELECT b.*, s.name AS service_name, s.slug AS service_slug
                  FROM bookings b
                  LEFT JOIN services s ON s.id = b.service_id";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY b.slot_date ASC, b.slot_time_start ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $bookings = $stmt->fetchAll();
        
        // Enrichir chaque réservation
        foreach ($bookings as &$booking) {
            $booking = $this->enrichBooking($booking);
        }
        
        return $bookings;
    }
    
    /**
     * Mettre à jour le statut d'une réservation
     */
    public function updateStatus(int $id, string $newStatus, ?string $adminNotes = null): array
    {
        $validStatuses = array_keys(BOOKING_STATUS);
        if (!in_array($newStatus, $validStatuses)) {
            return ['success' => false, 'message' => 'Statut invalide'];
        }
        
        try {
            $sets = ['status = :status', 'updated_at = NOW()'];
            $params = [':status' => $newStatus, ':id' => $id];
            
            if ($newStatus === 'confirmed') {
                $sets[] = 'confirmed_at = NOW()';
            } elseif ($newStatus === 'cancelled') {
                $sets[] = 'cancelled_at = NOW()';
            }
            
            if ($adminNotes !== null) {
                $sets[] = 'admin_notes = :notes';
                $params[':notes'] = $adminNotes;
            }
            
            $sql = "UPDATE bookings SET " . implode(', ', $sets) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return ['success' => true, 'message' => 'Statut mis à jour'];
            
        } catch (\PDOException $e) {
            return ['success' => false, 'message' => 'Erreur lors de la mise à jour'];
        }
    }
    
    /**
     * Mettre à jour l'ID Google Event
     */
    public function updateGoogleEventId(int $id, string $eventId): bool
    {
        try {
            $settings = new Settings();
            $calendarId = $settings->getGoogleCalendarId();
            
            $stmt = $this->db->prepare("
                UPDATE bookings 
                SET google_event_id = :event_id, google_calendar_id = :calendar_id 
                WHERE id = :id
            ");
            $stmt->execute([
                ':event_id' => $eventId,
                ':calendar_id' => $calendarId,
                ':id' => $id
            ]);
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }
    
    /**
     * Supprimer une réservation
     */
    public function delete(int $id): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM bookings WHERE id = :id");
            $stmt->execute([':id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            return false;
        }
    }
    
    /**
     * Vérifier si un créneau est déjà pris
     */
    public function isSlotTaken(string $date, string $timeStart): bool
    {
        $stmt = $this->db->prepare("
            SELECT id FROM bookings
            WHERE slot_date = :date
            AND slot_time_start = :time
            AND status IN ('pending', 'confirmed', 'pending_payment')
            AND NOT (status = 'pending_payment'
                     AND payment_expires_at IS NOT NULL
                     AND payment_expires_at < NOW())
        ");
        $stmt->execute([':date' => $date, ':time' => $timeStart]);
        return (bool) $stmt->fetch();
    }
    
    /**
     * Vérifier si un créneau est disponible (en excluant une réservation)
     */
    public function isSlotAvailable(string $date, string $timeStart, ?int $excludeId = null): bool
    {
        $sql = "
            SELECT id FROM bookings
            WHERE slot_date = :date
            AND slot_time_start = :time
            AND status IN ('pending', 'confirmed', 'pending_payment')
            AND NOT (status = 'pending_payment'
                     AND payment_expires_at IS NOT NULL
                     AND payment_expires_at < NOW())
        ";
        $params = [':date' => $date, ':time' => $timeStart];
        
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return !$stmt->fetch(); // Disponible si pas de résultat
    }
    
    /**
     * Déplacer une réservation vers un nouveau créneau
     */
    public function reschedule(int $id, string $newDate, string $newTimeStart, string $newTimeEnd): array
    {
        // Vérifier que la réservation existe
        $booking = $this->getById($id);
        if (!$booking) {
            return ['success' => false, 'message' => 'Réservation non trouvée'];
        }

        // Idempotence : si rien ne change (double POST, re-clic), on sort
        // sans toucher la BDD ni envoyer d'emails. Le drapeau `unchanged`
        // permet à l'API de skipper les notifs et la sync Google Calendar.
        if ($booking['slot_date'] === $newDate
            && substr($booking['slot_time_start'], 0, 5) === substr($newTimeStart, 0, 5)
            && substr($booking['slot_time_end'], 0, 5)   === substr($newTimeEnd, 0, 5)) {
            return [
                'success'   => true,
                'unchanged' => true,
                'message'   => 'Aucun changement de créneau.',
                'old_date'  => $booking['slot_date'],
                'old_time'  => $booking['slot_time_start'],
                'new_date'  => $newDate,
                'new_time'  => $newTimeStart,
            ];
        }

        // Vérifier que le nouveau créneau est disponible
        if (!$this->isSlotAvailable($newDate, $newTimeStart, $id)) {
            return ['success' => false, 'message' => 'Ce créneau est déjà pris'];
        }
        
        // Vérifier que la date est dans le futur
        $newDateTime = new \DateTime("$newDate $newTimeStart");
        if ($newDateTime < new \DateTime()) {
            return ['success' => false, 'message' => 'Impossible de déplacer vers une date passée'];
        }
        
        try {
            $stmt = $this->db->prepare("
                UPDATE bookings 
                SET slot_date = :date, 
                    slot_time_start = :time_start, 
                    slot_time_end = :time_end,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':date' => $newDate,
                ':time_start' => $newTimeStart,
                ':time_end' => $newTimeEnd,
                ':id' => $id
            ]);
            
            return [
                'success' => true, 
                'message' => 'Réservation déplacée avec succès',
                'old_date' => $booking['slot_date'],
                'old_time' => $booking['slot_time_start'],
                'new_date' => $newDate,
                'new_time' => $newTimeStart
            ];
            
        } catch (\PDOException $e) {
            return ['success' => false, 'message' => 'Erreur lors du déplacement'];
        }
    }
    
    /**
     * Obtenir les statistiques (admin)
     */
    public function getStats(): array
    {
        return [
            'pending' => $this->countByStatus('pending'),
            'confirmed' => $this->countByStatus('confirmed'),
            'today' => $this->countToday()
        ];
    }
    
    /**
     * Obtenir les statistiques publiques (visiteurs)
     */
    public function getPublicStats(): array
    {
        // RDV confirmés cette semaine
        $stmt = $this->db->query("
            SELECT COUNT(*) FROM bookings 
            WHERE status = 'confirmed' 
            AND slot_date >= CURDATE() 
            AND slot_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ");
        $thisWeek = (int) $stmt->fetchColumn();
        
        // RDV en attente de validation
        $pending = $this->countByStatus('pending');
        
        // RDV confirmés ce mois
        $stmt = $this->db->query("
            SELECT COUNT(*) FROM bookings 
            WHERE status = 'confirmed' 
            AND MONTH(slot_date) = MONTH(CURDATE())
            AND YEAR(slot_date) = YEAR(CURDATE())
        ");
        $thisMonth = (int) $stmt->fetchColumn();
        
        return [
            'confirmed_this_week' => $thisWeek,
            'pending' => $pending,
            'confirmed_this_month' => $thisMonth
        ];
    }
    
    /**
     * Obtenir les réservations groupées par date pour une période
     * Retourne pour chaque date le nombre de pending et confirmed
     */
    public function getBookingsByDateRange(string $startDate, string $endDate): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                slot_date,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed
            FROM bookings 
            WHERE slot_date >= :start_date 
            AND slot_date <= :end_date
            AND status IN ('pending', 'confirmed')
            GROUP BY slot_date
        ");
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        
        $results = [];
        while ($row = $stmt->fetch()) {
            $results[$row['slot_date']] = [
                'pending' => (int) $row['pending'],
                'confirmed' => (int) $row['confirmed']
            ];
        }
        
        return $results;
    }
    
    /**
     * Compter par statut
     */
    private function countByStatus(string $status): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM bookings WHERE status = :status");
        $stmt->execute([':status' => $status]);
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Compter les RDV confirmés aujourd'hui
     */
    private function countToday(): int
    {
        $stmt = $this->db->query("
            SELECT COUNT(*) FROM bookings 
            WHERE slot_date = CURDATE() AND status = 'confirmed'
        ");
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Enrichir les données d'une réservation
     */
    private function enrichBooking(array $booking): array
    {
        $booking['formatted_date'] = Helpers::formatDateFr($booking['slot_date']);
        $booking['formatted_time'] = Helpers::formatTimeSlot($booking['slot_time_start'], $booking['slot_time_end']);
        $booking['status_info']    = BOOKING_STATUS[$booking['status']] ?? null;
        // `service_name` est posé par les SELECT (LEFT JOIN services) ;
        // fallback explicite quand la jointure ne ramène rien
        // (ex. booking créé via tests anciens, service supprimé).
        if (empty($booking['service_name'])) {
            $booking['service_name'] = 'Prestation';
        }
        return $booking;
    }
}
