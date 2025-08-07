<?php
require_once __DIR__ . '/bootstrap.php';
require_once ROOT_PATH . '/src/EmailService.php';

use PHPUnit\Framework\TestCase;

/**
 * Mock Slot model for testing
 */
class MockSlot {
    public function findById($id) {
        if ($id == 1) {
            return [
                'id' => 1,
                'date' => '2025-08-08',
                'start_time' => '10:00:00'
            ];
        }
        return null;
    }
}

/**
 * Mock Reservation model for testing
 */
class MockReservation {
    public function getTomorrowReservations() {
        return [
            [
                'email' => 'test@test.com',
                'name' => 'Test User',
                'date' => '2025-08-08',
                'start_time' => '10:00:00'
            ]
        ];
    }
}

/**
 * Test EmailService functionality
 */
class EmailServiceTest extends TestCase {
    
    public function testEmailServiceCreation() {
        // Test that EmailService can be instantiated with mock objects
        $mockReservation = new MockReservation();
        $mockSlot = new MockSlot();
        $emailService = new EmailService($mockReservation, $mockSlot);
        $this->assertInstanceOf(EmailService::class, $emailService);
    }
    
    public function testSMTPConfigurationFromEnvironment() {
        // Set up test environment variables
        putenv('SMTP_HOST=smtp.test.com');
        putenv('SMTP_PORT=587');
        putenv('SMTP_USERNAME=test@test.com');
        putenv('SMTP_PASSWORD=testpass');
        putenv('SMTP_FROM=test@test.com');
        
        // Create email service with mock objects
        $mockReservation = new MockReservation();
        $mockSlot = new MockSlot();
        $emailService = new EmailService($mockReservation, $mockSlot);
        
        // Verify it can be created without errors
        $this->assertInstanceOf(EmailService::class, $emailService);
        
        // Clean up environment
        putenv('SMTP_HOST=');
        putenv('SMTP_PORT=');
        putenv('SMTP_USERNAME=');
        putenv('SMTP_PASSWORD=');
        putenv('SMTP_FROM=');
    }
    
    public function testConfirmationEmailWithInvalidSlot() {
        // Test that email service handles missing slots gracefully
        $mockReservation = new MockReservation();
        $mockSlot = new MockSlot();
        $emailService = new EmailService($mockReservation, $mockSlot);
        
        // Test with invalid slot ID
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Slot not found');
        
        $emailService->sendConfirmationEmail('test@test.com', 'Test User', 999);
    }
    
    public function testConfirmationEmailWithValidSlot() {
        // Test that email service can process valid slot data
        $mockReservation = new MockReservation();
        $mockSlot = new MockSlot();
        $emailService = new EmailService($mockReservation, $mockSlot);
        
        // We can't test actual email sending without SMTP configuration
        // But we can test that the method processes the slot data correctly
        try {
            $result = $emailService->sendConfirmationEmail('test@test.com', 'Test User', 1);
            // If we get here without exception, the slot was found and processed
            $this->assertTrue(true);
        } catch (Exception $e) {
            // Expected to fail due to mail sending, but not due to slot not found
            $this->assertStringNotContainsString('Slot not found', $e->getMessage());
        }
    }
    
    public function testReminderEmailMethod() {
        // Test reminder emails functionality
        $mockReservation = new MockReservation();
        $mockSlot = new MockSlot();
        $emailService = new EmailService($mockReservation, $mockSlot);
        
        // Test that the method exists and can be called
        $result = $emailService->sendReminderEmails();
        
        // Should return an array with sent, errors, and total counts
        $this->assertIsArray($result);
        $this->assertArrayHasKey('sent', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('total', $result);
        
        // Since we have one mock reservation, total should be 1
        $this->assertEquals(1, $result['total']);
    }
}
?>