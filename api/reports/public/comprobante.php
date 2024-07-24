<?php
// Se incluye la clase con las plantillas para generar reportes.
require_once('../../helpers/report.php');
 
// Se instancia la clase para crear el reporte.
$pdf = new Report;
 
// Se verifica si existe un valor para la categoría, de lo contrario se muestra un mensaje.
if (isset($_GET['idPedido'])) {
    // Se incluyen las clases para la transferencia y acceso a datos.
    require_once('../../models/data/pedido_data.php');
    // require_once('../../models/data/productos_data.php');
    // Se instancian las entidades correspondientes.
    $historial = new PedidoData;
 
    // Se establece el valor de la categoría, de lo contrario se muestra un mensaje.
    if ($historial->setId($_GET['idPedido'])) {
 
        if ($rowPedido = $historial->reporteHistorial()) {
 
 
            // Se inicia el reporte con el encabezado del documento.
            $pdf->startReport('Reporte de la compra');
            $item = $rowPedido[0];
 
            // Se establece un color de relleno para los encabezados.
            $pdf->setFillColor(0, 21, 26);
            // Se establece la fuente para los encabezados.
            $pdf->setFont('Times', 'B', 15);
            $pdf->SetTextColor(237, 237, 237);
 
            // Información del cliente
            $pdf->SetFont('Times', '', 20);
            $pdf->Write(10, $pdf->encodeString("Nombre : {$item['nombre_usuario']}"));
            $pdf->Ln(10); // Salto de línea
            $pdf->Write(10, $pdf->encodeString("Apellido : {$item['apellido_usuario']}"));
            $pdf->Ln(10); // Salto de línea
            $pdf->Write(10, $pdf->encodeString("Correo electroníco : {$item['correo_usuario']}"));
            $pdf->Ln(20); // Salto de línea
 
            // Encabezados de la tabla
            $pdf->SetFont('Times', 'B', 12);
            $pdf->cell(90, 10, 'Nombre', 1, 0, 'C', 1);
            $pdf->cell(40, 10, 'Precio (US$)', 1, 0, 'C', 1);
            $pdf->cell(30, 10, 'Cantidad', 1, 0, 'C', 1);
            $pdf->cell(30, 10, 'Fecha de pedido', 1, 1, 'C', 1);
 
            // Se establece la fuente para los datos de los productos.
            $pdf->setFont('Times', '', 15);
 
            // Se recorren los registros fila por fila.
            foreach ($rowPedido as $item) {
                $pdf->cell(90, 10, $pdf->encodeString($item['titulo_libro']), 1, 0);
                $pdf->cell(40, 10, $item['precio_pedido'], 1, 0);
                $pdf->cell(30, 10, $item['cantidad_pedido'], 1, 0);
                $pdf->cell(30, 10, $item['fecha_pedido'], 1, 1);
            }
        } else {
            $pdf->AddPage(); // Añadir una página para el mensaje
            $pdf->cell(0, 10, $pdf->encodeString('No hay comprobante de compra para este registro'), 1, 1);
        }
 
        // Se llama implícitamente al método footer() y se envía el documento al navegador web.
        $pdf->output('I', 'Factura de compra.pdf');
    } else {
        print('ID inexistente');
    }
} else {
    print('Error');
}
 