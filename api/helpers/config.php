<?php
// Encabezado para permitir solicitudes de cualquier origen.
header('Access-Control-Allow-Origin: *');
// Se establece la zona horaria local para la fecha y hora del servidor.
date_default_timezone_set('America/El_Salvador');

// Credenciales para conectar con el servidor de bases de datos.
// Se leen de variables de entorno (Docker) y, si no existen, caen al valor por defecto local (XAMPP).
define('SERVER',   getenv('DB_HOST')     ?: 'localhost');
define('DATABASE', getenv('DB_NAME')     ?: 'powerletters');
define('USERNAME', getenv('DB_USER')     ?: 'powerlettersAdmin2');
define('PASSWORD', getenv('DB_PASSWORD') ?: 'tienda123');
?>
