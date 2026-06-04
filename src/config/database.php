<?php
// src/config/database.php

// getenv() lee las variables del entorno que le inyectamos via docker-compose
$host     = getenv('DB_HOST') ?: 'db'; 
$db       = getenv('DB_NAME');
$user     = getenv('DB_USER');
$pass     = getenv('DB_PASSWORD');
$charset  = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // En producción, asegúrate de no mostrar $e->getMessage() directamente para no exponer credenciales
    http_response_code(500);
    echo json_encode(["error" => "Error de conexión a la base de datos"]);
    exit;
}