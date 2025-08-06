<?php
/**
 * Database configuration for the reservation system
 */

class DatabaseConfig {
    private static $host;
    private static $dbname;
    private static $username;
    private static $password;
    private static $charset = 'utf8mb4';
    
    private static function loadConfig() {
        // AWS EB環境変数を優先、次にカスタム環境変数、最後にローカル設定
        self::$host = $_ENV['RDS_HOSTNAME'] ?? getenv('DB_HOST') ?? 'localhost';
        self::$dbname = $_ENV['RDS_DB_NAME'] ?? getenv('DB_NAME') ?? 'reservation_system';
        self::$username = $_ENV['RDS_USERNAME'] ?? getenv('DB_USER') ?? 'root';
        self::$password = $_ENV['RDS_PASSWORD'] ?? getenv('DB_PASS') ?? '';
    }
    
    public static function getConnection() {
        self::loadConfig();
        
        $dsn = "mysql:host=" . self::$host . ";dbname=" . self::$dbname . ";charset=" . self::$charset;
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        try {
            return new PDO($dsn, self::$username, self::$password, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
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