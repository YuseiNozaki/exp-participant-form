<?php
/**
 * Database configuration for the reservation system
 */

class DatabaseConfig {
    private static $host = null;
    private static $dbname = null;
    private static $username = null;
    private static $password = null;
    private static $charset = 'utf8mb4';
    
    public static function getConnection() {
        // Load credentials from environment variables with fallbacks
        $host = self::$host ?? getenv('DB_HOST') ?: 'localhost';
        $dbname = self::$dbname ?? getenv('DB_NAME') ?: 'reservation_system';
        $username = self::$username ?? getenv('DB_USER') ?: 'root';
        $password = self::$password ?? (getenv('DB_PASSWORD') ?: getenv('DB_PASS') ?: '');
        
        $dsn = "mysql:host=" . $host . ";dbname=" . $dbname . ";charset=" . self::$charset;
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        try {
            return new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage(), (int)$e->getCode());
        }
    }
    
    public static function setCredentials($host, $dbname, $username, $password) {
        self::$host = $host;
        self::$dbname = $dbname;
        self::$username = $username;
        self::$password = $password;
    }
}
?>