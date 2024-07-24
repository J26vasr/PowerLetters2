<?php

require_once('../../helpers/report.php');
require_once('../../models/data/usuario_data.php');

$pdf = new Report;

$pdf->startReport('Usuarios activos e inactivos');

$usuario = new UsuarioData;

$dataUsuario = $usuario->readAll();

if ($dataUsuario) {
    $pdf->SetFillColor(27, 88, 169);
    $pdf->SetFont('Times', 'B', 11);
    $pdf->Cell(40, 10, 'Nombre usuario', 1, 0, 'C', 1);
    $pdf->Cell(80, 10, $pdf->encodeString('Correo'), 1, 0, 'C', 1);
    $pdf->Cell(30, 10, 'Estado', 1, 1, 'C', 1);
    
    foreach ($dataUsuario as $rowUsuario) {
        $pdf->SetFont('Times', 'B', 11);
        $pdf->Cell(180, 10, 'Estado: ' . ($rowUsuario['estado_cliente'] == 1 ? 'Activos' : 'Inactivos'), 1, 1, 'C', 1);
        
        $usuario->setEstado($rowUsuario['estado_cliente']);
        $dataUsuario2 = $usuario->reporteClientesA();
        
        if ($dataUsuario2) {
            foreach ($dataUsuario2 as $rowUser) {
                $yStart = $pdf->GetY();
                $xStart = $pdf->GetX();
                
                $pdf->MultiCell(40, 10, $pdf->encodeString($rowUser['nombre_usuario']), 1, 'L');
                $multiCellHeightNombre = $pdf->GetY() - $yStart;
                
                $pdf->SetXY($xStart + 40, $yStart);
                
                $pdf->MultiCell(80, 10, $pdf->encodeString($rowUser['correo_usuario']), 1, 'L');
                $multiCellHeightCorreo = $pdf->GetY() - $yStart;
                
                $pdf->SetXY($xStart + 120, $yStart);
                
                $pdf->Cell(30, max($multiCellHeightNombre, $multiCellHeightCorreo), $rowUser['estado'], 1, 1, 'C');
                
                $pdf->SetY($yStart + max($multiCellHeightNombre, $multiCellHeightCorreo));
            }
        } else {
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(190, 10, 'No hay usuarios de este estado', 1, 1, 'C');
        }
    }
} else {
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(190, 10, 'No hay usuarios para mostrar', 1, 1, 'C');
}

$pdf->output('I', 'Usuario.pdf');