<?php
/**
 * ElevenPro POS - Response Utility
 * https://elevenpropos.com
 */

namespace App\Utils;

class Response
{
    /**
     * Enviar respuesta JSON exitosa
     */
    public static function success(array $data = [], string $message = 'Success', int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }

    /**
     * Enviar respuesta JSON de error
     */
    public static function error(string $message = 'Error', int $code = 400, array $errors = []): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        
        $response = [
            'success' => false,
            'message' => $message
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        echo json_encode($response);
        exit;
    }

    /**
     * Respuesta para listados paginados
     */
    public static function paginated(array $items, int $total, int $page, int $perPage): void
    {
        $totalPages = ceil($total / $perPage);

        self::success([
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ]);
    }

    /**
     * Respuesta para archivos
     */
    public static function file(string $filePath, string $fileName = null): void
    {
        if (!file_exists($filePath)) {
            self::error('Archivo no encontrado', 404);
        }

        $fileName = $fileName ?? basename($filePath);
        $mimeType = mime_content_type($filePath);

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache, must-revalidate');

        readfile($filePath);
        exit;
    }

    /**
     * Respuesta para PDF
     */
    public static function pdf(string $content, string $fileName = 'document.pdf'): void
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $fileName . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-cache, must-revalidate');

        echo $content;
        exit;
    }

    /**
     * Configurar headers CORS
     */
    public static function setCorsHeaders(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
}
