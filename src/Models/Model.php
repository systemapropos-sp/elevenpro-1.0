<?php
/**
 * ElevenPro POS - Base Model
 * https://elevenpropos.com
 */

namespace App\Models;

use App\Config\Database;
use PDO;

abstract class Model
{
    protected PDO $db;
    protected string $table;
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $hidden = [];

    /**
     * Constructor
     */
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getMasterConnection();
    }

    /**
     * Encontrar por ID
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        
        return $result ? $this->hideFields($result) : null;
    }

    /**
     * Encontrar por campo
     */
    public function findBy(string $field, $value): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$field} = :value LIMIT 1");
        $stmt->execute(['value' => $value]);
        $result = $stmt->fetch();
        
        return $result ? $this->hideFields($result) : null;
    }

    /**
     * Obtener todos los registros
     */
    public function all(array $orderBy = []): array
    {
        $sql = "SELECT * FROM {$this->table}";
        
        if (!empty($orderBy)) {
            $sql .= " ORDER BY " . implode(', ', array_map(fn($k, $v) => "{$k} {$v}", array_keys($orderBy), $orderBy));
        }
        
        $stmt = $this->db->query($sql);
        $results = $stmt->fetchAll();
        
        return array_map([$this, 'hideFields'], $results);
    }

    /**
     * Obtener con paginación
     */
    public function paginate(int $page = 1, int $perPage = 20, array $where = [], array $orderBy = []): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [];
        
        $sql = "SELECT * FROM {$this->table}";
        $countSql = "SELECT COUNT(*) as total FROM {$this->table}";
        
        // WHERE clause
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $key => $value) {
                $conditions[] = "{$key} = :{$key}";
                $params[$key] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
            $countSql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        // ORDER BY
        if (!empty($orderBy)) {
            $sql .= " ORDER BY " . implode(', ', array_map(fn($k, $v) => "{$k} {$v}", array_keys($orderBy), $orderBy));
        }
        
        // LIMIT
        $sql .= " LIMIT :limit OFFSET :offset";
        
        // Contar total
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue(":{$key}", $value);
        }
        $countStmt->execute();
        $total = $countStmt->fetch()['total'];
        
        // Obtener resultados
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = $stmt->fetchAll();
        
        return [
            'items' => array_map([$this, 'hideFields'], $results),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    /**
     * Crear registro
     */
    public function create(array $data): int
    {
        $data = $this->filterFillable($data);
        
        $fields = array_keys($data);
        $placeholders = array_map(fn($f) => ":{$f}", $fields);
        
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        
        return (int) $this->db->lastInsertId();
    }

    /**
     * Actualizar registro
     */
    public function update(int $id, array $data): bool
    {
        $data = $this->filterFillable($data);
        
        if (empty($data)) {
            return false;
        }
        
        $fields = array_map(fn($k) => "{$k} = :{$k}", array_keys($data));
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE {$this->primaryKey} = :id";
        
        $data['id'] = $id;
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

    /**
     * Eliminar registro
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Contar registros
     */
    public function count(array $where = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        $params = [];
        
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $key => $value) {
                $conditions[] = "{$key} = :{$key}";
                $params[$key] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return (int) $stmt->fetch()['total'];
    }

    /**
     * Filtrar solo campos fillable
     */
    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }
        
        return array_intersect_key($data, array_flip($this->fillable));
    }

    /**
     * Ocultar campos sensibles
     */
    protected function hideFields(array $data): array
    {
        foreach ($this->hidden as $field) {
            unset($data[$field]);
        }
        return $data;
    }

    /**
     * Ejecutar query personalizada
     */
    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Ejecutar statement
     */
    public function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
}
