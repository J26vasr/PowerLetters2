<?php

require_once('../../helpers/report.php');
require_once('../../models/data/libros_data.php');
require_once('../../models/data/editoriales_data.php');

$pdf = new Report;

$pdf->startReport('Libros por editorial');

$editorial = new EditorialData;
if ($dataEditorial = $editorial->readAll()) {
    $pdf->SetFillColor(27, 88, 169);
    $pdf->SetFont('Times', 'B', 11);
    $pdf->Cell(50, 10, 'Titulo', 1, 0, 'C', 1);
    $pdf->Cell(80, 10, $pdf->encodeString('Descripción'), 1, 0, 'C', 1);
    $pdf->Cell(30, 10, 'Existencias', 1, 0, 'C', 1);
    $pdf->Cell(30, 10, 'Precio (US$)', 1, 1, 'C', 1);
    
    // Recorremos las editoriales obtenidas
    foreach ($dataEditorial as $rowEditorial) {
        $pdf->SetFont('Times', 'B', 11);
        $pdf->Cell(190, 10, 'Nombre de la editorial: ' . $pdf->encodeString($rowEditorial['nombre']), 1, 1, 'C', 1);
        
        // Creamos una instancia del modelo de Libro para procesar los datos
        $libros = new LibroData;
        
        // Establecemos la editorial para obtener sus libros
        if ($libros->setEditorial($rowEditorial['id_editorial'])) {
            // Verificamos si hay libros para mostrar
            if ($dataLibros = $libros->reporteLibrosE()) {
                foreach ($dataLibros as $rowLibro) {
                    // Guardar posición Y actual
                    $yStart = $pdf->GetY();
                    // Guardar posición X antes de la multicelda para título
                    $xStart = $pdf->GetX();
                
                    // MultiCell para título
                    $pdf->MultiCell(50, 10, $pdf->encodeString($rowLibro['titulo']), 1, 'L');
                    // Obtener la altura de la MultiCell para título
                    $multiCellHeightTitulo = $pdf->GetY() - $yStart;
                
                    // Volver a la posición X inicial y Y inicial
                    $pdf->SetXY($xStart + 50, $yStart);
                
                    // MultiCell para descripción
                    $pdf->MultiCell(80, 10, $pdf->encodeString($rowLibro['descripcion']), 1, 'L');
                    // Obtener la altura de la MultiCell para descripción
                    $multiCellHeightDescripcion = $pdf->GetY() - $yStart;
                
                    // Volver a la posición X inicial y Y inicial
                    $pdf->SetXY($xStart + 130, $yStart);
                
                    // Cell para existencias
                    $pdf->Cell(30, max($multiCellHeightTitulo, $multiCellHeightDescripcion), $rowLibro['existencias'], 1, 0, 'C');
                
                    // Cell para precio
                    $pdf->Cell(30, max($multiCellHeightTitulo, $multiCellHeightDescripcion), $rowLibro['precio'], 1, 1, 'C');
                
                    // Ajustar la posición Y para la siguiente fila
                    $pdf->SetY($yStart + max($multiCellHeightTitulo, $multiCellHeightDescripcion));
                }
            } else {
                // Mensaje si no hay libros para la editorial
                $pdf->SetFont('Arial', '', 10);
                $pdf->Cell(190, 10, 'No hay libros para esta editorial', 1, 1, 'C');
            }
        } else {
            // Mensaje si la editorial es incorrecta o inexistente
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(190, 10, 'Editorial incorrecta o inexistente', 1, 1, 'C');
        }
    }
} else {
    // Mensaje si no hay editoriales para mostrar
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(190, 10, 'No hay editoriales para mostrar', 1, 1, 'C');
}

// Se llama implícitamente al método footer() y se envía el documento al navegador web.
$pdf->output('I', 'libros_por_editorial.pdf');
