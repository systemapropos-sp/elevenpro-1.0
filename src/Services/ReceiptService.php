<?php
/**
 * ElevenPro POS - Receipt Service
 * https://elevenpropos.com
 */

namespace App\Services;

use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

class ReceiptService
{
    private FileUploadService $fileUploadService;

    public function __construct()
    {
        $this->fileUploadService = new FileUploadService();
    }

    /**
     * Generar recibo PDF
     */
    public function generate(array $sale, string $tenantId): array
    {
        try {
            // Configurar mPDF
            $defaultConfig = (new ConfigVariables())->getDefaults();
            $fontDirs = $defaultConfig['fontDir'];
            
            $defaultFontConfig = (new FontVariables())->getDefaults();
            $fontData = $defaultFontConfig['fontdata'];

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => [80, 200], // Formato ticket térmico
                'margin_left' => 2,
                'margin_right' => 2,
                'margin_top' => 2,
                'margin_bottom' => 2,
                'fontDir' => array_merge($fontDirs, [
                    __DIR__ . '/../../fonts',
                ]),
                'fontdata' => $fontData + [
                    'roboto' => [
                        'R' => 'Roboto-Regular.ttf',
                        'B' => 'Roboto-Bold.ttf',
                    ]
                ],
                'default_font' => 'roboto'
            ]);

            // Generar HTML del recibo
            $html = $this->generateReceiptHtml($sale);

            // Escribir HTML
            $mpdf->WriteHTML($html);

            // Generar PDF
            $pdfContent = $mpdf->Output('', 'S');

            // Guardar archivo
            $filename = 'recibo_' . $sale['ticket_number'];
            $result = $this->fileUploadService->uploadReceipt($pdfContent, $tenantId, $filename);

            return $result;

        } catch (\Exception $e) {
            error_log("Error generating receipt: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al generar el recibo'
            ];
        }
    }

    /**
     * Generar HTML del recibo
     */
    private function generateReceiptHtml(array $sale): string
    {
        $businessName = $_ENV['APP_NAME'] ?? 'ElevenPro POS';
        $businessAddress = '';
        $businessPhone = '';
        
        $date = date('d/m/Y H:i', strtotime($sale['created_at']));
        $ticketNumber = $sale['ticket_number'];
        
        $itemsHtml = '';
        foreach ($sale['items'] as $item) {
            $itemsHtml .= '
                <tr>
                    <td style="text-align: left;">' . htmlspecialchars($item['product_name']) . '</td>
                    <td style="text-align: center;">' . $item['quantity'] . '</td>
                    <td style="text-align: right;">$' . number_format($item['unit_price'], 2) . '</td>
                    <td style="text-align: right;">$' . number_format($item['total_price'], 2) . '</td>
                </tr>
            ';
        }

        $paymentMethodLabels = [
            'cash' => 'Efectivo',
            'card' => 'Tarjeta',
            'transfer' => 'Transferencia',
            'credit' => 'Crédito',
            'mixed' => 'Mixto'
        ];

        $paymentMethod = $paymentMethodLabels[$sale['payment_method']] ?? $sale['payment_method'];

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: roboto, sans-serif; font-size: 10px; }
                .header { text-align: center; margin-bottom: 10px; }
                .business-name { font-size: 14px; font-weight: bold; }
                .ticket-info { margin-bottom: 10px; }
                .ticket-info table { width: 100%; }
                .items { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
                .items th { border-bottom: 1px solid #000; padding: 3px; }
                .items td { padding: 3px; }
                .totals { width: 100%; margin-top: 10px; }
                .totals td { padding: 2px 0; }
                .total-row { font-weight: bold; font-size: 12px; }
                .footer { text-align: center; margin-top: 15px; font-size: 9px; }
                .divider { border-top: 1px dashed #000; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="business-name">' . htmlspecialchars($businessName) . '</div>
                <div>' . htmlspecialchars($businessAddress) . '</div>
                <div>' . htmlspecialchars($businessPhone) . '</div>
            </div>
            
            <div class="divider"></div>
            
            <div class="ticket-info">
                <table>
                    <tr>
                        <td><strong>Ticket:</strong> ' . $ticketNumber . '</td>
                        <td style="text-align: right;">' . $date . '</td>
                    </tr>
                    <tr>
                        <td colspan="2"><strong>Cajero:</strong> ' . htmlspecialchars($sale['user']['name'] ?? 'N/A') . '</td>
                    </tr>
                    ' . ($sale['customer'] ? '
                    <tr>
                        <td colspan="2"><strong>Cliente:</strong> ' . htmlspecialchars($sale['customer']['name']) . '</td>
                    </tr>
                    ' : '') . '
                </table>
            </div>
            
            <div class="divider"></div>
            
            <table class="items">
                <thead>
                    <tr>
                        <th style="text-align: left;">Producto</th>
                        <th style="text-align: center;">Cant.</th>
                        <th style="text-align: right;">P.Unit</th>
                        <th style="text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $itemsHtml . '
                </tbody>
            </table>
            
            <div class="divider"></div>
            
            <table class="totals">
                <tr>
                    <td>Subtotal:</td>
                    <td style="text-align: right;">$' . number_format($sale['subtotal'], 2) . '</td>
                </tr>
                <tr>
                    <td>Descuento:</td>
                    <td style="text-align: right;">$' . number_format($sale['discount'], 2) . '</td>
                </tr>
                <tr>
                    <td>Impuesto (' . $sale['tax_rate'] . '%):</td>
                    <td style="text-align: right;">$' . number_format($sale['tax'], 2) . '</td>
                </tr>
                <tr class="total-row">
                    <td>TOTAL:</td>
                    <td style="text-align: right;">$' . number_format($sale['total'], 2) . '</td>
                </tr>
                <tr>
                    <td colspan="2" style="height: 10px;"></td>
                </tr>
                <tr>
                    <td>Método de pago:</td>
                    <td style="text-align: right;">' . $paymentMethod . '</td>
                </tr>
                ' . ($sale['cash_received'] > 0 ? '
                <tr>
                    <td>Efectivo recibido:</td>
                    <td style="text-align: right;">$' . number_format($sale['cash_received'], 2) . '</td>
                </tr>
                <tr>
                    <td>Cambio:</td>
                    <td style="text-align: right;">$' . number_format($sale['change_amount'], 2) . '</td>
                </tr>
                ' : '') . '
            </table>
            
            <div class="divider"></div>
            
            <div class="footer">
                <p>Gracias por su compra!</p>
                <p>ElevenPro POS - https://elevenpropos.com</p>
            </div>
        </body>
        </html>
        ';

        return $html;
    }

    /**
     * Generar recibo para impresora térmica ESC/POS
     */
    public function generateEscPos(array $sale): string
    {
        $esc = chr(27);
        $gs = chr(29);
        
        $commands = '';
        
        // Inicializar impresora
        $commands .= $esc . '@';
        
        // Centrar
        $commands .= $esc . 'a' . chr(1);
        
        // Nombre del negocio (doble altura)
        $commands .= $esc . '!' . chr(16);
        $commands .= $_ENV['APP_NAME'] ?? 'ElevenPro POS';
        $commands .= "\n";
        
        // Normal
        $commands .= $esc . '!' . chr(0);
        $commands .= "\n";
        
        // Ticket y fecha
        $commands .= 'Ticket: ' . $sale['ticket_number'] . "\n";
        $commands .= date('d/m/Y H:i', strtotime($sale['created_at'])) . "\n";
        $commands .= 'Cajero: ' . ($sale['user']['name'] ?? 'N/A') . "\n";
        $commands .= "\n";
        
        // Items
        $commands .= str_repeat('-', 32) . "\n";
        foreach ($sale['items'] as $item) {
            $name = substr($item['product_name'], 0, 20);
            $commands .= $name . "\n";
            $commands .= sprintf("  %3d x $%8.2f = $%8.2f\n", 
                $item['quantity'], 
                $item['unit_price'], 
                $item['total_price']
            );
        }
        $commands .= str_repeat('-', 32) . "\n";
        
        // Totales
        $commands .= sprintf("Subtotal:      $%10.2f\n", $sale['subtotal']);
        $commands .= sprintf("Descuento:     $%10.2f\n", $sale['discount']);
        $commands .= sprintf("Impuesto:      $%10.2f\n", $sale['tax']);
        $commands .= sprintf("TOTAL:         $%10.2f\n", $sale['total']);
        $commands .= "\n";
        
        // Pie
        $commands .= "Gracias por su compra!\n";
        $commands .= "\n\n\n";
        
        // Cortar papel
        $commands .= $gs . 'V' . chr(1);
        
        return $commands;
    }
}
