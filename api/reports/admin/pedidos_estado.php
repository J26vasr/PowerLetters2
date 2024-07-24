<?php

require_once('../../helpers/report.php');
require_once('../../models/data/libros_data.php');
require_once('../../models/data/pedido_data.php');

$pdf = new Report;
$pdf->startReport('Libros por editorial');

$libros = new LibroData;
if ($dataLibros = $libros->readAll()) {
    $pdf->SetFillColor(27, 88, 169);
    $pdf->SetFont('Times', 'B', 11);
    $pdf->Cell(40, 10, 'Usuario', 1, 0, 'C', 1);
    $pdf->Cell(80, 10, $pdf->encodeString('Titulo'), 1, 0, 'C', 1);
    $pdf->Cell(30, 10, 'Pedido', 1, 0, 'C', 1);
    $pdf->Cell(30, 10, 'Cantidad', 1, 1, 'C', 1);

    // Recorremos los libros obtenidos
    foreach ($dataLibros as $rowLibros) {
        $pedido = new PedidoData;

        // Obtener pedidos solo para el libro actual
        $pedido->setLibro($rowLibros['id_libro']);
        $dataPedidos = $pedido->reportePedido();

        // Verificar si hay pedidos para el libro actual
        if ($dataPedidos) {
            // Usar un array para mantener un registro de libros ya impresos
            $printedBooks = [];
            $printedEntries = []; // Array para almacenar entradas impresas

            foreach ($dataPedidos as $rowPedido) {
                $entryKey = $rowPedido['TituloLibro'] . '|' . $rowPedido['NombreUsuario'] . '|' . $rowPedido['CantidadLibros'];
                
                if (!isset($printedEntries[$entryKey])) {
                    $pdf->SetFont('Arial', '', 10);
                    $pdf->Cell(40, 10, $pdf->encodeString($rowPedido['NombreUsuario']), 1, 0, 'L');
                    $pdf->Cell(80, 10, $pdf->encodeString($rowPedido['TituloLibro']), 1, 0, 'L');
                    $pdf->Cell(30, 10, $rowPedido['EstadoPedido'], 1, 0, 'C');
                    $pdf->Cell(30, 10, $rowPedido['CantidadLibros'], 1, 1, 'C');
            
                    // Agregar la entrada al array de entradas impresas
                    $printedEntries[$entryKey] = true;
                }
            }
        } else {
            // Mostrar mensaje si no hay pedidos para este libro
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(40, 10, '', 1, 0, 'C'); // Celda vacía para el nombre de usuario
            $pdf->Cell(80, 10, $pdf->encodeString($rowLibros['titulo']), 1, 0, 'L'); // Usar el título del libro del bucle actual
            $pdf->Cell(30, 10, 'Sin pedidos', 1, 0, 'C');
            $pdf->Cell(30, 10, '', 1, 1, 'C'); // Celda vacía para la cantidad de libros
        }
    }
} else {
    // Mostrar mensaje si no hay libros para mostrar
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(190, 10, 'No hay libros para mostrar', 1, 1, 'C');
}

$pdf->output('I', 'libros.pdf'); // Salida del PDF
