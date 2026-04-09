<?php
/**
 * ElevenPro POS - File Upload Service
 * https://elevenpropos.com
 */

namespace App\Services;

class FileUploadService
{
    private array $allowedImageTypes = ['image/jpeg', 'image/png', 'image/webp'];
    private array $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    private int $maxFileSize;
    private string $uploadBasePath;

    public function __construct()
    {
        $this->maxFileSize = intval($_ENV['UPLOAD_MAX_SIZE'] ?? 5242880); // 5MB default
        $this->uploadBasePath = __DIR__ . '/../../uploads';
    }

    /**
     * Subir imagen de producto
     */
    public function uploadProductImage(array $file, string $tenantId): array
    {
        return $this->uploadImage($file, $tenantId, 'products');
    }

    /**
     * Subir logo del negocio
     */
    public function uploadLogo(array $file, string $tenantId): array
    {
        return $this->uploadImage($file, $tenantId, 'logos');
    }

    /**
     * Subir avatar de usuario
     */
    public function uploadAvatar(array $file, string $tenantId): array
    {
        return $this->uploadImage($file, $tenantId, 'avatars');
    }

    /**
     * Subir imagen genérica
     */
    private function uploadImage(array $file, string $tenantId, string $folder): array
    {
        // Validar errores de subida
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'message' => $this->getUploadError($file['error'])
            ];
        }

        // Validar tipo de archivo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $this->allowedImageTypes)) {
            return [
                'success' => false,
                'message' => 'Tipo de archivo no permitido. Solo se permiten imágenes JPG, PNG y WebP'
            ];
        }

        // Validar extensión
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            return [
                'success' => false,
                'message' => 'Extensión de archivo no permitida'
            ];
        }

        // Validar tamaño
        if ($file['size'] > $this->maxFileSize) {
            return [
                'success' => false,
                'message' => 'El archivo excede el tamaño máximo permitido (' . ($this->maxFileSize / 1024 / 1024) . ' MB)'
            ];
        }

        // Crear directorio si no existe
        $uploadPath = $this->uploadBasePath . '/' . $tenantId . '/' . $folder;
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        // Generar nombre único
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $filepath = $uploadPath . '/' . $filename;

        // Mover archivo
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return [
                'success' => false,
                'message' => 'Error al guardar el archivo'
            ];
        }

        // Generar URL
        $url = $_ENV['APP_URL'] . '/uploads/' . $tenantId . '/' . $folder . '/' . $filename;

        return [
            'success' => true,
            'url' => $url,
            'filename' => $filename,
            'path' => $filepath
        ];
    }

    /**
     * Subir recibo PDF
     */
    public function uploadReceipt(string $pdfContent, string $tenantId, string $filename): array
    {
        $uploadPath = $this->uploadBasePath . '/' . $tenantId . '/receipts';
        
        // Crear directorio si no existe
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        // Sanitizar nombre de archivo
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
        $filepath = $uploadPath . '/' . $filename . '.pdf';

        // Guardar archivo
        if (file_put_contents($filepath, $pdfContent) === false) {
            return [
                'success' => false,
                'message' => 'Error al guardar el recibo'
            ];
        }

        // Generar URL
        $url = $_ENV['APP_URL'] . '/uploads/' . $tenantId . '/receipts/' . $filename . '.pdf';

        return [
            'success' => true,
            'url' => $url,
            'filename' => $filename . '.pdf',
            'path' => $filepath
        ];
    }

    /**
     * Eliminar archivo
     */
    public function deleteFile(string $url): bool
    {
        // Extraer ruta del URL
        $path = str_replace($_ENV['APP_URL'], $this->uploadBasePath, $url);
        $path = str_replace('/uploads/', '', $path);
        $filepath = $this->uploadBasePath . '/' . $path;

        if (file_exists($filepath)) {
            return unlink($filepath);
        }

        return false;
    }

    /**
     * Obtener mensaje de error de subida
     */
    private function getUploadError(int $errorCode): string
    {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por el servidor',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo permitido por el formulario',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en el disco',
            UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la subida del archivo'
        ];

        return $errors[$errorCode] ?? 'Error desconocido al subir el archivo';
    }

    /**
     * Redimensionar imagen
     */
    public function resizeImage(string $filepath, int $maxWidth, int $maxHeight): bool
    {
        if (!extension_loaded('gd')) {
            return false;
        }

        list($width, $height, $type) = getimagesize($filepath);

        // Calcular nuevas dimensiones manteniendo proporción
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = intval($width * $ratio);
        $newHeight = intval($height * $ratio);

        // Crear imagen según tipo
        switch ($type) {
            case IMAGETYPE_JPEG:
                $srcImage = imagecreatefromjpeg($filepath);
                break;
            case IMAGETYPE_PNG:
                $srcImage = imagecreatefrompng($filepath);
                break;
            case IMAGETYPE_WEBP:
                $srcImage = imagecreatefromwebp($filepath);
                break;
            default:
                return false;
        }

        // Crear imagen redimensionada
        $dstImage = imagecreatetruecolor($newWidth, $newHeight);

        // Preservar transparencia para PNG
        if ($type == IMAGETYPE_PNG) {
            imagealphablending($dstImage, false);
            imagesavealpha($dstImage, true);
        }

        imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Guardar imagen
        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($dstImage, $filepath, 85);
                break;
            case IMAGETYPE_PNG:
                imagepng($dstImage, $filepath, 8);
                break;
            case IMAGETYPE_WEBP:
                imagewebp($dstImage, $filepath, 85);
                break;
        }

        imagedestroy($srcImage);
        imagedestroy($dstImage);

        return true;
    }
}
