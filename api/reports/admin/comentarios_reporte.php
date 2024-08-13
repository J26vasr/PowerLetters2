<?php

require_once('../../helpers/report.php');
require_once('../../models/data/comentarios_data.php');
require_once('../../models/data/libros_data.php');

$pdf = new Report;

$pdf->startReport('Comentarios por libros');

$libros = new LibroData;

if ($dataLibros = $libros->readAll()) {
    $pdf->SetFillColor(27, 88, 169);
    $pdf->SetFont('Times', 'B', 11);
    $pdf->Cell(60, 10, 'Titulo', 1, 0, 'C', 1);
    $pdf->Cell(30, 10, 'Usuario', 1, 0, 'C', 1);
    $pdf->Cell(70, 10, 'Comentario', 1, 0, 'C', 1);
    $pdf->Cell(30, 10, $pdf->encodeString('Calificación'), 1, 1, 'C', 1);

    // Creamos una instancia del modelo de Comentario para procesar los datos
    $comentario = new ComentarioData;

    // Obtener todos los comentarios
    $dataComentarios = $comentario->reporteComentario();

    if ($dataComentarios) {
        foreach ($dataLibros as $rowLibros) {
            $hasComments = false;
            $pdf->SetFont('Times', 'B', 11);
            $pdf->Cell(190, 10, $pdf->encodeString('Título del libro: '). $pdf->encodeString($rowLibros['nombre_producto']), 1, 1, 'C', 1);
            
            foreach ($dataComentarios as $rowComentario) {
                if ($rowComentario['id_libro'] == $rowLibros['id_libro']) {
                    $pdf->SetFont('Times', 'B', 10);
                    $pdf->Cell(60, 10, $pdf->encodeString($rowComentario['titulo']), 1, 0, 'L');
                    $pdf->Cell(30, 10, $pdf->encodeString($rowComentario['nombre_usuario']), 1, 0, 'L');
                    $pdf->Cell(70, 10, $pdf->encodeString($rowComentario['comentario']), 1, 0, 'L');
                    $pdf->Cell(30, 10, $rowComentario['calificacion'], 1, 1, 'C');
                    $hasComments = true;
                }
            }
            if (!$hasComments) {
                // Mensaje si no hay comentarios para el libro
                $pdf->SetFont('Arial', '', 10);
                $pdf->Cell(190, 10, 'No hay comentarios para este libro', 1, 1, 'C');
            }
        }
    } else {
        // Mensaje si no hay comentarios para mostrar
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(190, 10, 'No hay comentarios para mostrar', 1, 1, 'C');
    }
} else {
    // Mensaje si no hay libros para mostrar
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(190, 10, 'No hay libros para mostrar', 1, 1, 'C');
}

// Se llama implícitamente al método footer() y se envía el documento al navegador web.
$pdf->output('I', 'comentarios_por_libros.pdf');
?>