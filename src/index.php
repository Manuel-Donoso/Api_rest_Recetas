<?php
// src/index.php

// 1. Cargamos la base de datos (con cabeceras JSON y CORS incluidas)
require_once __DIR__ . '/config/database.php';

// 2. Obtener los detalles de la petición HTTP
$metodo = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$ruta   = rtrim($uri, '/');

// -------------------------------------------------------------------------
// ROUTER REST
// -------------------------------------------------------------------------

// ENDPOINT 1: Listar todas las recetas o buscar por nombre parcial (Buscador)
// GET /recetas o GET /recetas?nombre=ALBÓNDIGAS
if ($metodo === 'GET' && ($ruta === '/recetas' || $ruta === '' || $ruta === '/index.php')) {
    
    $buscarNombre = $_GET['nombre'] ?? null;

    try {
        if ($buscarNombre) {
            // Búsqueda parcial para un buscador en el frontend
            $stmt = $pdo->prepare("SELECT id, nombre, calorias, rutaImagen FROM recetas WHERE nombre LIKE :nombre LIMIT 20");
            $stmt->execute(['nombre' => "%$buscarNombre%"]);
        } else {
            // Listado general (Limitado a 20 por rendimiento)
            $stmt = $pdo->query("SELECT id, nombre, calorias, rutaImagen FROM recetas LIMIT 20");
        }
        
        $resultados = $stmt->fetchAll();

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "contador" => count($resultados),
            "data" => $resultados
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    } catch (\PDOException $e) {
         responderError(500, "Error en la consulta de recetas: " . $e->getMessage());
    }
}

// ENDPOINT 2: Obtener UNA receta específica con TODOS sus INGREDIENTES
// GET /receta/detalle?nombre=ALBÓNDIGAS DE PAVO EN SALSA DE TOMATE CASERA
elseif ($metodo === 'GET' && $ruta === '/receta/detalle') {
    
    $nombreReceta = $_GET['nombre'] ?? null;

    if (!$nombreReceta) {
        responderError(400, "Falta el parámetro obligatorio 'nombre' de la receta.");
    }

    try {
        // 1. Buscamos los datos generales de la receta por su nombre único
        $stmtReceta = $pdo->prepare("SELECT * FROM recetas WHERE nombre = :nombre");
        $stmtReceta->execute(['nombre' => $nombreReceta]);
        $receta = $stmtReceta->fetch();

        if (!$receta) {
            responderError(404, "La receta especificada no existe.");
        }

        // 2. Buscamos los ingredientes usando tu columna 'fk_nombre_receta'
        $sqlIngredientes = "SELECT i.nombre AS ingrediente, ri.cantidad, ri.unidadMedida 
                            FROM recetas_ingredientes ri
                            JOIN ingredientes i ON ri.fk_id_ingrediente = i.id
                            WHERE ri.fk_nombre_receta = :nombre_receta"; // 👈 Ajustado a tu estructura real
        
        $stmtIngredientes = $pdo->prepare($sqlIngredientes);
        $stmtIngredientes->execute(['nombre_receta' => $receta['nombre']]);
        $ingredientes = $stmtIngredientes->fetchAll();

        // 3. Metemos los ingredientes dentro de la respuesta de la receta
        $receta['ingredientes'] = $ingredientes;

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $receta
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    } catch (\PDOException $e) {
        responderError(500, "Error al obtener el detalle: " . $e->getMessage());
    }
}

// RUTA NO ENCONTRADA
else {
    responderError(404, "Endpoint no encontrado o método HTTP no permitido.");
}

// Función auxiliar para centralizar las respuestas de error
function responderError($codigo, $mensaje) {
    http_response_code($codigo);
    echo json_encode([
        "status" => "error",
        "message" => $mensaje
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}