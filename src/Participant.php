<?php
require_once __DIR__ . '/BaseModel.php';

/**
 * Participant model for managing participant information
 */
class Participant extends BaseModel {
    protected $table = 'participants';
    
    /**
     * Find participant by email
     */
    public function findByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM participants WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }
    
    /**
     * Create or get existing participant
     */
    public function createOrGet($name, $email) {
        // Check if participant already exists
        $existing = $this->findByEmail($email);
        if ($existing) {
            // Update name if different
            if ($existing['name'] !== $name) {
                $this->update($existing['id'], ['name' => $name]);
            }
            return $existing['id'];
        }
        
        // Create new participant
        return $this->create([
            'name' => $name,
            'email' => $email
        ]);
    }
    
    /**
     * Get participant with their reservations
     */
    public function getWithReservations($participantId) {
        $stmt = $this->pdo->prepare("
            SELECT p.*, 
                   r.id as reservation_id,
                   r.reserved_at,
                   r.status as reservation_status,
                   s.date,
                   s.start_time
            FROM participants p
            LEFT JOIN reservations r ON p.id = r.participant_id
            LEFT JOIN slots s ON r.slot_id = s.id
            WHERE p.id = ?
            ORDER BY s.date ASC, s.start_time ASC
        ");
        $stmt->execute([$participantId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get all participants with their reservation count
     */
    public function getAllWithReservationCount() {
        $stmt = $this->pdo->query("
            SELECT p.*, 
                   COUNT(r.id) as reservation_count
            FROM participants p
            LEFT JOIN reservations r ON p.id = r.participant_id AND r.status = 'active'
            GROUP BY p.id
            ORDER BY p.name ASC
        ");
        return $stmt->fetchAll();
    }
}
?>