<?php
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Slot model
 */
class SlotTest extends TestCase {
    private $slot;
    
    protected function setUp(): void {
        $this->slot = new Slot();
        $this->setupTestDatabase();
    }
    
    protected function tearDown(): void {
        $this->cleanupTestDatabase();
    }
    
    private function setupTestDatabase() {
        // Create test tables
        $pdo = DatabaseConfig::getConnection();
        
        // Create test slots table
        $pdo->exec("DROP TABLE IF EXISTS slots");
        $pdo->exec("
            CREATE TABLE slots (
                id INT PRIMARY KEY AUTO_INCREMENT,
                date DATE NOT NULL,
                start_time TIME NOT NULL,
                is_available BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_slot (date, start_time)
            )
        ");
        
        // Insert test data
        $pdo->exec("
            INSERT INTO slots (date, start_time, is_available) VALUES
            ('2025-08-08', '09:00:00', 1),
            ('2025-08-08', '10:00:00', 1),
            ('2025-08-08', '11:00:00', 0),
            ('2025-08-09', '09:00:00', 1)
        ");
    }
    
    private function cleanupTestDatabase() {
        $pdo = DatabaseConfig::getConnection();
        $pdo->exec("DROP TABLE IF EXISTS slots");
    }
    
    public function testGetAvailableSlots() {
        $availableSlots = $this->slot->getAvailableSlots();
        
        $this->assertIsArray($availableSlots);
        $this->assertCount(3, $availableSlots); // 3 available slots
        
        // Check that all returned slots are available
        foreach ($availableSlots as $slot) {
            $this->assertEquals(1, $slot['is_available']);
        }
    }
    
    public function testFindByDateTime() {
        $slot = $this->slot->findByDateTime('2025-08-08', '09:00:00');
        
        $this->assertIsArray($slot);
        $this->assertEquals('2025-08-08', $slot['date']);
        $this->assertEquals('09:00:00', $slot['start_time']);
    }
    
    public function testToggleAvailability() {
        // Find a slot
        $testSlot = $this->slot->findByDateTime('2025-08-08', '09:00:00');
        $originalStatus = $testSlot['is_available'];
        
        // Toggle availability
        $result = $this->slot->toggleAvailability($testSlot['id']);
        $this->assertTrue($result);
        
        // Check that status changed
        $updatedSlot = $this->slot->findById($testSlot['id']);
        $this->assertNotEquals($originalStatus, $updatedSlot['is_available']);
    }
    
    public function testGetSlotsByDate() {
        $slots = $this->slot->getSlotsByDate('2025-08-08');
        
        $this->assertIsArray($slots);
        $this->assertCount(3, $slots); // 3 slots for 2025-08-08
        
        // Check that all slots are for the correct date
        foreach ($slots as $slot) {
            $this->assertEquals('2025-08-08', $slot['date']);
        }
    }
    
    public function testCreateSlot() {
        $newSlotId = $this->slot->create([
            'date' => '2025-08-10',
            'start_time' => '14:00:00',
            'is_available' => 1
        ]);
        
        $this->assertIsNumeric($newSlotId);
        $this->assertGreaterThan(0, $newSlotId);
        
        // Verify the slot was created
        $createdSlot = $this->slot->findById($newSlotId);
        $this->assertEquals('2025-08-10', $createdSlot['date']);
        $this->assertEquals('14:00:00', $createdSlot['start_time']);
    }
}
?>