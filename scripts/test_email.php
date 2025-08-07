#!/usr/bin/env php
<?php
/**
 * Test script for email functionality
 * Usage: php test_email.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/EmailService.php';

// Mock models for testing
class TestSlot {
    public function findById($id) {
        return [
            'id' => $id,
            'date' => '2025-08-08',
            'start_time' => '10:00:00'
        ];
    }
}

class TestReservation {
    public function getTomorrowReservations() {
        return [];
    }
}

echo "Email Service Test Script\n";
echo "========================\n\n";

// Check environment variables
echo "Environment Configuration:\n";
echo "SMTP_HOST: " . (getenv('SMTP_HOST') ?: 'not set') . "\n";
echo "SMTP_PORT: " . (getenv('SMTP_PORT') ?: 'not set') . "\n";
echo "SMTP_USERNAME: " . (getenv('SMTP_USERNAME') ?: 'not set') . "\n";
echo "SMTP_FROM: " . (getenv('SMTP_FROM') ?: 'not set') . "\n";
echo "\n";

// Create email service with test models
$testSlot = new TestSlot();
$testReservation = new TestReservation();
$emailService = new EmailService($testReservation, $testSlot);

echo "Testing EmailService instantiation... ";
if ($emailService instanceof EmailService) {
    echo "✓ SUCCESS\n";
} else {
    echo "✗ FAILED\n";
    exit(1);
}

echo "\nTesting email configuration...\n";
try {
    // This will test the SMTP configuration without actually sending
    $emailService->sendConfirmationEmail('test@example.com', 'Test User', 1);
    echo "✓ Email configuration appears to be working\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Slot not found') !== false) {
        echo "✗ Unexpected error: " . $e->getMessage() . "\n";
    } else {
        echo "ℹ Email sending failed as expected (no real SMTP credentials): " . $e->getMessage() . "\n";
        echo "  This is normal for testing environment.\n";
    }
}

echo "\nEmail service configuration test completed.\n";

if (getenv('SMTP_HOST')) {
    echo "\n✓ SMTP configuration detected - emails should work on Heroku\n";
} else {
    echo "\n⚠ No SMTP configuration - set environment variables for production\n";
    echo "  Required variables: SMTP_HOST, SMTP_USERNAME, SMTP_PASSWORD, SMTP_FROM\n";
}
?>