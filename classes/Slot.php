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
}
