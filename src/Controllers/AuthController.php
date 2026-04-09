<?php
/**
 * ElevenPro POS - Auth Controller
 * https://elevenpropos.com
 */

namespace App\Controllers;

use App\Config\JWTConfig;
use App\Models\Tenant;
use App\Models\User;
use App\Utils\Response;
use App\Utils\Validator;

class AuthController
{
    private Tenant $tenantModel;
    private User $userModel;

    public function __construct()
    {
        $this->tenantModel = new Tenant();
    }

    /**
     * Login de tenant (negocio)
     */
    public function loginTenant(): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $validator = new Validator($data);
        $validator
            ->required('email', 'El email es requerido')
            ->email('email', 'El email no es válido')
            ->required('password', 'La contraseña es requerida');

        if ($validator->fails()) {
            Response::error('Datos inválidos', 422, $validator->errors());
        }

        $email = Validator::sanitizeEmail($data['email']);
        $password = $data['password'];

        // Buscar tenant
        $tenant = $this->tenantModel->findByEmail($email);

        if (!$tenant) {
            Response::error('Credenciales inválidas', 401);
        }

        // Verificar contraseña
        if (!password_verify($password, $tenant['admin_password'])) {
            Response::error('Credenciales inválidas', 401);
        }

        // Verificar estado
        if ($tenant['status'] !== 'active') {
            Response::error('Cuenta suspendida. Contacte al administrador.', 403);
        }

        // Verificar suscripción
        if (!$this->tenantModel->hasValidSubscription($tenant['id'])) {
            Response::error('Suscripción expirada. Renueve su plan.', 403);
        }

        // Generar token
        $token = JWTConfig::generate([
            'type' => 'tenant',
            'tenant_id' => $tenant['id'],
            'email' => $tenant['admin_email'],
            'business_name' => $tenant['business_name'],
            'slug' => $tenant['slug'],
            'db_name' => $tenant['db_name'],
            'plan' => $tenant['plan']
        ]);

        Response::success([
            'token' => $token,
            'expires_in' => JWTConfig::getExpireTime(),
            'tenant' => [
                'id' => $tenant['id'],
                'business_name' => $tenant['business_name'],
                'slug' => $tenant['slug'],
                'email' => $tenant['admin_email'],
                'plan' => $tenant['plan'],
                'logo_url' => $tenant['logo_url']
            ]
        ], 'Login exitoso');
    }

    /**
     * Login de usuario (dentro del tenant)
     */
    public function loginUser(): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $validator = new Validator($data);
        $validator
            ->required('email', 'El email es requerido')
            ->email('email', 'El email no es válido')
            ->required('password', 'La contraseña es requerida')
            ->required('db_name', 'La base de datos es requerida');

        if ($validator->fails()) {
            Response::error('Datos inválidos', 422, $validator->errors());
        }

        $email = Validator::sanitizeEmail($data['email']);
        $password = $data['password'];
        $dbName = $data['db_name'];

        // Conectar a base de datos del tenant
        try {
            $tenantDb = \App\Config\Database::getTenantConnection($dbName);
            $this->userModel = new User($tenantDb);
        } catch (\Exception $e) {
            Response::error('Error de conexión a la base de datos', 500);
        }

        // Buscar usuario
        $user = $this->userModel->findByEmail($email);

        if (!$user) {
            Response::error('Credenciales inválidas', 401);
        }

        // Verificar contraseña
        if (!$this->userModel->verifyPassword($password, $user['password'])) {
            Response::error('Credenciales inválidas', 401);
        }

        // Verificar si está activo
        if (!$user['is_active']) {
            Response::error('Usuario desactivado. Contacte al administrador.', 403);
        }

        // Actualizar último login
        $this->userModel->updateLastLogin($user['id']);

        // Generar token
        $token = JWTConfig::generate([
            'type' => 'user',
            'user_id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role'],
            'db_name' => $dbName
        ]);

        Response::success([
            'token' => $token,
            'expires_in' => JWTConfig::getExpireTime(),
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'avatar' => $user['avatar']
            ]
        ], 'Login exitoso');
    }

    /**
     * Registrar nuevo tenant
     */
    public function registerTenant(): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $validator = new Validator($data);
        $validator
            ->required('business_name', 'El nombre del negocio es requerido')
            ->minLength('business_name', 3, 'El nombre debe tener al menos 3 caracteres')
            ->required('email', 'El email es requerido')
            ->email('email', 'El email no es válido')
            ->required('password', 'La contraseña es requerida')
            ->minLength('password', 8, 'La contraseña debe tener al menos 8 caracteres')
            ->required('password_confirmation', 'La confirmación de contraseña es requerida')
            ->matches('password_confirmation', 'password', 'Las contraseñas no coinciden');

        if ($validator->fails()) {
            Response::error('Datos inválidos', 422, $validator->errors());
        }

        // Verificar si el email ya existe
        $existing = $this->tenantModel->findByEmail($data['email']);
        if ($existing) {
            Response::error('El email ya está registrado', 409);
        }

        // Crear tenant
        $tenantData = [
            'business_name' => Validator::sanitize($data['business_name']),
            'admin_email' => Validator::sanitizeEmail($data['email']),
            'admin_password' => $data['password'],
            'phone' => !empty($data['phone']) ? Validator::sanitize($data['phone']) : null,
            'plan' => 'free'
        ];

        $tenant = $this->tenantModel->createWithDatabase($tenantData);

        if (!$tenant) {
            Response::error('Error al crear el negocio. Intente nuevamente.', 500);
        }

        Response::success([
            'tenant' => [
                'id' => $tenant['id'],
                'business_name' => $tenant['business_name'],
                'slug' => $tenant['slug'],
                'email' => $tenant['admin_email']
            ]
        ], 'Negocio creado exitosamente', 201);
    }

    /**
     * Verificar token
     */
    public function verifyToken(): void
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';

        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            Response::error('Token no proporcionado', 401);
        }

        $token = $matches[1];
        $payload = JWTConfig::verify($token);

        if (!$payload) {
            Response::error('Token inválido o expirado', 401);
        }

        Response::success([
            'valid' => true,
            'payload' => $payload
        ]);
    }

    /**
     * Refrescar token
     */
    public function refreshToken(): void
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';

        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            Response::error('Token no proporcionado', 401);
        }

        $token = $matches[1];
        $newToken = JWTConfig::refresh($token);

        if (!$newToken) {
            Response::error('Token inválido o expirado', 401);
        }

        Response::success([
            'token' => $newToken,
            'expires_in' => JWTConfig::getExpireTime()
        ]);
    }

    /**
     * Cambiar contraseña
     */
    public function changePassword(): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $validator = new Validator($data);
        $validator
            ->required('current_password', 'La contraseña actual es requerida')
            ->required('new_password', 'La nueva contraseña es requerida')
            ->minLength('new_password', 8, 'La nueva contraseña debe tener al menos 8 caracteres')
            ->required('new_password_confirmation', 'La confirmación es requerida')
            ->matches('new_password_confirmation', 'new_password', 'Las contraseñas no coinciden');

        if ($validator->fails()) {
            Response::error('Datos inválidos', 422, $validator->errors());
        }

        // Obtener usuario del token
        $user = $this->getCurrentUser();
        if (!$user) {
            Response::error('Usuario no autenticado', 401);
        }

        // Verificar contraseña actual
        if (!$this->userModel->verifyPassword($data['current_password'], $user['password'])) {
            Response::error('Contraseña actual incorrecta', 400);
        }

        // Actualizar contraseña
        $this->userModel->updatePassword($user['id'], $data['new_password']);

        Response::success([], 'Contraseña actualizada exitosamente');
    }

    /**
     * Obtener usuario actual desde el token
     */
    private function getCurrentUser(): ?array
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';

        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return null;
        }

        $payload = JWTConfig::verify($matches[1]);
        if (!$payload || $payload['type'] !== 'user') {
            return null;
        }

        $dbName = $payload['db_name'] ?? null;
        if (!$dbName) {
            return null;
        }

        try {
            $tenantDb = \App\Config\Database::getTenantConnection($dbName);
            $userModel = new User($tenantDb);
            return $userModel->find($payload['user_id']);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Logout
     */
    public function logout(): void
    {
        // En JWT el logout es manejado del lado del cliente
        // Solo respondemos éxito
        Response::success([], 'Sesión cerrada exitosamente');
    }
}
