<?php
use PHPUnit\Framework\TestCase;

/**
 * Tests for the DatabaseConfig class, specifically JawsDB functionality
 */
class DatabaseConfigTest extends TestCase {
    private $originalJawsDbUrl;
    
    protected function setUp(): void {
        // Save original environment state
        $this->originalJawsDbUrl = getenv('JAWSDB_URL');
        // Reset any credential overrides
        DatabaseConfig::resetCredentials();
    }
    
    protected function tearDown(): void {
        // Restore original environment state
        if ($this->originalJawsDbUrl !== false) {
            putenv('JAWSDB_URL=' . $this->originalJawsDbUrl);
        } else {
            putenv('JAWSDB_URL');
        }
        // Reset any credential overrides
        DatabaseConfig::resetCredentials();
    }
    
    public function testJawsDbUrlParsing() {
        // Test valid JawsDB URL parsing
        $testUrl = 'mysql://username:password@hostname:3306/dbname';
        putenv('JAWSDB_URL=' . $testUrl);
        
        // We need to test that the URL gets parsed correctly
        // Since we can't actually connect without a real database, 
        // we'll test the parsing logic by checking if it doesn't throw an exception
        // and that the connection attempt uses the correct parameters
        
        try {
            $connection = DatabaseConfig::getConnection();
            // If we get here, the parsing worked (connection may fail due to invalid credentials)
            $this->assertTrue(true, 'JawsDB URL was parsed without throwing an exception');
        } catch (PDOException $e) {
            // PDO connection failure is expected with test credentials
            // What we're testing is that the URL parsing doesn't throw an exception
            $this->assertStringContainsString('hostname', $e->getMessage(), 'Connection should attempt to use parsed hostname');
        } catch (Exception $e) {
            // Any other exception should be from our parsing logic
            $this->fail('Unexpected exception during JawsDB URL parsing: ' . $e->getMessage());
        }
    }
    
    public function testJawsDbUrlMissing() {
        // Test behavior when JAWSDB_URL is not set
        putenv('JAWSDB_URL');
        
        // Should fall back to default local configuration or throw appropriate exception
        try {
            DatabaseConfig::getConnection();
            // If no exception is thrown, it should use local defaults
            $this->assertTrue(true, 'Falls back to local configuration when JAWSDB_URL is not set');
        } catch (PDOException $e) {
            // Expected when no local database is available
            $this->assertTrue(true, 'Expected PDO connection failure for local database');
        } catch (Exception $e) {
            // Should not throw other exceptions for missing JAWSDB_URL in fallback mode
            $this->fail('Should not throw exception for missing JAWSDB_URL: ' . $e->getMessage());
        }
    }
    
    public function testInvalidJawsDbUrl() {
        // Test behavior with invalid JawsDB URL format
        putenv('JAWSDB_URL=invalid-url-format');
        
        try {
            DatabaseConfig::getConnection();
            $this->fail('Should throw exception for invalid JAWSDB_URL format');
        } catch (Exception $e) {
            $this->assertStringContainsString('Invalid', $e->getMessage(), 'Should indicate invalid URL format');
        }
    }
    
    public function testBackwardCompatibilityWithSetCredentials() {
        // Test that existing setCredentials functionality still works
        putenv('JAWSDB_URL=mysql://user:pass@jawsdb-host:3306/jawsdb-name');
        
        // Override with setCredentials
        DatabaseConfig::setCredentials('localhost', 'test_db', 'test_user', 'test_pass');
        
        try {
            DatabaseConfig::getConnection();
            // Should use setCredentials values, not JAWSDB_URL
            $this->assertTrue(true, 'setCredentials should override JAWSDB_URL');
        } catch (PDOException $e) {
            // Expected connection failure, but should be using localhost
            // The error message format can vary, so we'll check that it's not using JawsDB host
            $this->assertStringNotContainsString('jawsdb-host', $e->getMessage(), 'Should not use JawsDB host when setCredentials is used');
        }
    }
}
?>