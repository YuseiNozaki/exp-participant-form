<?php
/**
 * Bootstrap file for PHPUnit tests
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define root path
define('ROOT_PATH', dirname(__DIR__));

// Set test environment
define('TEST_ENV', true);

// Include autoloader (if using Composer) or require needed files
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/src/BaseModel.php';
require_once ROOT_PATH . '/src/Slot.php';
require_once ROOT_PATH . '/src/Participant.php';
require_once ROOT_PATH . '/src/Reservation.php';

// Set test database configuration
DatabaseConfig::setCredentials(
    $_ENV['DB_HOST'] ?? 'localhost',
    $_ENV['DB_NAME'] ?? 'reservation_system_test',
    $_ENV['DB_USER'] ?? 'root',
    $_ENV['DB_PASS'] ?? ''
);
?>