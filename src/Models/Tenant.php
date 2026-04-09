<?php
/**
 * ElevenPro POS - Tenant Model
 * https://elevenpropos.com
 */

namespace App\Models;

class Tenant extends Model
{
    protected string $table = 'tenants';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'business_name',
        'slug',
        'db_name',
        'admin_email',
        'admin_password',
        'phone',
        'address',
        'logo_url',
        'status',
        'plan',
        'max_users',
        'max_products',
        'expires_at'
    ];
    
    protected array $hidden = ['admin_password'];

    /**
     * Encontrar tenant por slug
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->findBy('slug', $slug);
    }

    /**
     * Encontrar tenant por email de admin
     */
    public function findByEmail(string $email): ?array
    {
        return $this->findBy('admin_email', $email);
    }

    /**
     * Verificar si el tenant está activo
     */
    public function isActive(int $id): bool
    {
        $tenant = $this->find($id);
        return $tenant && $tenant['status'] === 'active';
    }

    /**
     * Verificar si la suscripción está vigente
     */
    public function hasValidSubscription(int $id): bool
    {
        $tenant = $this->find($id);
        
        if (!$tenant) {
            return false;
        }
        
        if ($tenant['plan'] === 'free') {
            return true;
        }
        
        if (empty($tenant['expires_at'])) {
            return false;
        }
        
        return strtotime($tenant['expires_at']) > time();
    }

    /**
     * Crear nuevo tenant con base de datos
     */
    public function createWithDatabase(array $data): ?array
    {
        // Generar slug único
        $data['slug'] = $this->generateSlug($data['business_name']);
        
        // Generar nombre de base de datos único
        $data['db_name'] = $this->generateDbName($data['slug']);
        
        // Hashear contraseña
        $data['admin_password'] = password_hash($data['admin_password'], PASSWORD_BCRYPT);
        
        // Crear registro en base maestra
        $id = $this->create($data);
        
        if (!$id) {
            return null;
        }
        
        // Crear base de datos del tenant
        $dbCreated = \App\Config\Database::createTenantDatabase($data['db_name']);
        
        if (!$dbCreated) {
            // Rollback: eliminar registro
            $this->delete($id);
            return null;
        }
        
        // Ejecutar migraciones
        $migrationFile = __DIR__ . '/../../scripts/database_tenant.sql';
        $migrationRun = \App\Config\Database::runMigration($data['db_name'], $migrationFile);
        
        if (!$migrationRun) {
            // Rollback: eliminar base de datos y registro
            // Nota: Implementar eliminación de BD
            $this->delete($id);
            return null;
        }
        
        return $this->find($id);
    }

    /**
     * Generar slug único
     */
    private function generateSlug(string $businessName): string
    {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $businessName));
        $slug = trim($slug, '-');
        
        // Verificar si existe
        $originalSlug = $slug;
        $counter = 1;
        
        while ($this->findBySlug($slug)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

    /**
     * Generar nombre de base de datos único
     */
    private function generateDbName(string $slug): string
    {
        $dbPrefix = $_ENV['DB_NAME'] ?? 'pos';
        return $dbPrefix . '_tenant_' . str_replace('-', '_', $slug);
    }

    /**
     * Actualizar logo
     */
    public function updateLogo(int $id, string $logoUrl): bool
    {
        return $this->update($id, ['logo_url' => $logoUrl]);
    }

    /**
     * Cambiar plan
     */
    public function changePlan(int $id, string $plan, ?string $expiresAt = null): bool
    {
        $data = ['plan' => $plan];
        
        if ($expiresAt) {
            $data['expires_at'] = $expiresAt;
        }
        
        // Actualizar límites según plan
        $limits = [
            'free' => ['max_users' => 2, 'max_products' => 50],
            'basic' => ['max_users' => 5, 'max_products' => 200],
            'premium' => ['max_users' => 10, 'max_products' => 1000],
            'enterprise' => ['max_users' => 50, 'max_products' => 10000]
        ];
        
        if (isset($limits[$plan])) {
            $data = array_merge($data, $limits[$plan]);
        }
        
        return $this->update($id, $data);
    }

    /**
     * Suspender tenant
     */
    public function suspend(int $id): bool
    {
        return $this->update($id, ['status' => 'suspended']);
    }

    /**
     * Activar tenant
     */
    public function activate(int $id): bool
    {
        return $this->update($id, ['status' => 'active']);
    }

    /**
     * Obtener estadísticas del tenant
     */
    public function getStats(int $id): array
    {
        $tenant = $this->find($id);
        
        if (!$tenant) {
            return [];
        }
        
        // Conectar a base de datos del tenant
        $tenantDb = \App\Config\Database::getTenantConnection($tenant['db_name']);
        
        $stats = [];
        
        // Contar usuarios
        $stmt = $tenantDb->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
        $stats['users'] = $stmt->fetch()['count'];
        
        // Contar productos
        $stmt = $tenantDb->query("SELECT COUNT(*) as count FROM products WHERE is_active = 1");
        $stats['products'] = $stmt->fetch()['count'];
        
        // Contar ventas del día
        $stmt = $tenantDb->query("SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as total FROM transactions WHERE DATE(created_at) = CURDATE() AND status = 'completed'");
        $salesToday = $stmt->fetch();
        $stats['sales_today'] = $salesToday['count'];
        $stats['revenue_today'] = $salesToday['total'];
        
        // Productos con stock bajo
        $stmt = $tenantDb->query("SELECT COUNT(*) as count FROM products WHERE stock <= min_stock AND is_active = 1");
        $stats['low_stock'] = $stmt->fetch()['count'];
        
        return $stats;
    }
}
