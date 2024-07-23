<?php

require_once('../../helpers/report.php');

require_once('../../models/data/comentarios_data.php');

require_once('../../models/data/usuario_data.php');

$pdf = new Report;

$pdf -> startReport('Comentarios por cliente');

$comentario =new ComentarioData;
if ($dataComentario = $comentario->readAll()) {
    $pdf->SetFillColor(27, 88, 169);
    $pdf->SetFont('Times', 'B', 11);
    $pdf->Cell(40, 10, 'Usuario', 1, 0, 'C', 1);
    $pdf->Cell(80, 10, $pdf->encodeString('Comentario'), 1, 0, 'C', 1);
    $pdf->Cell(30, 10, 'Calificación', 1, 0, 'C', 1);
    
    // Recorremos las editoriales obtenidas
    foreach ($dataComentario as $rowComentario) {
        $pdf->SetFont('Times', 'B', 11);
        // Creamos una instancia del modelo de Libro para procesar los datos
        $usuario = new UsuarioData;
        
        // Establecemos la editorial para obtener sus libros
        if ($comentario->setComentario($rowComentario['id_comentario'])) {
            // Verificamos si hay libros para mostrar
            if ($dataComentario = $comentario->reporteComentario()) {
                // Recorremos los libros obtenidos
                foreach ($dataComentario as $rowComentario) {
                    $pdf->SetFont('Arial', '', 10);
                    $pdf->Cell(40, 10, $pdf->encodeString($rowLibro['nombre_usuario']), 1, 0, 'L');
                    $pdf->Cell(80, 10, $pdf->encodeString($rowLibro['comentario']), 1, 0, 'L');
                    $pdf->Cell(30, 10, $rowLibro['calificacion'], 1, 0, 'C');
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
 $pdf->output('I', 'libross.pdf');