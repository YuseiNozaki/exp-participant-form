<?php
/**
 * Database configuration for the reservation system
 */

class DatabaseConfig {
    private static $host = 'localhost';
    private static $dbname = 'reservation_system';
    private static $username = 'root';
    private static $password = '';
    private static $charset = 'utf8mb4';
    
    public static function getConnection() {
        $dsn = "mysql:host=" . self::$host . ";dbname=" . self::$dbname . ";charset=" . self::$charset;
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        try {
            return new PDO($dsn, self::$username, self::$password, $options);
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