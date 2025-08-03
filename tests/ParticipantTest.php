<?php
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Participant model
 */
class ParticipantTest extends TestCase {
    private $participant;
    
    protected function setUp(): void {
        $this->participant = new Participant();
        $this->setupTestDatabase();
    }
    
    protected function tearDown(): void {
        $this->cleanupTestDatabase();
    }
    
    private function setupTestDatabase() {
        $pdo = DatabaseConfig::getConnection();
        
        // Create test participants table
        $pdo->exec("DROP TABLE IF EXISTS participants");
        $pdo->exec("
            CREATE TABLE participants (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        
        // Insert test data
        $pdo->exec("
            INSERT INTO participants (name, email) VALUES
            ('Test User 1', 'test1@example.com'),
            ('Test User 2', 'test2@example.com')
        ");
    }
    
    private function cleanupTestDatabase() {
        $pdo = DatabaseConfig::getConnection();
        $pdo->exec("DROP TABLE IF EXISTS participants");
    }
    
    public function testFindByEmail() {
        $participant = $this->participant->findByEmail('test1@example.com');
        
        $this->assertIsArray($participant);
        $this->assertEquals('Test User 1', $participant['name']);
        $this->assertEquals('test1@example.com', $participant['email']);
    }
    
    public function testFindByEmailNotFound() {
        $participant = $this->participant->findByEmail('nonexistent@example.com');
        
        $this->assertFalse($participant);
    }
    
    public function testCreateOrGetExisting() {
        // Test with existing user
        $participantId = $this->participant->createOrGet('Test User 1', 'test1@example.com');
        
        $this->assertIsNumeric($participantId);
        
        // Verify it's the existing user
        $participant = $this->participant->findById($participantId);
        $this->assertEquals('Test User 1', $participant['name']);
        $this->assertEquals('test1@example.com', $participant['email']);
    }
    
    public function testCreateOrGetNew() {
        // Test with new user
        $participantId = $this->participant->createOrGet('New User', 'new@example.com');
        
        $this->assertIsNumeric($participantId);
        $this->assertGreaterThan(0, $participantId);
        
        // Verify the new user was created
        $participant = $this->participant->findById($participantId);
        $this->assertEquals('New User', $participant['name']);
        $this->assertEquals('new@example.com', $participant['email']);
    }
    
    public function testCreateOrGetUpdateName() {
        // Update name for existing email
        $participantId = $this->participant->createOrGet('Updated Name', 'test1@example.com');
        
        $participant = $this->participant->findById($participantId);
        $this->assertEquals('Updated Name', $participant['name']);
        $this->assertEquals('test1@example.com', $participant['email']);
    }
    
    public function testCreateParticipant() {
        $newParticipantId = $this->participant->create([
            'name' => 'Direct Create User',
            'email' => 'direct@example.com'
        ]);
        
        $this->assertIsNumeric($newParticipantId);
        $this->assertGreaterThan(0, $newParticipantId);
        
        // Verify the participant was created
        $createdParticipant = $this->participant->findById($newParticipantId);
        $this->assertEquals('Direct Create User', $createdParticipant['name']);
        $this->assertEquals('direct@example.com', $createdParticipant['email']);
    }
    
    public function testFindAllParticipants() {
        $participants = $this->participant->findAll();
        
        $this->assertIsArray($participants);
        $this->assertGreaterThanOrEqual(2, count($participants)); // At least 2 test participants
    }
}
?>