<?php
/**
 * ElevenPro POS - Product Model
 * https://elevenpropos.com
 */

namespace App\Models;

class Product extends Model
{
    protected string $table = 'products';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'sku',
        'name',
        'description',
        'category_id',
        'price',
        'cost',
        'stock',
        'min_stock',
        'max_stock',
        'image_url',
        'barcode',
        'unit',
        'is_active',
        'allow_negative_stock'
    ];

    /**
     * Encontrar por SKU
     */
    public function findBySku(string $sku): ?array
    {
        return $this->findBy('sku', $sku);
    }

    /**
     * Encontrar por código de barras
     */
    public function findByBarcode(string $barcode): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE barcode = :barcode AND is_active = 1 LIMIT 1");
        $stmt->execute(['barcode' => $barcode]);
        $result = $stmt->fetch();
        
        return $result ? $this->hideFields($result) : null;
    }

    /**
     * Buscar productos
     */
    public function search(string $query): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE (name LIKE :query OR sku LIKE :query OR barcode LIKE :query) AND is_active = 1 ORDER BY name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['query' => '%' . $query . '%']);
        
        return array_map([$this, 'hideFields'], $stmt->fetchAll());
    }

    /**
     * Obtener productos por categoría
     */
    public function getByCategory(int $categoryId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE category_id = :category_id AND is_active = 1 ORDER BY name ASC");
        $stmt->execute(['category_id' => $categoryId]);
        
        return array_map([$this, 'hideFields'], $stmt->fetchAll());
    }

    /**
     * Obtener productos con stock bajo
     */
    public function getLowStock(): array
    {
        $stmt = $this->db->query("SELECT * FROM {$this->table} WHERE stock <= min_stock AND is_active = 1 ORDER BY stock ASC");
        
        return array_map([$this, 'hideFields'], $stmt->fetchAll());
    }

    /**
     * Contar productos con stock bajo
     */
    public function countLowStock(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM {$this->table} WHERE stock <= min_stock AND is_active = 1");
        return (int) $stmt->fetch()['count'];
    }

    /**
     * Actualizar stock
     */
    public function updateStock(int $id, int $quantity, string $type = 'adjustment', ?string $reason = null, int $userId = 0): bool
    {
        $product = $this->find($id);
        if (!$product) {
            return false;
        }

        $stockBefore = $product['stock'];
        $stockAfter = $stockBefore + $quantity;

        // Verificar stock negativo
        if ($stockAfter < 0 && !$product['allow_negative_stock']) {
            return false;
        }

        // Actualizar stock
        $success = $this->update($id, ['stock' => $stockAfter]);

        if ($success) {
            // Registrar en log de inventario
            $this->logInventoryMovement($id, $type, $quantity, $stockBefore, $stockAfter, $reason, $userId);
        }

        return $success;
    }

    /**
     * Registrar movimiento de inventario
     */
    private function logInventoryMovement(int $productId, string $type, int $quantity, int $stockBefore, int $stockAfter, ?string $reason, int $userId): void
    {
        $sql = "INSERT INTO inventory_logs (product_id, type, quantity, stock_before, stock_after, reason, user_id, created_at) 
                VALUES (:product_id, :type, :quantity, :stock_before, :stock_after, :reason, :user_id, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'product_id' => $productId,
            'type' => $type,
            'quantity' => $quantity,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'reason' => $reason,
            'user_id' => $userId
        ]);
    }

    /**
     * Obtener productos más vendidos
     */
    public function getTopSelling(int $limit = 10, ?string $startDate = null, ?string $endDate = null): array
    {
        $sql = "SELECT p.*, SUM(ti.quantity) as total_sold 
                FROM {$this->table} p 
                JOIN transaction_items ti ON p.id = ti.product_id 
                JOIN transactions t ON ti.transaction_id = t.id 
                WHERE t.status = 'completed'";
        
        $params = [];
        
        if ($startDate && $endDate) {
            $sql .= " AND t.created_at BETWEEN :start_date AND :end_date";
            $params['start_date'] = $startDate;
            $params['end_date'] = $endDate;
        }
        
        $sql .= " GROUP BY p.id ORDER BY total_sold DESC LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Activar/desactivar producto
     */
    public function toggleActive(int $id): bool
    {
        $product = $this->find($id);
        if (!$product) {
            return false;
        }
        
        return $this->update($id, ['is_active' => $product['is_active'] ? 0 : 1]);
    }

    /**
     * Obtener valor del inventario
     */
    public function getInventoryValue(): array
    {
        $stmt = $this->db->query("SELECT 
            SUM(stock * cost) as total_cost,
            SUM(stock * price) as total_price,
            COUNT(*) as total_products
            FROM {$this->table} WHERE is_active = 1");
        
        return $stmt->fetch();
    }

    /**
     * Verificar disponibilidad para venta
     */
    public function isAvailableForSale(int $id, int $quantity = 1): bool
    {
        $product = $this->find($id);
        
        if (!$product || !$product['is_active']) {
            return false;
        }

        if ($product['allow_negative_stock']) {
            return true;
        }

        return $product['stock'] >= $quantity;
    }

    /**
     * Obtener productos para POS (grid)
     */
    public function getForPos(?int $categoryId = null): array
    {
        $sql = "SELECT id, name, price, stock, image_url, category_id 
                FROM {$this->table} 
                WHERE is_active = 1";
        
        $params = [];
        
        if ($categoryId) {
            $sql .= " AND category_id = :category_id";
            $params['category_id'] = $categoryId;
        }
        
        $sql .= " ORDER BY name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
}
