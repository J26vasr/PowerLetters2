<?php
require_once('database.php');

$data = Database::getRow("SELECT 1 AS test");

if ($data) {
    echo "✅ Conexión exitosa a la base de datos POWERLETTERS";
} else {
    echo "❌ Error: " . Database::getException();
}
