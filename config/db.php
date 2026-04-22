<?php
/**
 * TRUSTLINK - Database Connection Manager
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: PDO database connection with PostgreSQL support
 * Features:
 * - Connection pooling ready
 * - Prepared statements support
 * - Error handling with debug mode
 * - Connection retry logic
 */

namespace TrustLink\Config;

// Load environment configuration
require_once __DIR__ . '/load_env.php';

// Initialize environment
EnvironmentLoader::load();

class Database
{
    /**
     * @var \PDO|null Singleton PDO instance
     */
    private static $instance = null;
    
    /**
     * @var array Connection options
     */
    private static $options = [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false,
        \PDO::ATTR_STRINGIFY_FETCHES => false,
        \PDO::ATTR_TIMEOUT => 30,
    ];
    
    /**
     * Private constructor - use getInstance() instead
     */
    private function __construct()
    {
        // Singleton pattern
    }
    
    /**
     * Get database connection instance (Singleton)
     * 
     * @return \PDO
     * @throws \PDOException
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = self::createConnection();
        }
        
        return self::$instance;
    }
    
    /**
     * Create a new database connection
     * 
     * @return \PDO
     * @throws \PDOException
     */
private static function createConnection()
{
    // Read database credentials from environment variables (set on Render)
    $driver = 'pgsql';
    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: '5432';
    $dbname = getenv('DB_NAME') ?: 'trustlink';
    $user = getenv('DB_USER') ?: 'postgres';
    $pass = getenv('DB_PASS') ?: '';
    $charset = 'utf8';

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;options='--client_encoding=$charset'";

    error_log("Connecting to DB: host=$host, dbname=$dbname, user=$user");

    try {
        $pdo = new \PDO($dsn, $user, $pass, self::$options);
        $pdo->exec("SET timezone = 'Africa/Nairobi'");
        error_log("Database connection successful");
        return $pdo;
    } catch (\PDOException $e) {
        error_log("DB connection failed: " . $e->getMessage());
        throw $e;
    }
}
    
    /**
     * Close the database connection
     * 
     * @return void
     */
    public static function close()
    {
        self::$instance = null;
    }
    
    /**
     * Execute a query with parameters (prepared statement)
     * 
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters to bind
     * @return \PDOStatement
     * @throws \PDOException
     */
    public static function query($sql, $params = [])
    {
        $db = self::getInstance();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    /**
     * Fetch a single row
     * 
     * @param string $sql SQL query
     * @param array $params Parameters
     * @return array|false
     */
    public static function fetchOne($sql, $params = [])
    {
        $stmt = self::query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Fetch all rows
     * 
     * @param string $sql SQL query
     * @param array $params Parameters
     * @return array
     */
    public static function fetchAll($sql, $params = [])
    {
        $stmt = self::query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Insert a record and return last insert ID
     * 
     * @param string $table Table name
     * @param array $data Associative array of column => value
     * @return int|string Last insert ID
     */
    public static function insert($table, $data)
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s) RETURNING id",
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        
        $stmt = self::query($sql, array_values($data));
        $result = $stmt->fetch();
        
        return $result['id'];
    }
    
    /**
     * Update records
     * 
     * @param string $table Table name
     * @param array $data Associative array of column => value
     * @param string $where WHERE clause with placeholders
     * @param array $whereParams Parameters for WHERE clause
     * @return int Number of affected rows
     */
    public static function update($table, $data, $where, $whereParams = [])
    {
        $setClause = [];
        $params = [];
        
        foreach ($data as $column => $value) {
            $setClause[] = "$column = ?";
            $params[] = $value;
        }
        
        // Merge where parameters
        $params = array_merge($params, $whereParams);
        
        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $table,
            implode(', ', $setClause),
            $where
        );
        
        $stmt = self::query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Delete records
     * 
     * @param string $table Table name
     * @param string $where WHERE clause with placeholders
     * @param array $params Parameters
     * @return int Number of affected rows
     */
    public static function delete($table, $where, $params = [])
    {
        $sql = sprintf("DELETE FROM %s WHERE %s", $table, $where);
        $stmt = self::query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Begin a transaction
     * 
     * @return bool
     */
    public static function beginTransaction()
    {
        return self::getInstance()->beginTransaction();
    }
    
    /**
     * Commit a transaction
     * 
     * @return bool
     */
    public static function commit()
    {
        return self::getInstance()->commit();
    }
    
    /**
     * Rollback a transaction
     * 
     * @return bool
     */
    public static function rollback()
    {
        return self::getInstance()->rollBack();
    }
    
    /**
     * Check if inside a transaction
     * 
     * @return bool
     */
    public static function inTransaction()
    {
        return self::getInstance()->inTransaction();
    }
    
    /**
     * Get the last insert ID (PostgreSQL uses RETURNING, this is fallback)
     * 
     * @param string $sequence Sequence name for PostgreSQL
     * @return string
     */
    public static function lastInsertId($sequence = null)
    {
        return self::getInstance()->lastInsertId($sequence);
    }
    
    /**
     * Log database errors (for production)
     * 
     * @param string $message Error message
     * @param string $dsn DSN for context
     * @return void
     */
    private static function logError($message, $dsn)
    {
        if (!EnvironmentLoader::isDebug()) {
            $logPath = EnvironmentLoader::get('LOG_PATH', __DIR__ . '/../logs/error.log');
            $logDir = dirname($logPath);
            
            // Create logs directory if it doesn't exist
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            $timestamp = date('Y-m-d H:i:s');
            $logMessage = "[$timestamp] Database Error: $message | DSN: $dsn" . PHP_EOL;
            error_log($logMessage, 3, $logPath);
        }
    }
    
    /**
     * Test database connection
     * 
     * @return array Connection test result
     */
    public static function testConnection()
    {
        try {
            $db = self::getInstance();
            $stmt = $db->query('SELECT 1 as test, version() as version, current_database() as database');
            $result = $stmt->fetch();
            
            return [
                'success' => true,
                'message' => 'Database connection successful',
                'version' => $result['version'],
                'database' => $result['database']
            ];
        } catch (\PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database connection failed: ' . $e->getMessage(),
                'error_code' => $e->getCode()
            ];
        }
    }
}

// ============================================================================
// CONVENIENCE FUNCTIONS (Global namespace for easier use)
// ============================================================================

if (!function_exists('db')) {
    /**
     * Get database connection instance
     * 
     * @return \PDO
     */
    function db()
    {
        return TrustLink\Config\Database::getInstance();
    }
}

if (!function_exists('db_query')) {
    /**
     * Execute a database query
     * 
     * @param string $sql
     * @param array $params
     * @return \PDOStatement
     */
    function db_query($sql, $params = [])
    {
        return TrustLink\Config\Database::query($sql, $params);
    }
}

if (!function_exists('db_fetch_one')) {
    /**
     * Fetch single row
     * 
     * @param string $sql
     * @param array $params
     * @return array|false
     */
    function db_fetch_one($sql, $params = [])
    {
        return TrustLink\Config\Database::fetchOne($sql, $params);
    }
}

if (!function_exists('db_fetch_all')) {
    /**
     * Fetch all rows
     * 
     * @param string $sql
     * @param array $params
     * @return array
     */
    function db_fetch_all($sql, $params = [])
    {
        return TrustLink\Config\Database::fetchAll($sql, $params);
    }
}

if (!function_exists('db_insert')) {
    /**
     * Insert record
     * 
     * @param string $table
     * @param array $data
     * @return int|string
     */
    function db_insert($table, $data)
    {
        return TrustLink\Config\Database::insert($table, $data);
    }
}

if (!function_exists('db_update')) {
    /**
     * Update records
     * 
     * @param string $table
     * @param array $data
     * @param string $where
     * @param array $whereParams
     * @return int
     */
    function db_update($table, $data, $where, $whereParams = [])
    {
        return TrustLink\Config\Database::update($table, $data, $where, $whereParams);
    }
}

if (!function_exists('db_delete')) {
    /**
     * Delete records
     * 
     * @param string $table
     * @param string $where
     * @param array $params
     * @return int
     */
    function db_delete($table, $where, $params = [])
    {
        return TrustLink\Config\Database::delete($table, $where, $params);
    }
}

if (!function_exists('db_transaction')) {
    /**
     * Execute callback within a transaction
     * 
     * @param callable $callback
     * @return mixed
     * @throws \Exception
     */
    function db_transaction($callback)
    {
        $db = TrustLink\Config\Database::getInstance();
        
        try {
            $db->beginTransaction();
            $result = $callback();
            $db->commit();
            return $result;
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
}

// Test connection if this file is accessed directly
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    header('Content-Type: application/json');
    
    // We call the class name directly because we are outside the class braces
    $test = \TrustLink\Config\Database::testConnection(); 
    
    echo json_encode($test, JSON_PRETTY_PRINT);
    exit; // Stop execution here so no other text gets output
}