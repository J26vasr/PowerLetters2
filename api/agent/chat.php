<?php
// Proxy entre el frontend y el contenedor hermes (FastAPI). Requiere sesion activa.
header('Content-Type: application/json; charset=utf-8');

session_start();

if (isset($_SESSION['idUsuario'])) {
    $role    = 'client';
    $user_id = (int)$_SESSION['idUsuario'];
} elseif (isset($_SESSION['idAdministrador'])) {
    $role    = 'admin';
    $user_id = (int)$_SESSION['idAdministrador'];
} else {
    http_response_code(401);
    echo json_encode(['error' => 'Debes iniciar sesion para usar el asistente.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body) || empty($body['messages'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan mensajes en el body.']);
    exit;
}

$messages = $body['messages'];
if (count($messages) > 20) {
    $messages = array_slice($messages, -20);
}

$payload = [
    'messages' => $messages,
    'role'     => $role,
    'user_id'  => $user_id,
];

$hermesUrl   = rtrim(getenv('HERMES_URL') ?: 'http://hermes:8091', '/') . '/chat';
$internalTok = getenv('HERMES_INTERNAL_TOKEN') ?: '';

$ch = curl_init($hermesUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-Internal-Token: ' . $internalTok,
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 120,
    CURLOPT_CONNECTTIMEOUT => 5,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    http_response_code(502);
    echo json_encode(['error' => 'No se pudo conectar con el agente: ' . $curlErr]);
    exit;
}

http_response_code($httpCode ?: 502);
echo $response;
