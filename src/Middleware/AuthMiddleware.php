<?php
/**
 * ElevenPro POS - Auth Middleware
 * https://elevenpropos.com
 */

namespace App\Middleware;

use App\Config\JWTConfig;
use App\Utils\Response;

class AuthMiddleware
{
    /**
     * Verificar autenticación
     */
    public static function authenticate(): ?array
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';

        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            Response::error('Token no proporcionado', 401);
            return null;
        }

        $token = $matches[1];
        $payload = JWTConfig::verify($token);

        if (!$payload) {
            Response::error('Token inválido o expirado', 401);
            return null;
        }

        return $payload;
    }

    /**
     * Verificar que sea tipo tenant
     */
    public static function requireTenant(): array
    {
        $payload = self::authenticate();
        
        if (!$payload) {
            exit;
        }

        if ($payload['type'] !== 'tenant') {
            Response::error('Acceso no autorizado', 403);
            exit;
        }

        return $payload;
    }

    /**
     * Verificar que sea tipo usuario
     */
    public static function requireUser(): array
    {
        $payload = self::authenticate();
        
        if (!$payload) {
            exit;
        }

        if ($payload['type'] !== 'user') {
            Response::error('Acceso no autorizado', 403);
            exit;
        }

        return $payload;
    }

    /**
     * Verificar rol de administrador
     */
    public static function requireAdmin(): array
    {
        $payload = self::requireUser();

        if ($payload['role'] !== 'admin') {
            Response::error('Se requieren permisos de administrador', 403);
            exit;
        }

        return $payload;
    }

    /**
     * Verificar rol de gerente o superior
     */
    public static function requireManager(): array
    {
        $payload = self::requireUser();

        if (!in_array($payload['role'], ['admin', 'manager'])) {
            Response::error('Se requieren permisos de gerente', 403);
            exit;
        }

        return $payload;
    }

    /**
     * Verificar rol de cajero o superior
     */
    public static function requireCashier(): array
    {
        $payload = self::requireUser();

        if (!in_array($payload['role'], ['admin', 'manager', 'cashier'])) {
            Response::error('Se requieren permisos de cajero', 403);
            exit;
        }

        return $payload;
    }

    /**
     * Verificar permiso específico
     */
    public static function requirePermission(string $permission): array
    {
        $payload = self::requireUser();

        // Obtener permisos del rol
        $permissions = self::getRolePermissions($payload['role']);

        if (!in_array($permission, $permissions)) {
            Response::error('No tiene permiso para realizar esta acción', 403);
            exit;
        }

        return $payload;
    }

    /**
     * Obtener permisos por rol
     */
    private static function getRolePermissions(string $role): array
    {
        $permissions = [
            'admin' => [
                'dashboard.view',
                'sales.create', 'sales.view', 'sales.cancel', 'sales.refund',
                'products.create', 'products.edit', 'products.delete', 'products.view',
                'categories.create', 'categories.edit', 'categories.delete',
                'customers.create', 'customers.edit', 'customers.delete', 'customers.view',
                'users.create', 'users.edit', 'users.delete', 'users.view',
                'inventory.view', 'inventory.adjust', 'inventory.history',
                'reports.view', 'reports.export',
                'settings.view', 'settings.edit',
                'cash.register.open', 'cash.register.close', 'cash.register.view'
            ],
            'manager' => [
                'dashboard.view',
                'sales.create', 'sales.view', 'sales.cancel',
                'products.create', 'products.edit', 'products.view',
                'categories.create', 'categories.edit',
                'customers.create', 'customers.edit', 'customers.view',
                'inventory.view', 'inventory.adjust', 'inventory.history',
                'reports.view', 'reports.export',
                'cash.register.open', 'cash.register.close', 'cash.register.view'
            ],
            'cashier' => [
                'dashboard.view',
                'sales.create', 'sales.view',
                'products.view',
                'customers.create', 'customers.view',
                'cash.register.open', 'cash.register.close'
            ]
        ];

        return $permissions[$role] ?? [];
    }

    /**
     * Obtener conexión a base de datos del tenant
     */
    public static function getTenantDb(array $payload): \PDO
    {
        $dbName = $payload['db_name'] ?? null;

        if (!$dbName) {
            Response::error('Base de datos no especificada', 500);
            exit;
        }

        try {
            return \App\Config\Database::getTenantConnection($dbName);
        } catch (\Exception $e) {
            Response::error('Error de conexión a la base de datos', 500);
            exit;
        }
    }
}
