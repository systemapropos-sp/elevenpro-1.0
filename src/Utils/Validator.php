<?php
/**
 * ElevenPro POS - Validator Utility
 * https://elevenpropos.com
 */

namespace App\Utils;

class Validator
{
    private array $errors = [];
    private array $data = [];

    /**
     * Constructor
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Validar campo requerido
     */
    public function required(string $field, string $message = null): self
    {
        if (empty($this->data[$field]) || trim($this->data[$field]) === '') {
            $this->errors[$field] = $message ?? "El campo {$field} es requerido";
        }
        return $this;
    }

    /**
     * Validar email
     */
    public function email(string $field, string $message = null): self
    {
        if (!empty($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = $message ?? "El campo {$field} debe ser un email válido";
        }
        return $this;
    }

    /**
     * Validar longitud mínima
     */
    public function minLength(string $field, int $length, string $message = null): self
    {
        if (!empty($this->data[$field]) && strlen($this->data[$field]) < $length) {
            $this->errors[$field] = $message ?? "El campo {$field} debe tener al menos {$length} caracteres";
        }
        return $this;
    }

    /**
     * Validar longitud máxima
     */
    public function maxLength(string $field, int $length, string $message = null): self
    {
        if (!empty($this->data[$field]) && strlen($this->data[$field]) > $length) {
            $this->errors[$field] = $message ?? "El campo {$field} no debe exceder {$length} caracteres";
        }
        return $this;
    }

    /**
     * Validar número
     */
    public function numeric(string $field, string $message = null): self
    {
        if (!empty($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field] = $message ?? "El campo {$field} debe ser un número";
        }
        return $this;
    }

    /**
     * Validar número entero
     */
    public function integer(string $field, string $message = null): self
    {
        if (!empty($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_INT)) {
            $this->errors[$field] = $message ?? "El campo {$field} debe ser un número entero";
        }
        return $this;
    }

    /**
     * Validar número positivo
     */
    public function positive(string $field, string $message = null): self
    {
        if (!empty($this->data[$field]) && (!is_numeric($this->data[$field]) || $this->data[$field] < 0)) {
            $this->errors[$field] = $message ?? "El campo {$field} debe ser un número positivo";
        }
        return $this;
    }

    /**
     * Validar rango
     */
    public function range(string $field, float $min, float $max, string $message = null): self
    {
        if (!empty($this->data[$field])) {
            $value = floatval($this->data[$field]);
            if ($value < $min || $value > $max) {
                $this->errors[$field] = $message ?? "El campo {$field} debe estar entre {$min} y {$max}";
            }
        }
        return $this;
    }

    /**
     * Validar que coincida con otro campo
     */
    public function matches(string $field, string $matchField, string $message = null): self
    {
        if (!empty($this->data[$field]) && $this->data[$field] !== ($this->data[$matchField] ?? null)) {
            $this->errors[$field] = $message ?? "El campo {$field} no coincide con {$matchField}";
        }
        return $this;
    }

    /**
     * Validar con expresión regular
     */
    public function regex(string $field, string $pattern, string $message = null): self
    {
        if (!empty($this->data[$field]) && !preg_match($pattern, $this->data[$field])) {
            $this->errors[$field] = $message ?? "El campo {$field} tiene un formato inválido";
        }
        return $this;
    }

    /**
     * Validar teléfono
     */
    public function phone(string $field, string $message = null): self
    {
        $pattern = '/^[0-9\s\-\+\(\)]{8,20}$/';
        if (!empty($this->data[$field]) && !preg_match($pattern, $this->data[$field])) {
            $this->errors[$field] = $message ?? "El campo {$field} debe ser un teléfono válido";
        }
        return $this;
    }

    /**
     * Validar RFC (México)
     */
    public function rfc(string $field, string $message = null): self
    {
        $pattern = '/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/';
        if (!empty($this->data[$field]) && !preg_match($pattern, strtoupper($this->data[$field]))) {
            $this->errors[$field] = $message ?? "El RFC no tiene un formato válido";
        }
        return $this;
    }

    /**
     * Validar con función personalizada
     */
    public function custom(string $field, callable $callback, string $message = null): self
    {
        if (!empty($this->data[$field]) && !$callback($this->data[$field])) {
            $this->errors[$field] = $message ?? "El campo {$field} no es válido";
        }
        return $this;
    }

    /**
     * Verificar si la validación pasó
     */
    public function passes(): bool
    {
        return empty($this->errors);
    }

    /**
     * Verificar si la validación falló
     */
    public function fails(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Obtener errores
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Obtener primer error
     */
    public function firstError(): ?string
    {
        return $this->errors[array_key_first($this->errors)] ?? null;
    }

    /**
     * Sanitizar string
     */
    public static function sanitize(string $value): string
    {
        return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitizar email
     */
    public static function sanitizeEmail(string $value): string
    {
        return filter_var(trim($value), FILTER_SANITIZE_EMAIL);
    }

    /**
     * Sanitizar entero
     */
    public static function sanitizeInt($value): int
    {
        return filter_var($value, FILTER_VALIDATE_INT) ?: 0;
    }

    /**
     * Sanitizar float
     */
    public static function sanitizeFloat($value): float
    {
        return filter_var($value, FILTER_VALIDATE_FLOAT) ?: 0.0;
    }
}
