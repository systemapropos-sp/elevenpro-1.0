<?php
/**
 * ElevenPro POS - Database Configuration
 * https://elevenpropos.com
 */

namespace App\Config;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $masterConnection = null;
    private static ?PDO $tenantConnection = null;
    private static array $config = [];

    /**
     * Cargar configuración desde .env
     */
    public static function loadConfig(): void
    {
        if (empty(self::$config)) {
            self::$config = [
                'host' => $_ENV['DB_HOST'] ?? 'localhost',
                'port' => $_ENV['DB_PORT'] ?? '3306',
                'database' => $_ENV['DB_NAME'] ?? '',
                'username' => $_ENV['DB_USER'] ?? '',
                'password' => $_ENV['DB_PASS'] ?? '',
                'charset' => 'utf8mb4'
            ];
        }
    }

    /**
     * Obtener conexión a la base de datos maestra
     */
    public static function getMasterConnection(): PDO
    {
        self::loadConfig();

        if (self::$masterConnection === null) {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                    self::$config['host'],
                    self::$config['port'],
                    self::$config['database'],
                    self::$config['charset']
                );

                self::$masterConnection = new PDO($dsn, self::$config['username'], self::$config['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ]);
            } catch (PDOException $e) {
                error_log("Database connection error: " . $e->getMessage());
                throw new \Exception("Error de conexión a la base de datos");
            }
        }

        return self::$masterConnection;
    }

    /**
     * Obtener conexión a la base de datos del tenant
     */
    public static function getTenantConnection(string $tenantDbName): PDO
    {
        self::loadConfig();

        if (self::$tenantConnection === null) {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                    self::$config['host'],
                    self::$config['port'],
                    $tenantDbName,
                    self::$config['charset']
                );

                self::$tenantConnection = new PDO($dsn, self::$config['username'], self::$config['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ]);
            } catch (PDOException $e) {
                error_log("Tenant database connection error: " . $e->getMessage());
                throw new \Exception("Error de conexión a la base de datos del tenant");
            }
        }

        return self::$tenantConnection;
    }

    /**
     * Cerrar conexiones
     */
    public static function closeConnections(): void
    {
        self::$masterConnection = null;
        self::$tenantConnection = null;
    }

    /**
     * Crear nueva base de datos para tenant
     */
    public static function createTenantDatabase(string $dbName): bool
    {
        try {
            $pdo = self::getMasterConnection();
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            return true;
        } catch (PDOException $e) {
            error_log("Error creating tenant database: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Ejecutar script SQL en base de datos tenant
     */
    public static function runMigration(string $dbName, string $sqlFile): bool
    {
        try {
            $pdo = self::getTenantConnection($dbName);
            $sql = file_get_contents($sqlFile);
            $pdo->exec($sql);
            return true;
        } catch (PDOException $e) {
            error_log("Error running migration: " . $e->getMessage());
            return false;
        }
    }
}
