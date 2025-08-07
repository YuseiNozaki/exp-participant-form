<?php
/**
 * Database configuration for the reservation system
 * Supports both local configuration and Heroku JawsDB
 */

class DatabaseConfig {
    private static $host = 'localhost';
    private static $dbname = 'reservation_system';
    private static $username = 'root';
    private static $password = '';
    private static $charset = 'utf8mb4';
    private static $port = '3306';
    private static $credentialsOverride = false;
    
    public static function getConnection() {
        // Check if credentials were explicitly set (for tests/local development)
        if (self::$credentialsOverride) {
            return self::createLocalConnection();
        }
        
        // Check for Heroku JawsDB URL
        $jawsDbUrl = getenv('JAWSDB_URL');
        if ($jawsDbUrl) {
            return self::createJawsDbConnection($jawsDbUrl);
        }
        
        // Fall back to local configuration
        return self::createLocalConnection();
    }
    
    private static function createJawsDbConnection($url) {
        // Validate URL format first
        if (!filter_var($url, FILTER_VALIDATE_URL) || substr($url, 0, 8) !== 'mysql://') {
            throw new \Exception("Invalid JAWSDB_URL format. Expected: mysql://user:pass@host:port/dbname");
        }
        
        // Parse the JawsDB URL
        $parts = parse_url($url);
        
        if (!$parts || !isset($parts['host'], $parts['user'], $parts['pass'], $parts['path'])) {
            throw new \Exception("Invalid JAWSDB_URL format. Expected: mysql://user:pass@host:port/dbname");
        }
        
        $host = $parts['host'];
        $port = isset($parts['port']) ? $parts['port'] : 3306;
        $dbname = ltrim($parts['path'], '/');
        $user = $parts['user'];
        $pass = $parts['pass'];
        
        // Validate that we have all required components
        if (empty($host) || empty($user) || empty($dbname)) {
            throw new \Exception("Invalid JAWSDB_URL format. Missing required components: host, user, or database name");
        }
        
        // Create DSN with explicit host and port
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=" . self::$charset;
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        try {
            return new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage(), (int)$e->getCode());
        }
    }
    
    private static function createLocalConnection() {
        $dsn = "mysql:host=" . self::$host . ";port=" . self::$port . ";dbname=" . self::$dbname . ";charset=" . self::$charset;
        
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
        self::$credentialsOverride = true;
    }
    
    public static function resetCredentials() {
        self::$credentialsOverride = false;
    }
}
?>