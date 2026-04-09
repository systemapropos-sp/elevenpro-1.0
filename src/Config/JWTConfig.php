<?php
/**
 * ElevenPro POS - JWT Configuration
 * https://elevenpropos.com
 */

namespace App\Config;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

class JWTConfig
{
    private static string $secret;
    private static int $expireTime;
    private static string $algorithm = 'HS256';

    /**
     * Inicializar configuración
     */
    public static function init(): void
    {
        self::$secret = $_ENV['JWT_SECRET'] ?? 'default_secret_key_change_in_production';
        self::$expireTime = intval($_ENV['JWT_EXPIRE'] ?? 86400); // 24 horas por defecto
    }

    /**
     * Generar token JWT
     */
    public static function generate(array $payload): string
    {
        self::init();

        $issuedAt = time();
        $expire = $issuedAt + self::$expireTime;

        $tokenPayload = array_merge($payload, [
            'iat' => $issuedAt,
            'exp' => $expire,
            'iss' => $_ENV['APP_URL'] ?? 'https://elevenpropos.com'
        ]);

        return JWT::encode($tokenPayload, self::$secret, self::$algorithm);
    }

    /**
     * Verificar y decodificar token JWT
     */
    public static function verify(string $token): ?array
    {
        self::init();

        try {
            $decoded = JWT::decode($token, new Key(self::$secret, self::$algorithm));
            return (array) $decoded;
        } catch (ExpiredException $e) {
            error_log("JWT Token expired: " . $e->getMessage());
            return null;
        } catch (SignatureInvalidException $e) {
            error_log("JWT Invalid signature: " . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            error_log("JWT Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Refrescar token JWT
     */
    public static function refresh(string $token): ?string
    {
        $payload = self::verify($token);
        
        if ($payload === null) {
            return null;
        }

        // Eliminar campos de tiempo para regenerar
        unset($payload['iat']);
        unset($payload['exp']);
        unset($payload['iss']);

        return self::generate($payload);
    }

    /**
     * Obtener tiempo de expiración
     */
    public static function getExpireTime(): int
    {
        self::init();
        return self::$expireTime;
    }
}
