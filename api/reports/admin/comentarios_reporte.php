<?php
 
require_once('../../helpers/report.php');
require_once('../../models/data/comentarios_data.php');
require_once('../../models/data/libros_data.php');
 
$pdf = new Report;
 
$pdf->startReport('Comentarios por cliente');
 
$libros = new LibroData;
 
if ($dataLibros = $libros->readAll()) {
    $pdf->SetFillColor(27, 88, 169);
    $pdf->SetFont('Times', 'B', 11);
    $pdf->Cell(60, 10, $pdf->encodeString('Titulo'), 1, 0, 'C', 1);
    $pdf->Cell(30, 10, $pdf->encodeString('Usuario'), 1, 0, 'C', 1);
    $pdf->Cell(70, 10, $pdf->encodeString('Comentario'), 1, 0, 'C', 1);
    $pdf->Cell(20, 10, $pdf->encodeString('Calificación'), 1, 1, 'C', 1); // Corregido para que haya salto de línea
 
    $comentario = new ComentarioData;
 
    // Obtener todos los comentarios
    $dataComentarios = $comentario->reporteComentario();
 
    if ($dataComentarios) {
        foreach ($dataLibros as $rowLibros) {
            $hasComments = false;
            foreach ($dataComentarios as $rowComentario) {
                if ($rowComentario['id_libro'] == $rowLibros['id_libro']) {
                    $pdf->SetFont('Arial', '', 10);
                    $pdf->Cell(60, 10, $pdf->encodeString($rowComentario['titulo']), 1, 0, 'L');
                    $pdf->Cell(30, 10, $pdf->encodeString($rowComentario['nombre_usuario']), 1, 0, 'L');
                    $pdf->Cell(70, 10, $pdf->encodeString($rowComentario['comentario']), 1, 0, 'L');
                    $pdf->Cell(20, 10, $rowComentario['calificacion'], 1, 1, 'C');
                    $hasComments = true;
                }
            }
            if (!$hasComments) {
                $pdf->Cell(180, 10, 'Libro sin comentarios o inexistente', 1, 1);
            }
        }
    } else {
        $pdf->Cell(190, 10, 'No hay comentarios para mostrar', 1, 1, 'C');
    }
} else {
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(190, 10, 'No hay libros para mostrar', 1, 1, 'C');
}
 
$pdf->output('I', 'comentarios.pdf'); // Salida del PDF
?>