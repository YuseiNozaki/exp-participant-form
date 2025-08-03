<?php
require_once __DIR__ . '/BaseModel.php';

/**
 * Slot model for managing time slots
 */
class Slot extends BaseModel {
    protected $table = 'slots';
    
    /**
     * Get available slots (public and available)
     */
    public function getAvailableSlots() {
        $stmt = $this->pdo->query("
            SELECT * FROM slots 
            WHERE is_available = 1 
            ORDER BY date ASC, start_time ASC
        ");
        return $stmt->fetchAll();
    }
    
    /**
     * Get slots with reservation count
     */
    public function getSlotsWithReservationCount() {
        $stmt = $this->pdo->query("
            SELECT s.*, 
                   COUNT(r.id) as reservation_count
            FROM slots s 
            LEFT JOIN reservations r ON s.id = r.slot_id AND r.status = 'active'
            GROUP BY s.id 
            ORDER BY s.date ASC, s.start_time ASC
        ");
        return $stmt->fetchAll();
    }
    
    /**
     * Toggle slot availability
     */
    public function toggleAvailability($id) {
        $stmt = $this->pdo->prepare("
            UPDATE slots 
            SET is_available = NOT is_available 
            WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }
    
    /**
     * Find slot by date and time
     */
    public function findByDateTime($date, $start_time) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM slots 
            WHERE date = ? AND start_time = ?
        ");
        $stmt->execute([$date, $start_time]);
        return $stmt->fetch();
    }
    
    /**
     * Get slots for a specific date
     */
    public function getSlotsByDate($date) {
        $stmt = $this->pdo->prepare("
            SELECT s.*, 
                   COUNT(r.id) as reservation_count
            FROM slots s 
            LEFT JOIN reservations r ON s.id = r.slot_id AND r.status = 'active'
            WHERE s.date = ?
            GROUP BY s.id 
            ORDER BY s.start_time ASC
        ");
        $stmt->execute([$date]);
        return $stmt->fetchAll();
    }
}
?>