<?php
/**
 * ElevenPro POS - API Router
 * https://elevenpropos.com
 */

// Configurar CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Cargar autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

// Cargar variables de entorno
if (file_exists(__DIR__ . '/../../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
}

// Inicializar JWT
\App\Config\JWTConfig::init();

// Obtener ruta y método
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace('/api/', '', $uri);
$uri = trim($uri, '/');
$method = $_SERVER['REQUEST_METHOD'];

// Router
$routes = [
    // Auth routes
    'auth/login' => ['POST', 'AuthController@loginUser'],
    'auth/login-tenant' => ['POST', 'AuthController@loginTenant'],
    'auth/register' => ['POST', 'AuthController@registerTenant'],
    'auth/verify' => ['GET', 'AuthController@verifyToken'],
    'auth/refresh' => ['POST', 'AuthController@refreshToken'],
    'auth/logout' => ['POST', 'AuthController@logout'],
    'auth/change-password' => ['POST', 'AuthController@changePassword'],

    // Dashboard
    'dashboard' => ['GET', 'DashboardController@index'],
    'dashboard/stats' => ['GET', 'DashboardController@stats'],

    // Products
    'products' => ['GET', 'ProductController@index', 'cashier'],
    'products/create' => ['POST', 'ProductController@create', 'manager'],
    'products/search' => ['GET', 'ProductController@search', 'cashier'],
    'products/barcode' => ['GET', 'ProductController@byBarcode', 'cashier'],
    'products/:id' => ['GET', 'ProductController@show', 'cashier'],
    'products/:id/update' => ['PUT', 'ProductController@update', 'manager'],
    'products/:id/delete' => ['DELETE', 'ProductController@delete', 'admin'],
    'products/:id/image' => ['POST', 'ProductController@uploadImage', 'manager'],
    'products/import' => ['POST', 'ProductController@import', 'admin'],
    'products/export' => ['GET', 'ProductController@export', 'manager'],

    // Categories
    'categories' => ['GET', 'CategoryController@index', 'cashier'],
    'categories/create' => ['POST', 'CategoryController@create', 'manager'],
    'categories/:id' => ['GET', 'CategoryController@show', 'cashier'],
    'categories/:id/update' => ['PUT', 'CategoryController@update', 'manager'],
    'categories/:id/delete' => ['DELETE', 'CategoryController@delete', 'admin'],

    // Customers
    'customers' => ['GET', 'CustomerController@index', 'cashier'],
    'customers/create' => ['POST', 'CustomerController@create', 'cashier'],
    'customers/search' => ['GET', 'CustomerController@search', 'cashier'],
    'customers/:id' => ['GET', 'CustomerController@show', 'cashier'],
    'customers/:id/update' => ['PUT', 'CustomerController@update', 'manager'],
    'customers/:id/delete' => ['DELETE', 'CustomerController@delete', 'admin'],

    // Sales/Transactions
    'sales' => ['GET', 'SaleController@index', 'cashier'],
    'sales/create' => ['POST', 'SaleController@create', 'cashier'],
    'sales/:id' => ['GET', 'SaleController@show', 'cashier'],
    'sales/:id/cancel' => ['POST', 'SaleController@cancel', 'manager'],
    'sales/:id/receipt' => ['GET', 'SaleController@receipt', 'cashier'],
    'sales/:id/email' => ['POST', 'SaleController@sendEmail', 'cashier'],
    'sales/today' => ['GET', 'SaleController@today', 'cashier'],

    // Inventory
    'inventory' => ['GET', 'InventoryController@index', 'manager'],
    'inventory/adjust' => ['POST', 'InventoryController@adjust', 'manager'],
    'inventory/history' => ['GET', 'InventoryController@history', 'manager'],
    'inventory/low-stock' => ['GET', 'InventoryController@lowStock', 'manager'],

    // Users
    'users' => ['GET', 'UserController@index', 'admin'],
    'users/create' => ['POST', 'UserController@create', 'admin'],
    'users/:id' => ['GET', 'UserController@show', 'admin'],
    'users/:id/update' => ['PUT', 'UserController@update', 'admin'],
    'users/:id/delete' => ['DELETE', 'UserController@delete', 'admin'],
    'users/:id/toggle' => ['POST', 'UserController@toggle', 'admin'],

    // Reports
    'reports/sales' => ['GET', 'ReportController@sales', 'manager'],
    'reports/products' => ['GET', 'ReportController@products', 'manager'],
    'reports/inventory' => ['GET', 'ReportController@inventory', 'manager'],
    'reports/export' => ['GET', 'ReportController@export', 'manager'],

    // Cash Register
    'cash-register/status' => ['GET', 'CashRegisterController@status', 'cashier'],
    'cash-register/open' => ['POST', 'CashRegisterController@open', 'cashier'],
    'cash-register/close' => ['POST', 'CashRegisterController@close', 'cashier'],
    'cash-register/history' => ['GET', 'CashRegisterController@history', 'manager'],

    // Settings
    'settings' => ['GET', 'SettingController@index', 'admin'],
    'settings/update' => ['PUT', 'SettingController@update', 'admin'],
    'settings/logo' => ['POST', 'SettingController@uploadLogo', 'admin'],
];

// Encontrar ruta
$matchedRoute = null;
$params = [];

foreach ($routes as $route => $config) {
    $routeMethod = $config[0];
    $routePattern = preg_replace('/:([a-zA-Z_]+)/', '([^/]+)', $route);
    $routePattern = '#^' . $routePattern . '$#';

    if ($method === $routeMethod && preg_match($routePattern, $uri, $matches)) {
        $matchedRoute = $config;
        
        // Extraer parámetros
        preg_match_all('/:([a-zA-Z_]+)/', $route, $paramNames);
        for ($i = 0; $i < count($paramNames[1]); $i++) {
            $params[$paramNames[1][$i]] = $matches[$i + 1];
        }
        break;
    }
}

// Si no se encontró la ruta
if (!$matchedRoute) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Ruta no encontrada']);
    exit;
}

// Verificar permisos si es necesario
if (isset($matchedRoute[2])) {
    $requiredRole = $matchedRoute[2];
    
    switch ($requiredRole) {
        case 'admin':
            $payload = \App\Middleware\AuthMiddleware::requireAdmin();
            break;
        case 'manager':
            $payload = \App\Middleware\AuthMiddleware::requireManager();
            break;
        case 'cashier':
            $payload = \App\Middleware\AuthMiddleware::requireCashier();
            break;
        default:
            $payload = \App\Middleware\AuthMiddleware::authenticate();
    }
}

// Ejecutar controlador
$controllerAction = $matchedRoute[1];
list($controllerName, $action) = explode('@', $controllerAction);

$controllerClass = 'App\\Controllers\\' . $controllerName;

if (!class_exists($controllerClass)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Controlador no encontrado']);
    exit;
}

$controller = new $controllerClass();

if (!method_exists($controller, $action)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Acción no encontrada']);
    exit;
}

// Llamar al método del controlador
try {
    $controller->$action($params);
} catch (\Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $_ENV['APP_DEBUG'] === 'true' ? $e->getMessage() : null
    ]);
}
