<?php

require_once('../../helpers/report.php');
require_once('../../models/data/usuario_data.php');

$pdf = new Report;

$pdf->startReport('Usuarios activos e inactivos');

$usuario = new UsuarioData;

// Obtener todos los usuarios
$dataUsuario = $usuario->readAll();

if ($dataUsuario) {
    $pdf->SetFillColor(27, 88, 169);
    $pdf->SetFont('Times', 'B', 11);
    $pdf->Cell(40, 10, 'Nombre usuario', 1, 0, 'C', 1);
    $pdf->Cell(80, 10, $pdf->encodeString('Correo'), 1, 0, 'C', 1);
    $pdf->Cell(60, 10, 'Estado', 1, 1, 'C', 1);

    // Usar un array para agrupar usuarios por estado
    $usuariosActivos = [];
    $usuariosInactivos = [];

    // Agrupar usuarios por estado
    foreach ($dataUsuario as $rowUsuario) {
        if ($rowUsuario['estado_cliente'] == 1) {
            $usuariosActivos[] = $rowUsuario;
        } else {
            $usuariosInactivos[] = $rowUsuario;
        }
    }

    // Imprimir usuarios activos
    if (!empty($usuariosActivos)) {
        $pdf->SetFont('Times', 'B', 11);
        $pdf->Cell(180, 10, 'Estado: Activos', 1, 1, 'C', 1);
        foreach ($usuariosActivos as $rowUser) {
            $yStart = $pdf->GetY();
            $xStart = $pdf->GetX();

            // Imprimir nombre de usuario
            $pdf->MultiCell(40, 10, $pdf->encodeString($rowUser['nombre_usuario']), 1, 'L');
            $multiCellHeightNombre = $pdf->GetY() - $yStart;

            $pdf->SetXY($xStart + 40, $yStart);

            // Imprimir correo de usuario
            $pdf->MultiCell(80, 10, $pdf->encodeString($rowUser['correo_usuario']), 1, 'L');
            $multiCellHeightCorreo = $pdf->GetY() - $yStart;

            $pdf->SetXY($xStart + 120, $yStart);

            // Imprimir estado
            $pdf->Cell(60, max($multiCellHeightNombre, $multiCellHeightCorreo), 'Activos', 1, 1, 'C');

            // Ajustar la posición Y para la siguiente fila
            $pdf->SetY($yStart + max($multiCellHeightNombre, $multiCellHeightCorreo));
        }
    }

    // Imprimir usuarios inactivos
    if (!empty($usuariosInactivos)) {
        $pdf->SetFont('Times', 'B', 11);
        $pdf->Cell(180, 10, 'Estado: Inactivos', 1, 1, 'C', 1);
        foreach ($usuariosInactivos as $rowUser) {
            $yStart = $pdf->GetY();
            $xStart = $pdf->GetX();

            // Imprimir nombre de usuario
            $pdf->MultiCell(40, 10, $pdf->encodeString($rowUser['nombre_usuario']), 1, 'L');
            $multiCellHeightNombre = $pdf->GetY() - $yStart;

            $pdf->SetXY($xStart + 40, $yStart);

            // Imprimir correo de usuario
            $pdf->MultiCell(80, 10, $pdf->encodeString($rowUser['correo_usuario']), 1, 'L');
            $multiCellHeightCorreo = $pdf->GetY() - $yStart;

            $pdf->SetXY($xStart + 120, $yStart);

            // Imprimir estado
            $pdf->Cell(60, max($multiCellHeightNombre, $multiCellHeightCorreo), 'Inactivos', 1, 1, 'C');

            // Ajustar la posición Y para la siguiente fila
            $pdf->SetY($yStart + max($multiCellHeightNombre, $multiCellHeightCorreo));
        }
    }
} else {
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(190, 10, 'No hay usuarios para mostrar', 1, 1, 'C');
}

$pdf->output('I', 'Usuario.pdf');
