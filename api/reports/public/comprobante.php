<?php

require_once('../../helpers/report_public.php');
require_once('../../models/data/libros_data.php');
require_once('../../models/data/pedido_data.php');

$pdf = new Report;
$pdf->startReport('Comprobante de compra');


$libros = new LibroData;
if ($dataLibros = $libros->readAll()) {
    $pdf->SetFillColor(27, 88, 169);
    $pdf->SetFont('Times', 'B', 11);
    $pdf->Cell(60, 10, $pdf->encodeString('Titulo'), 1, 0, 'C', 1);
    $pdf->Cell(30, 10, 'Pedido', 1, 0, 'C', 1);
    $pdf->Cell(30, 10, 'Cantidad', 1, 0, 'C', 1);
    $pdf->Cell(30, 10, 'Subtotal', 1, 0, 'C', 1);
    $pdf->Cell(40, 10, 'Fecha', 1, 1, 'C', 1);

    $printedEntries = []; // Array para almacenar entradas impresas

    foreach ($dataLibros as $rowLibros) {

        $pedido = new PedidoData;
        $pedido->setLibro($rowLibros['id_libro']);
        $dataPedidos = $pedido->reporteHistorial();

        if ($dataPedidos) {
         
            foreach ($dataPedidos as $rowPedido) {

                $entryKey = $rowPedido['TituloLibro'] . '|' . $rowPedido['NombreUsuario'] . '|' . $rowPedido['CantidadLibros'];

                if (!isset($printedEntries[$entryKey])) {
                    $pdf->SetFont('Arial', '', 10);
                    $pdf->Cell(60, 10, $pdf->encodeString($rowPedido['TituloLibro']), 1, 0, 'L');
                    $pdf->Cell(30, 10, $rowPedido['EstadoPedido'], 1, 0, 'C');
                    $pdf->Cell(30, 10, $rowPedido['CantidadLibros'], 1, 0, 'C');
                    $pdf->Cell(30, 10, $rowPedido['subtotal'], 1, 0, 'C');
                    $pdf->Cell(40, 10, $rowPedido['FechaPedido'], 1, 1, 'C');

                    $printedEntries[$entryKey] = true;
                }
            }
        }
    }
} else {
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(190, 10, 'No hay libros para mostrar', 1, 1, 'C');
}

$pdf->output('I', 'libros.pdf');
