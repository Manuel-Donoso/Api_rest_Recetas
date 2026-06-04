<?php
// src/index.php

// 1. Incluimos la configuración y conexión a la base de datos
require_once __DIR__ . '/config/database.php';

// 2. Intentamos hacer una consulta de prueba a tus 200 recetas
try {
    // Tomamos las 5 primeras recetas para verificar que hay datos
    // NOTA: Si mantuviste tu estructura original, cambia 'id' por 'nombre' en el SELECT
    $stmt = $pdo->query("SELECT nombre, calorias, rutaImagen FROM recetas LIMIT 5");
    $recetas = $stmt->fetchAll();

    // 3. Si todo va bien, devolvemos un JSON de éxito
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "mensaje" => "¡Conexión exitosa! Docker, PHP y MariaDB están hablando correctamente.",
        "total_muestra" => count($recetas),
        "datos_prueba" => $recetas
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (\PDOException $e) {
    // Si la base de datos falla o la tabla no existe todavía
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "mensaje" => "Hubo un problema al consultar la base de datos.",
        "error_detalle" => $e->getMessage() // Esto te dirá exactamente qué falló
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}