<?php
/**
 * ElevenPro POS - User Model
 * https://elevenpropos.com
 */

namespace App\Models;

class User extends Model
{
    protected string $table = 'users';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'email',
        'password',
        'name',
        'role',
        'phone',
        'avatar',
        'is_active'
    ];
    
    protected array $hidden = ['password'];

    /**
     * Roles disponibles
     */
    public const ROLES = [
        'admin' => 'Administrador',
        'manager' => 'Gerente',
        'cashier' => 'Cajero'
    ];

    /**
     * Encontrar usuario por email
     */
    public function findByEmail(string $email): ?array
    {
        return $this->findBy('email', $email);
    }

    /**
     * Verificar contraseña
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Crear usuario con contraseña hasheada
     */
    public function createWithPassword(array $data): int
    {
        $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        return $this->create($data);
    }

    /**
     * Actualizar contraseña
     */
    public function updatePassword(int $id, string $newPassword): bool
    {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        return $this->update($id, ['password' => $hash]);
    }

    /**
     * Verificar si el usuario es administrador
     */
    public function isAdmin(int $id): bool
    {
        $user = $this->find($id);
        return $user && $user['role'] === 'admin';
    }

    /**
     * Verificar si el usuario es gerente
     */
    public function isManager(int $id): bool
    {
        $user = $this->find($id);
        return $user && ($user['role'] === 'admin' || $user['role'] === 'manager');
    }

    /**
     * Verificar si el usuario es cajero
     */
    public function isCashier(int $id): bool
    {
        $user = $this->find($id);
        return $user && in_array($user['role'], ['admin', 'manager', 'cashier']);
    }

    /**
     * Actualizar último login
     */
    public function updateLastLogin(int $id): bool
    {
        return $this->update($id, ['last_login' => date('Y-m-d H:i:s')]);
    }

    /**
     * Activar/desactivar usuario
     */
    public function toggleActive(int $id): bool
    {
        $user = $this->find($id);
        if (!$user) {
            return false;
        }
        
        return $this->update($id, ['is_active' => $user['is_active'] ? 0 : 1]);
    }

    /**
     * Actualizar avatar
     */
    public function updateAvatar(int $id, string $avatarUrl): bool
    {
        return $this->update($id, ['avatar' => $avatarUrl]);
    }

    /**
     * Obtener usuarios por rol
     */
    public function getByRole(string $role): array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE role = :role AND is_active = 1");
        $stmt->execute(['role' => $role]);
        return $stmt->fetchAll();
    }

    /**
     * Buscar usuarios
     */
    public function search(string $query): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE (name LIKE :query OR email LIKE :query) AND is_active = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['query' => '%' . $query . '%']);
        return $stmt->fetchAll();
    }

    /**
     * Obtener permisos del usuario
     */
    public function getPermissions(string $role): array
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
     * Verificar si tiene permiso
     */
    public function hasPermission(int $userId, string $permission): bool
    {
        $user = $this->find($userId);
        if (!$user) {
            return false;
        }

        $permissions = $this->getPermissions($user['role']);
        return in_array($permission, $permissions);
    }
}
