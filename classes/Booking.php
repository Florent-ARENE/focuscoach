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
     * Créer une nouvelle réservation
     */
    public function create(array $data): array
    {
        // Vérifier que le créneau est disponible
        if ($this->isSlotTaken($data['slot_date'], $data['slot_time_start'])) {
            return [
                'success' => false,
                'message' => 'Ce créneau vient d\'être réservé. Veuillez en choisir un autre.',
                'booking_id' => null,
                'manage_token' => null
            ];
        }
        
        // Générer un token unique pour la gestion client
        $manageToken = $this->generateManageToken();
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO bookings (
                    visitor_name, visitor_email, visitor_phone, visitor_organization,
                    slot_date, slot_time_start, slot_time_end,
                    service_type, subject, message,
                    status, manage_token, ip_address, user_agent
                ) VALUES (
                    :name, :email, :phone, :organization,
                    :date, :time_start, :time_end,
                    :service, :subject, :message,
                    'pending', :token, :ip, :ua
                )
            ");
            
            $stmt->execute([
                ':name' => $data['visitor_name'],
                ':email' => $data['visitor_email'],
                ':phone' => $data['visitor_phone'] ?? null,
                ':organization' => $data['visitor_organization'] ?? null,
                ':date' => $data['slot_date'],
                ':time_start' => $data['slot_time_start'],
                ':time_end' => $data['slot_time_end'],
                ':service' => $data['service_type'] ?? 'autre',
                ':subject' => $data['subject'] ?? null,
                ':message' => $data['message'] ?? null,
                ':token' => $manageToken,
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            $bookingId = (int) $this->db->lastInsertId();
            
            return [
                'success' => true,
                'message' => 'Votre demande de rendez-vous a été enregistrée.',
                'booking_id' => $bookingId,
                'manage_token' => $manageToken
            ];
            
        } catch (\PDOException $e) {
            if (APP_DEBUG) {
                return ['success' => false, 'message' => 'Erreur : ' . $e->getMessage(), 'booking_id' => null, 'manage_token' => null];
            }
            return ['success' => false, 'message' => 'Une erreur est survenue.', 'booking_id' => null, 'manage_token' => null];
        }
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
        $stmt = $this->db->prepare("SELECT * FROM bookings WHERE manage_token = :token");
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
        $stmt = $this->db->prepare("SELECT * FROM bookings WHERE id = :id");
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
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = 'slot_date >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = 'slot_date <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }
        
        $sql = "SELECT * FROM bookings";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY slot_date ASC, slot_time_start ASC";
        
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
            AND status IN ('pending', 'confirmed')
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
            AND status IN ('pending', 'confirmed')
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
        $booking['status_info'] = BOOKING_STATUS[$booking['status']] ?? null;
        $booking['service_label'] = SERVICE_TYPES[$booking['service_type']] ?? $booking['service_type'];
        return $booking;
    }
}
