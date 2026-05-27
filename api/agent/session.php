<?php
// Estado de sesion para el widget Hermes. Si authenticated=false, el frontend no monta el widget.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

session_start();

$out = [
    'authenticated' => false,
    'role'          => null,
    'user_id'       => null,
    'username'      => null,
];

if (isset($_SESSION['idUsuario'])) {
    $out['authenticated'] = true;
    $out['role']          = 'client';
    $out['user_id']       = (int)$_SESSION['idUsuario'];
    $out['username']      = $_SESSION['usuario'] ?? $_SESSION['correo'] ?? null;
} elseif (isset($_SESSION['idAdministrador'])) {
    $out['authenticated'] = true;
    $out['role']          = 'admin';
    $out['user_id']       = (int)$_SESSION['idAdministrador'];
    $out['username']      = $_SESSION['aliasAdministrador'] ?? null;
}

echo json_encode($out);
