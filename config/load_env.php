<?php
/**
 * TRUSTLINK - Environment Configuration Loader
 * Version: 1.0 | Production Ready | March 2026
 * 
 * Description: Loads environment variables from .env file into $_ENV and getenv()
 * Usage: Include at the very beginning of your application
 */

namespace TrustLink\Config;

class EnvironmentLoader
{
    /**
     * @var array Loaded environment variables
     */
    private static $loaded = false;
    
    /**
     * @var string Path to .env file
     */
    private static $envPath = __DIR__ . '/.env';
    
    /**
     * Load environment variables from .env file
     * 
     * @param string $path Optional custom path to .env file
     * @return void
     * @throws \Exception If .env file not found in production
     */
    public static function load($path = null)
    {
        if (self::$loaded) {
            return;
        }
        
        if ($path !== null) {
            self::$envPath = $path;
        }
        
        // In production, .env must exist
        if (!file_exists(self::$envPath)) {
            $appEnv = getenv('APP_ENV') ?: 'development';
            
            if ($appEnv === 'production') {
                throw new \Exception('.env file not found in production environment');
            }
            
            // In development, we can warn but continue with defaults
            error_log('WARNING: .env file not found at ' . self::$envPath . '. Using environment variables from system.');
            self::$loaded = true;
            return;
        }
        
        // Parse .env file
        $lines = file(self::$envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse key=value
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }
            
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            
            // Remove quotes if present
            if (preg_match('/^["\'].*["\']$/', $value)) {
                $value = substr($value, 1, -1);
            }
            
            // Set environment variable
            putenv("$key=$value");
            $_ENV[$key] = $value;
            
            // Also set in $_SERVER for compatibility
            $_SERVER[$key] = $value;
        }
        
        self::$loaded = true;
    }
    
    /**
     * Get environment variable with optional default
     * 
     * @param string $key Environment variable name
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        $value = getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        // Convert string booleans
        if (strtolower($value) === 'true') {
            return true;
        }
        if (strtolower($value) === 'false') {
            return false;
        }
        
        // Convert numeric strings
        if (is_numeric($value) && strpos($value, '.') !== false) {
            return (float) $value;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        
        return $value;
    }
    
    /**
     * Check if application is in production mode
     * 
     * @return bool
     */
    public static function isProduction()
    {
        return self::get('APP_ENV') === 'production';
    }
    
    /**
     * Check if debug mode is enabled
     * 
     * @return bool
     */
    public static function isDebug()
    {
        return (bool) self::get('APP_DEBUG', false);
    }
    
    /**
     * Get application timezone
     * 
     * @return string
     */
    public static function getTimezone()
    {
        return self::get('APP_TIMEZONE', 'Africa/Nairobi');
    }
}

// Auto-load if this file is included directly (not via require_once)
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    EnvironmentLoader::load();
}