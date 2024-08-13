<?php

require_once('../../helpers/report.php');
require_once('../../models/data/libros_data.php');
require_once('../../models/data/clasificacion_data.php');

$pdf = new Report;

$pdf->startReport('Libros por clasificación');

$clasificacion = new ClasificacionData;
if ($dataClasificacion = $clasificacion->readAll()) {
    $pdf->SetFillColor(27, 88, 169);
    $pdf->SetFont('Times', 'B', 11);
    $pdf->Cell(70, 10, 'Titulo', 1, 0, 'C', 1);
    // Se elimina la columna de 'Descripción'
    $pdf->Cell(60, 10, 'Existencias', 1, 0, 'C', 1);
    $pdf->Cell(60, 10, 'Precio (US$)', 1, 1, 'C', 1);
    
    // Recorremos las clasificaciones obtenidas
    foreach ($dataClasificacion as $rowClasificacion) {
        $pdf->SetFont('Times', 'B', 11);
        $pdf->Cell(190, 10, $pdf->encodeString('Nombre de la clasificación: ') . $pdf->encodeString($rowClasificacion['nombre']), 1, 1, 'C', 1);
        
        // Creamos una instancia del modelo de Libro para procesar los datos
        $libros = new LibroData;
        
        // Establecemos la clasificación para obtener sus libros
        if ($libros->setClasificación($rowClasificacion['id_clasificacion'])) {
            // Verificamos si hay libros para mostrar
            if ($dataLibros = $libros->librosClasificacion()) {
                foreach ($dataLibros as $rowLibro) {
                    // Guardar posición Y actual
                    $yStart = $pdf->GetY();
                    // Guardar posición X antes de la multicelda para título
                    $xStart = $pdf->GetX();
                
                    // MultiCell para título
                    $pdf->MultiCell(70, 10, $pdf->encodeString($rowLibro['titulo']), 1, 'L');
                    // Obtener la altura de la MultiCell para título
                    $multiCellHeightTitulo = $pdf->GetY() - $yStart;
                
                    // Volver a la posición X inicial y Y inicial
                    $pdf->SetXY($xStart + 70, $yStart);
                
                    // Se elimina la MultiCell para descripción
                    // No se necesita ajustar la posición X aquí porque no hay descripción
                
                    // Cell para existencias
                    $pdf->Cell(60, $multiCellHeightTitulo, $rowLibro['existencias'], 1, 0, 'C');
                
                    // Cell para precio
                    $pdf->Cell(60, $multiCellHeightTitulo, $rowLibro['precio'], 1, 1, 'C');
                
                    // Ajustar la posición Y para la siguiente fila
                    $pdf->SetY($yStart + $multiCellHeightTitulo);
                }
            } else {
                // Mensaje si no hay libros para esta clasificación
                $pdf->SetFont('Arial', '', 10);
                $pdf->Cell(180, 10, $pdf->encodeString('No hay libros para esta clasificación'), 1, 1, 'C');
            }
        } else {
            // Mensaje si la clasificación es incorrecta o inexistente
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(190, 10, 'Clasificación incorrecta o inexistente', 1, 1, 'C');
        }
    }
} else {
    // Mensaje si no hay clasificaciones para mostrar
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(190, 10, 'No hay clasificación para mostrar', 1, 1, 'C');
}

// Se llama implícitamente al método footer() y se envía el documento al navegador web.
$pdf->output('I', 'libros.pdf');
?>