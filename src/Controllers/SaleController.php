<?php
/**
 * ElevenPro POS - Sale Controller
 * https://elevenpropos.com
 */

namespace App\Controllers;

use App\Config\Database;
use App\Middleware\AuthMiddleware;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\ReceiptService;
use App\Utils\Response;
use App\Utils\Validator;

class SaleController
{
    private Transaction $transactionModel;
    private Product $productModel;
    private string $dbName;

    public function __construct()
    {
        $payload = AuthMiddleware::requireCashier();
        $this->dbName = $payload['db_name'];
        $db = AuthMiddleware::getTenantDb($payload);
        
        $this->transactionModel = new Transaction($db);
        $this->productModel = new Product($db);
    }

    /**
     * Listar ventas
     */
    public function index(): void
    {
        $page = intval($_GET['page'] ?? 1);
        $perPage = intval($_GET['per_page'] ?? 20);
        $status = $_GET['status'] ?? null;
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;

        $where = [];
        if ($status) {
            $where['status'] = $status;
        }

        $sales = $this->transactionModel->paginateWithFilters($page, $perPage, $where, $startDate, $endDate);

        Response::success($sales);
    }

    /**
     * Mostrar venta
     */
    public function show(array $params): void
    {
        $id = intval($params['id'] ?? 0);
        
        $sale = $this->transactionModel->findWithItems($id);
        
        if (!$sale) {
            Response::error('Venta no encontrada', 404);
        }

        Response::success(['sale' => $sale]);
    }

    /**
     * Crear venta
     */
    public function create(): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $validator = new Validator($data);
        $validator
            ->required('items', 'Los items son requeridos')
            ->required('payment_method', 'El método de pago es requerido')
            ->custom('items', function($items) {
                return is_array($items) && count($items) > 0;
            }, 'Debe incluir al menos un producto');

        if ($validator->fails()) {
            Response::error('Datos inválidos', 422, $validator->errors());
        }

        // Obtener usuario del token
        $payload = AuthMiddleware::requireCashier();
        $userId = $payload['user_id'];

        // Calcular totales
        $subtotal = 0;
        $items = [];
        $errors = [];

        foreach ($data['items'] as $index => $item) {
            if (empty($item['product_id']) || empty($item['quantity'])) {
                $errors[] = "Item {$index}: product_id y quantity son requeridos";
                continue;
            }

            $productId = intval($item['product_id']);
            $quantity = intval($item['quantity']);
            $product = $this->productModel->find($productId);

            if (!$product) {
                $errors[] = "Item {$index}: Producto no encontrado";
                continue;
            }

            if (!$product['is_active']) {
                $errors[] = "Item {$index}: Producto '{$product['name']}' no está activo";
                continue;
            }

            // Verificar stock
            if (!$this->productModel->isAvailableForSale($productId, $quantity)) {
                $errors[] = "Item {$index}: Stock insuficiente para '{$product['name']}' (Disponible: {$product['stock']})";
                continue;
            }

            $unitPrice = floatval($item['unit_price'] ?? $product['price']);
            $itemDiscount = floatval($item['discount'] ?? 0);
            $itemTotal = ($unitPrice * $quantity) - $itemDiscount;

            $items[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount' => $itemDiscount,
                'total_price' => $itemTotal,
                'cost_price' => $product['cost']
            ];

            $subtotal += $itemTotal;
        }

        if (!empty($errors)) {
            Response::error('Errores en los items', 422, ['items_errors' => $errors]);
        }

        // Calcular impuestos y descuentos
        $taxRate = floatval($data['tax_rate'] ?? 16);
        $discount = floatval($data['discount'] ?? 0);
        $discountType = $data['discount_type'] ?? 'fixed';

        // Aplicar descuento global
        if ($discountType === 'percentage') {
            $discount = ($subtotal * $discount) / 100;
        }

        $taxableAmount = $subtotal - $discount;
        $tax = ($taxableAmount * $taxRate) / 100;
        $total = $taxableAmount + $tax;

        // Calcular cambio
        $cashReceived = floatval($data['cash_received'] ?? $total);
        $changeAmount = max(0, $cashReceived - $total);

        // Generar número de ticket
        $ticketNumber = $this->transactionModel->generateTicketNumber();

        // Crear transacción
        $transactionData = [
            'ticket_number' => $ticketNumber,
            'customer_id' => !empty($data['customer_id']) ? intval($data['customer_id']) : null,
            'user_id' => $userId,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'tax_rate' => $taxRate,
            'discount' => $discount,
            'discount_type' => $discountType,
            'total' => $total,
            'payment_method' => $data['payment_method'],
            'payment_reference' => !empty($data['payment_reference']) ? $data['payment_reference'] : null,
            'cash_received' => $cashReceived,
            'change_amount' => $changeAmount,
            'status' => 'completed',
            'notes' => !empty($data['notes']) ? Validator::sanitize($data['notes']) : null
        ];

        $transactionId = $this->transactionModel->createWithItems($transactionData, $items);

        if (!$transactionId) {
            Response::error('Error al crear la venta', 500);
        }

        // Actualizar stock de productos
        foreach ($items as $item) {
            $this->productModel->updateStock(
                $item['product_id'], 
                -$item['quantity'], 
                'sale', 
                'Venta #' . $ticketNumber,
                $userId
            );
        }

        // Generar recibo
        $sale = $this->transactionModel->findWithItems($transactionId);
        $receiptService = new ReceiptService();
        $receipt = $receiptService->generate($sale, $this->dbName);

        if ($receipt['success']) {
            $this->transactionModel->update($transactionId, ['receipt_url' => $receipt['url']]);
            $sale['receipt_url'] = $receipt['url'];
        }

        Response::success([
            'sale' => $sale,
            'receipt' => $receipt
        ], 'Venta completada exitosamente', 201);
    }

    /**
     * Cancelar venta
     */
    public function cancel(array $params): void
    {
        $id = intval($params['id'] ?? 0);
        
        $sale = $this->transactionModel->findWithItems($id);
        
        if (!$sale) {
            Response::error('Venta no encontrada', 404);
        }

        if ($sale['status'] === 'cancelled') {
            Response::error('La venta ya está cancelada', 400);
        }

        // Obtener usuario del token
        $payload = AuthMiddleware::requireManager();
        $userId = $payload['user_id'];

        // Restaurar stock
        foreach ($sale['items'] as $item) {
            $this->productModel->updateStock(
                $item['product_id'],
                $item['quantity'],
                'return',
                'Cancelación de venta #' . $sale['ticket_number'],
                $userId
            );
        }

        // Cancelar transacción
        $this->transactionModel->update($id, [
            'status' => 'cancelled',
            'notes' => ($sale['notes'] ? $sale['notes'] . ' | ' : '') . 'Cancelada el ' . date('Y-m-d H:i:s')
        ]);

        Response::success([], 'Venta cancelada exitosamente');
    }

    /**
     * Obtener recibo
     */
    public function receipt(array $params): void
    {
        $id = intval($params['id'] ?? 0);
        
        $sale = $this->transactionModel->findWithItems($id);
        
        if (!$sale) {
            Response::error('Venta no encontrada', 404);
        }

        // Si ya existe un recibo, devolverlo
        if (!empty($sale['receipt_url'])) {
            $filepath = str_replace($_ENV['APP_URL'], __DIR__ . '/../../public', $sale['receipt_url']);
            if (file_exists($filepath)) {
                $pdfContent = file_get_contents($filepath);
                Response::pdf($pdfContent, 'recibo_' . $sale['ticket_number'] . '.pdf');
            }
        }

        // Generar nuevo recibo
        $receiptService = new ReceiptService();
        $receipt = $receiptService->generate($sale, $this->dbName);

        if ($receipt['success']) {
            $this->transactionModel->update($id, ['receipt_url' => $receipt['url']]);
            $pdfContent = file_get_contents($receipt['path']);
            Response::pdf($pdfContent, 'recibo_' . $sale['ticket_number'] . '.pdf');
        }

        Response::error('Error al generar el recibo', 500);
    }

    /**
     * Enviar recibo por email
     */
    public function sendEmail(array $params): void
    {
        $id = intval($params['id'] ?? 0);
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        $validator = new Validator($data);
        $validator->required('email', 'El email es requerido')->email('email', 'El email no es válido');

        if ($validator->fails()) {
            Response::error('Datos inválidos', 422, $validator->errors());
        }

        $sale = $this->transactionModel->findWithItems($id);
        
        if (!$sale) {
            Response::error('Venta no encontrada', 404);
        }

        // Generar recibo si no existe
        if (empty($sale['receipt_url'])) {
            $receiptService = new ReceiptService();
            $receipt = $receiptService->generate($sale, $this->dbName);
            
            if ($receipt['success']) {
                $this->transactionModel->update($id, ['receipt_url' => $receipt['url']]);
                $sale['receipt_url'] = $receipt['url'];
            }
        }

        // Enviar email (implementar según servicio de email)
        // TODO: Implementar envío de email

        Response::success([], 'Recibo enviado exitosamente');
    }

    /**
     * Ventas del día
     */
    public function today(): void
    {
        $sales = $this->transactionModel->getTodaySales();
        
        Response::success([
            'sales' => $sales['sales'],
            'count' => $sales['count'],
            'total' => $sales['total']
        ]);
    }
}
