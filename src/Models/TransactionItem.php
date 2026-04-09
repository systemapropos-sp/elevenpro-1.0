<?php
/**
 * ElevenPro POS - Transaction Item Model
 * https://elevenpropos.com
 */

namespace App\Models;

class TransactionItem extends Model
{
    protected string $table = 'transaction_items';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'transaction_id',
        'product_id',
        'quantity',
        'unit_price',
        'discount',
        'total_price',
        'cost_price'
    ];

    /**
     * Obtener items por transacción
     */
    public function getByTransaction(int $transactionId): array
    {
        $sql = "SELECT ti.*, p.name as product_name, p.sku, p.barcode 
                FROM {$this->table} ti 
                JOIN products p ON ti.product_id = p.id 
                WHERE ti.transaction_id = :transaction_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['transaction_id' => $transactionId]);
        
        return $stmt->fetchAll();
    }

    /**
     * Obtener productos más vendidos
     */
    public function getTopProducts(int $limit = 10, ?string $startDate = null, ?string $endDate = null): array
    {
        $sql = "SELECT 
                    p.id,
                    p.name,
                    p.sku,
                    SUM(ti.quantity) as total_quantity,
                    SUM(ti.total_price) as total_revenue,
                    AVG(ti.unit_price) as avg_price
                FROM {$this->table} ti
                JOIN products p ON ti.product_id = p.id
                JOIN transactions t ON ti.transaction_id = t.id
                WHERE t.status = 'completed'";
        
        $params = [];
        
        if ($startDate && $endDate) {
            $sql .= " AND DATE(t.created_at) BETWEEN :start_date AND :end_date";
            $params['start_date'] = $startDate;
            $params['end_date'] = $endDate;
        }
        
        $sql .= " GROUP BY p.id, p.name, p.sku
                  ORDER BY total_quantity DESC
                  LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}
