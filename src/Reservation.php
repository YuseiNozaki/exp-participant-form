<?php
require_once __DIR__ . '/BaseModel.php';

/**
 * Reservation model for managing reservations
 */
class Reservation extends BaseModel {
    protected $table = 'reservations';
    
    /**
     * Create a new reservation
     */
    public function makeReservation($slotId, $participantId) {
        // Check if participant already has a reservation for this slot
        if ($this->hasActiveReservation($slotId, $participantId)) {
            throw new Exception('Participant already has an active reservation for this slot');
        }
        
        return $this->create([
            'slot_id' => $slotId,
            'participant_id' => $participantId,
            'status' => 'active'
        ]);
    }
    
    /**
     * Cancel a reservation
     */
    public function cancelReservation($reservationId) {
        return $this->update($reservationId, ['status' => 'cancelled']);
    }
    
    /**
     * Check if participant has active reservation for slot
     */
    public function hasActiveReservation($slotId, $participantId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count 
            FROM reservations 
            WHERE slot_id = ? AND participant_id = ? AND status = 'active'
        ");
        $stmt->execute([$slotId, $participantId]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }
    
    /**
     * Get reservations for a specific slot
     */
    public function getBySlot($slotId) {
        $stmt = $this->pdo->prepare("
            SELECT r.*, p.name, p.email
            FROM reservations r
            JOIN participants p ON r.participant_id = p.id
            WHERE r.slot_id = ? AND r.status = 'active'
            ORDER BY r.reserved_at ASC
        ");
        $stmt->execute([$slotId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get reservations for a specific participant
     */
    public function getByParticipant($participantId) {
        $stmt = $this->pdo->prepare("
            SELECT r.*, s.date, s.start_time
            FROM reservations r
            JOIN slots s ON r.slot_id = s.id
            WHERE r.participant_id = ? AND r.status = 'active'
            ORDER BY s.date ASC, s.start_time ASC
        ");
        $stmt->execute([$participantId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get all reservations with details
     */
    public function getAllWithDetails() {
        $stmt = $this->pdo->query("
            SELECT r.*, 
                   p.name, p.email,
                   s.date, s.start_time
            FROM reservations r
            JOIN participants p ON r.participant_id = p.id
            JOIN slots s ON r.slot_id = s.id
            WHERE r.status = 'active'
            ORDER BY s.date ASC, s.start_time ASC
        ");
        return $stmt->fetchAll();
    }
    
    /**
     * Get reservations for tomorrow (for reminder emails)
     */
    public function getTomorrowReservations() {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        
        $stmt = $this->pdo->prepare("
            SELECT r.*, 
                   p.name, p.email,
                   s.date, s.start_time
            FROM reservations r
            JOIN participants p ON r.participant_id = p.id
            JOIN slots s ON r.slot_id = s.id
            WHERE r.status = 'active' AND s.date = ?
            ORDER BY s.start_time ASC
        ");
        $stmt->execute([$tomorrow]);
        return $stmt->fetchAll();
    }
}
?>