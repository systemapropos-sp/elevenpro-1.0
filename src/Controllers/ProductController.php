<?php
/**
 * ElevenPro POS - Product Controller
 * https://elevenpropos.com
 */

namespace App\Controllers;

use App\Config\Database;
use App\Middleware\AuthMiddleware;
use App\Models\Product;
use App\Services\FileUploadService;
use App\Utils\Response;
use App\Utils\Validator;

class ProductController
{
    private Product $productModel;

    public function __construct()
    {
        // Obtener conexión del tenant
        $payload = AuthMiddleware::requireUser();
        $db = AuthMiddleware::getTenantDb($payload);
        $this->productModel = new Product($db);
    }

    /**
     * Listar productos
     */
    public function index(): void
    {
        $page = intval($_GET['page'] ?? 1);
        $perPage = intval($_GET['per_page'] ?? 20);
        $category = $_GET['category'] ?? null;
        $search = $_GET['search'] ?? null;
        $lowStock = isset($_GET['low_stock']) ? true : false;

        $where = [];
        
        if ($category) {
            $where['category_id'] = intval($category);
        }

        $products = $this->productModel->paginate($page, $perPage, $where, ['name' => 'ASC']);

        // Filtrar por búsqueda si se especifica
        if ($search) {
            $products['items'] = $this->productModel->search($search);
            $products['total'] = count($products['items']);
        }

        // Filtrar por stock bajo
        if ($lowStock) {
            $products['items'] = $this->productModel->getLowStock();
            $products['total'] = count($products['items']);
        }

        Response::success($products);
    }

    /**
     * Mostrar producto
     */
    public function show(array $params): void
    {
        $id = intval($params['id'] ?? 0);
        
        $product = $this->productModel->find($id);
        
        if (!$product) {
            Response::error('Producto no encontrado', 404);
        }

        Response::success(['product' => $product]);
    }

    /**
     * Buscar productos
     */
    public function search(): void
    {
        $query = $_GET['q'] ?? '';
        
        if (empty($query)) {
            Response::error('Query de búsqueda requerida', 400);
        }

        $products = $this->productModel->search($query);
        
        Response::success(['products' => $products]);
    }

    /**
     * Buscar por código de barras
     */
    public function byBarcode(): void
    {
        $barcode = $_GET['code'] ?? '';
        
        if (empty($barcode)) {
            Response::error('Código de barras requerido', 400);
        }

        $product = $this->productModel->findByBarcode($barcode);
        
        if (!$product) {
            Response::error('Producto no encontrado', 404);
        }

        Response::success(['product' => $product]);
    }

    /**
     * Crear producto
     */
    public function create(): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $validator = new Validator($data);
        $validator
            ->required('sku', 'El SKU es requerido')
            ->maxLength('sku', 50, 'El SKU no debe exceder 50 caracteres')
            ->required('name', 'El nombre es requerido')
            ->maxLength('name', 255, 'El nombre no debe exceder 255 caracteres')
            ->required('price', 'El precio es requerido')
            ->numeric('price', 'El precio debe ser un número')
            ->positive('price', 'El precio debe ser positivo');

        if ($validator->fails()) {
            Response::error('Datos inválidos', 422, $validator->errors());
        }

        // Verificar SKU único
        if ($this->productModel->findBySku($data['sku'])) {
            Response::error('El SKU ya existe', 409);
        }

        // Sanitizar datos
        $productData = [
            'sku' => Validator::sanitize($data['sku']),
            'name' => Validator::sanitize($data['name']),
            'description' => !empty($data['description']) ? Validator::sanitize($data['description']) : null,
            'category_id' => !empty($data['category_id']) ? intval($data['category_id']) : null,
            'price' => floatval($data['price']),
            'cost' => !empty($data['cost']) ? floatval($data['cost']) : 0,
            'stock' => !empty($data['stock']) ? intval($data['stock']) : 0,
            'min_stock' => !empty($data['min_stock']) ? intval($data['min_stock']) : 5,
            'barcode' => !empty($data['barcode']) ? Validator::sanitize($data['barcode']) : null,
            'unit' => !empty($data['unit']) ? Validator::sanitize($data['unit']) : 'pieza',
            'is_active' => isset($data['is_active']) ? intval($data['is_active']) : 1
        ];

        $id = $this->productModel->create($productData);

        if (!$id) {
            Response::error('Error al crear el producto', 500);
        }

        $product = $this->productModel->find($id);

        Response::success(['product' => $product], 'Producto creado exitosamente', 201);
    }

    /**
     * Actualizar producto
     */
    public function update(array $params): void
    {
        $id = intval($params['id'] ?? 0);
        
        $product = $this->productModel->find($id);
        if (!$product) {
            Response::error('Producto no encontrado', 404);
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $validator = new Validator($data);
        
        if (isset($data['name'])) {
            $validator->maxLength('name', 255, 'El nombre no debe exceder 255 caracteres');
        }
        if (isset($data['price'])) {
            $validator->numeric('price', 'El precio debe ser un número')->positive('price', 'El precio debe ser positivo');
        }

        if ($validator->fails()) {
            Response::error('Datos inválidos', 422, $validator->errors());
        }

        // Sanitizar datos
        $updateData = [];
        if (isset($data['name'])) $updateData['name'] = Validator::sanitize($data['name']);
        if (isset($data['description'])) $updateData['description'] = Validator::sanitize($data['description']);
        if (isset($data['category_id'])) $updateData['category_id'] = intval($data['category_id']);
        if (isset($data['price'])) $updateData['price'] = floatval($data['price']);
        if (isset($data['cost'])) $updateData['cost'] = floatval($data['cost']);
        if (isset($data['stock'])) $updateData['stock'] = intval($data['stock']);
        if (isset($data['min_stock'])) $updateData['min_stock'] = intval($data['min_stock']);
        if (isset($data['barcode'])) $updateData['barcode'] = Validator::sanitize($data['barcode']);
        if (isset($data['unit'])) $updateData['unit'] = Validator::sanitize($data['unit']);
        if (isset($data['is_active'])) $updateData['is_active'] = intval($data['is_active']);

        $success = $this->productModel->update($id, $updateData);

        if (!$success) {
            Response::error('Error al actualizar el producto', 500);
        }

        $product = $this->productModel->find($id);

        Response::success(['product' => $product], 'Producto actualizado exitosamente');
    }

    /**
     * Eliminar producto
     */
    public function delete(array $params): void
    {
        $id = intval($params['id'] ?? 0);
        
        $product = $this->productModel->find($id);
        if (!$product) {
            Response::error('Producto no encontrado', 404);
        }

        $success = $this->productModel->delete($id);

        if (!$success) {
            Response::error('Error al eliminar el producto', 500);
        }

        Response::success([], 'Producto eliminado exitosamente');
    }

    /**
     * Subir imagen de producto
     */
    public function uploadImage(array $params): void
    {
        $id = intval($params['id'] ?? 0);
        
        $product = $this->productModel->find($id);
        if (!$product) {
            Response::error('Producto no encontrado', 404);
        }

        if (!isset($_FILES['image'])) {
            Response::error('No se proporcionó ninguna imagen', 400);
        }

        $payload = AuthMiddleware::requireUser();
        $uploadService = new FileUploadService();
        
        $result = $uploadService->uploadProductImage($_FILES['image'], $payload['tenant_id'] ?? 'default');

        if (!$result['success']) {
            Response::error($result['message'], 400);
        }

        // Actualizar producto con URL de imagen
        $this->productModel->update($id, ['image_url' => $result['url']]);

        Response::success(['image_url' => $result['url']], 'Imagen subida exitosamente');
    }

    /**
     * Importar productos desde CSV
     */
    public function import(): void
    {
        if (!isset($_FILES['file'])) {
            Response::error('No se proporcionó ningún archivo', 400);
        }

        $file = $_FILES['file'];
        
        if ($file['type'] !== 'text/csv' && !str_ends_with($file['name'], '.csv')) {
            Response::error('El archivo debe ser CSV', 400);
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            Response::error('Error al leer el archivo', 500);
        }

        // Leer encabezados
        $headers = fgetcsv($handle);
        if (!$headers) {
            Response::error('Archivo CSV vacío o inválido', 400);
        }

        $imported = 0;
        $errors = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $data = array_combine($headers, $row);

            // Validar datos mínimos
            if (empty($data['sku']) || empty($data['name']) || empty($data['price'])) {
                $errors[] = "Fila {$rowNumber}: SKU, nombre y precio son requeridos";
                continue;
            }

            // Verificar SKU único
            if ($this->productModel->findBySku($data['sku'])) {
                $errors[] = "Fila {$rowNumber}: SKU '{$data['sku']}' ya existe";
                continue;
            }

            $productData = [
                'sku' => Validator::sanitize($data['sku']),
                'name' => Validator::sanitize($data['name']),
                'description' => !empty($data['description']) ? Validator::sanitize($data['description']) : null,
                'category_id' => !empty($data['category_id']) ? intval($data['category_id']) : null,
                'price' => floatval($data['price']),
                'cost' => !empty($data['cost']) ? floatval($data['cost']) : 0,
                'stock' => !empty($data['stock']) ? intval($data['stock']) : 0,
                'min_stock' => !empty($data['min_stock']) ? intval($data['min_stock']) : 5,
                'barcode' => !empty($data['barcode']) ? Validator::sanitize($data['barcode']) : null,
                'unit' => !empty($data['unit']) ? Validator::sanitize($data['unit']) : 'pieza',
                'is_active' => 1
            ];

            $id = $this->productModel->create($productData);
            if ($id) {
                $imported++;
            } else {
                $errors[] = "Fila {$rowNumber}: Error al crear producto";
            }
        }

        fclose($handle);

        Response::success([
            'imported' => $imported,
            'errors' => $errors
        ], "{$imported} productos importados exitosamente");
    }

    /**
     * Exportar productos a CSV
     */
    public function export(): void
    {
        $products = $this->productModel->all(['name' => 'ASC']);

        $filename = 'productos_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        
        // Encabezados
        fputcsv($output, ['sku', 'name', 'description', 'category_id', 'price', 'cost', 'stock', 'min_stock', 'barcode', 'unit', 'is_active']);

        // Datos
        foreach ($products as $product) {
            fputcsv($output, [
                $product['sku'],
                $product['name'],
                $product['description'],
                $product['category_id'],
                $product['price'],
                $product['cost'],
                $product['stock'],
                $product['min_stock'],
                $product['barcode'],
                $product['unit'],
                $product['is_active']
            ]);
        }

        fclose($output);
        exit;
    }
}
