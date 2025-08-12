<?php
// Database Connection Class with Singleton Pattern

class Database {
    private static $instance = null;
    private $connection;
    private $host;
    private $dbname;
    private $username;
    private $password;
    
    private function __construct() {
        // Get database credentials from multiple sources
        $this->host = $this->getDbConfig('DB_HOST', 'localhost');
        $this->dbname = $this->getDbConfig('DB_NAME', 'ezizov04_alumpro');
        $this->username = $this->getDbConfig('DB_USER', 'ezizov04_db');
        $this->password = $this->getDbConfig('DB_PASS', 'ezizovs074');
        
        $this->connect();
    }
    
    /**
     * Get database configuration from various sources
     */
    private function getDbConfig($key, $default = '') {
        // Try environment variable first
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        
        // Try PHP constant
        if (defined($key)) {
            return constant($key);
        }
        
        // Try external config file
        $config_file = __DIR__ . '/database_config.php';
        if (file_exists($config_file)) {
            include_once $config_file;
            if (defined($key)) {
                return constant($key);
            }
        }
        
        return $default;
    }
    
    /**
     * Establish database connection
     */
    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                PDO::ATTR_TIMEOUT => 30,
                PDO::ATTR_PERSISTENT => false
            ];
            
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
            
            // Set timezone to Baku
            $this->connection->exec("SET time_zone = '+04:00'");
            
        } catch (PDOException $e) {
            $error_message = "Database connection failed: " . $e->getMessage();
            error_log($error_message);
            
            // Show user-friendly error in production
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
                throw new Exception("Database connection failed. Please try again later.");
            } else {
                throw new Exception($error_message);
            }
        }
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get PDO connection
     */
    public function getConnection() {
        // Check if connection is still alive
        if ($this->connection === null) {
            $this->connect();
        }
        
        try {
            $this->connection->query('SELECT 1');
        } catch (PDOException $e) {
            // Reconnect if connection is lost
            $this->connect();
        }
        
        return $this->connection;
    }
    
    /**
     * Execute query with parameters
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $error_message = "Database query failed: " . $e->getMessage() . " SQL: " . $sql;
            error_log($error_message);
            
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
                throw new Exception("Database operation failed. Please try again.");
            } else {
                throw new Exception($error_message);
            }
        }
    }
    
    /**
     * Get last insert ID
     */
    public function lastInsertId() {
        return $this->getConnection()->lastInsertId();
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->getConnection()->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->getConnection()->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->getConnection()->rollback();
    }
    
    /**
     * Check if in transaction
     */
    public function inTransaction() {
        return $this->getConnection()->inTransaction();
    }
    
    /**
     * Execute multiple queries (for imports)
     */
    public function multiQuery($sql) {
        try {
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $this->getConnection()->exec($statement);
                }
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("Multi-query failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get table columns info
     */
    public function getTableColumns($table_name) {
        try {
            $stmt = $this->query("DESCRIBE `$table_name`");
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Check if table exists
     */
    public function tableExists($table_name) {
        try {
            $stmt = $this->query("SHOW TABLES LIKE ?", [$table_name]);
            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get database info
     */
    public function getDatabaseInfo() {
        try {
            $info = [];
            
            // MySQL version
            $stmt = $this->query("SELECT VERSION() as version");
            $info['mysql_version'] = $stmt->fetch()['version'];
            
            // Database name
            $info['database_name'] = $this->dbname;
            
            // Connection info
            $stmt = $this->query("SELECT CONNECTION_ID() as connection_id");
            $info['connection_id'] = $stmt->fetch()['connection_id'];
            
            // Character set
            $stmt = $this->query("SELECT @@character_set_database as charset");
            $info['charset'] = $stmt->fetch()['charset'];
            
            // Timezone
            $stmt = $this->query("SELECT @@time_zone as timezone");
            $info['timezone'] = $stmt->fetch()['timezone'];
            
            return $info;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
    
    /**
     * Close connection on destruct
     */
    public function __destruct() {
        $this->connection = null;
    }
}

// Backward compatibility - create global database instance
try {
    $db = Database::getInstance();
} catch (Exception $e) {
    error_log("Failed to create database instance: " . $e->getMessage());
}

// Helper function for quick database access
if (!function_exists('getDatabase')) {
    function getDatabase() {
        return Database::getInstance();
    }
}

// Test database connection if this file is accessed directly
if (basename($_SERVER['PHP_SELF']) === 'database.php') {
    try {
        $db = Database::getInstance();
        $info = $db->getDatabaseInfo();
        
        echo "<h3>Database Connection Test</h3>";
        echo "<pre>";
        print_r($info);
        echo "</pre>";
        
        echo "<p style='color: green;'>✅ Database connection successful!</p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
    }
}
?>