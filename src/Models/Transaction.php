<?php
/**
 * ElevenPro POS - Transaction Model
 * https://elevenpropos.com
 */

namespace App\Models;

class Transaction extends Model
{
    protected string $table = 'transactions';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'ticket_number',
        'customer_id',
        'user_id',
        'subtotal',
        'tax',
        'tax_rate',
        'discount',
        'discount_type',
        'total',
        'payment_method',
        'payment_reference',
        'cash_received',
        'change_amount',
        'status',
        'notes',
        'receipt_url'
    ];

    /**
     * Generar número de ticket único
     */
    public function generateTicketNumber(): string
    {
        $prefix = $_ENV['TICKET_PREFIX'] ?? 'TKT';
        $date = date('Ymd');
        
        // Obtener último número del día
        $stmt = $this->db->prepare(
            "SELECT ticket_number FROM {$this->table} 
             WHERE DATE(created_at) = CURDATE() 
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute();
        $last = $stmt->fetch();
        
        $number = 1;
        if ($last) {
            // Extraer número del último ticket
            preg_match('/-(\d+)$/', $last['ticket_number'], $matches);
            if ($matches) {
                $number = intval($matches[1]) + 1;
            }
        }
        
        return $prefix . '-' . $date . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Crear transacción con items
     */
    public function createWithItems(array $data, array $items): int
    {
        $this->db->beginTransaction();
        
        try {
            // Crear transacción
            $transactionId = $this->create($data);
            
            if (!$transactionId) {
                $this->db->rollBack();
                return 0;
            }
            
            // Crear items
            $itemModel = new TransactionItem($this->db);
            foreach ($items as $item) {
                $item['transaction_id'] = $transactionId;
                $itemModel->create($item);
            }
            
            $this->db->commit();
            return $transactionId;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Error creating transaction: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Encontrar transacción con items
     */
    public function findWithItems(int $id): ?array
    {
        $transaction = $this->find($id);
        
        if (!$transaction) {
            return null;
        }
        
        // Obtener items
        $itemModel = new TransactionItem($this->db);
        $transaction['items'] = $itemModel->getByTransaction($id);
        
        // Obtener información del usuario
        $stmt = $this->db->prepare("SELECT id, name, email FROM users WHERE id = :user_id");
        $stmt->execute(['user_id' => $transaction['user_id']]);
        $transaction['user'] = $stmt->fetch();
        
        // Obtener información del cliente si existe
        if ($transaction['customer_id']) {
            $stmt = $this->db->prepare("SELECT id, name, email, phone FROM customers WHERE id = :customer_id");
            $stmt->execute(['customer_id' => $transaction['customer_id']]);
            $transaction['customer'] = $stmt->fetch();
        }
        
        return $transaction;
    }

    /**
     * Paginación con filtros
     */
    public function paginateWithFilters(int $page, int $perPage, array $where = [], ?string $startDate = null, ?string $endDate = null): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [];
        
        $sql = "SELECT t.*, u.name as user_name, c.name as customer_name 
                FROM {$this->table} t 
                LEFT JOIN users u ON t.user_id = u.id 
                LEFT JOIN customers c ON t.customer_id = c.id";
        
        $countSql = "SELECT COUNT(*) as total FROM {$this->table} t";
        
        $conditions = [];
        
        // Filtros WHERE
        foreach ($where as $key => $value) {
            $conditions[] = "t.{$key} = :{$key}";
            $params[$key] = $value;
        }
        
        // Filtros de fecha
        if ($startDate && $endDate) {
            $conditions[] = "DATE(t.created_at) BETWEEN :start_date AND :end_date";
            $params['start_date'] = $startDate;
            $params['end_date'] = $endDate;
        } elseif ($startDate) {
            $conditions[] = "DATE(t.created_at) >= :start_date";
            $params['start_date'] = $startDate;
        } elseif ($endDate) {
            $conditions[] = "DATE(t.created_at) <= :end_date";
            $params['end_date'] = $endDate;
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
            $countSql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $sql .= " ORDER BY t.created_at DESC LIMIT :limit OFFSET :offset";
        
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
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
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
     * Obtener ventas del día
     */
    public function getTodaySales(): array
    {
        $sql = "SELECT t.*, u.name as user_name 
                FROM {$this->table} t 
                LEFT JOIN users u ON t.user_id = u.id 
                WHERE DATE(t.created_at) = CURDATE() AND t.status = 'completed'
                ORDER BY t.created_at DESC";
        
        $stmt = $this->db->query($sql);
        $sales = $stmt->fetchAll();
        
        // Calcular totales
        $totalSql = "SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as total 
                     FROM {$this->table} 
                     WHERE DATE(created_at) = CURDATE() AND status = 'completed'";
        $totalStmt = $this->db->query($totalSql);
        $totals = $totalStmt->fetch();
        
        return [
            'sales' => $sales,
            'count' => intval($totals['count']),
            'total' => floatval($totals['total'])
        ];
    }

    /**
     * Obtener ventas por período
     */
    public function getSalesByPeriod(string $startDate, string $endDate): array
    {
        $sql = "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as transactions,
                    COALESCE(SUM(total), 0) as total,
                    COALESCE(SUM(tax), 0) as tax,
                    COALESCE(SUM(discount), 0) as discount
                FROM {$this->table}
                WHERE DATE(created_at) BETWEEN :start_date AND :end_date
                AND status = 'completed'
                GROUP BY DATE(created_at)
                ORDER BY date DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
        
        return $stmt->fetchAll();
    }

    /**
     * Obtener totales por método de pago
     */
    public function getTotalsByPaymentMethod(?string $date = null): array
    {
        $sql = "SELECT 
                    payment_method,
                    COUNT(*) as count,
                    COALESCE(SUM(total), 0) as total
                FROM {$this->table}
                WHERE status = 'completed'";
        
        $params = [];
        
        if ($date) {
            $sql .= " AND DATE(created_at) = :date";
            $params['date'] = $date;
        } else {
            $sql .= " AND DATE(created_at) = CURDATE()";
        }
        
        $sql .= " GROUP BY payment_method";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    /**
     * Obtener ventas por usuario
     */
    public function getSalesByUser(string $startDate, string $endDate): array
    {
        $sql = "SELECT 
                    u.id,
                    u.name,
                    COUNT(t.id) as transactions,
                    COALESCE(SUM(t.total), 0) as total
                FROM users u
                LEFT JOIN {$this->table} t ON u.id = t.user_id 
                    AND DATE(t.created_at) BETWEEN :start_date AND :end_date
                    AND t.status = 'completed'
                WHERE u.is_active = 1
                GROUP BY u.id, u.name
                ORDER BY total DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
        
        return $stmt->fetchAll();
    }

    /**
     * Obtener últimas ventas
     */
    public function getLatest(int $limit = 10): array
    {
        $sql = "SELECT t.*, u.name as user_name, c.name as customer_name 
                FROM {$this->table} t 
                LEFT JOIN users u ON t.user_id = u.id 
                LEFT JOIN customers c ON t.customer_id = c.id
                WHERE t.status = 'completed'
                ORDER BY t.created_at DESC 
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}
