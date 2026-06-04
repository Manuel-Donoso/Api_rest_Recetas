<?php
// src/index.php

require_once __DIR__ . '/config/database.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$ruta   = rtrim($uri, '/');

// -------------------------------------------------------------------------
// 1. GET /recetas - LISTAR RECETAS (AHORA CON PAGINACIÓN Y BUSCADOR)
// -------------------------------------------------------------------------
if ($metodo === 'GET' && ($ruta === '/recetas' || $ruta === '' || $ruta === '/index.php')) {
    
    $buscarNombre = $_GET['nombre'] ?? null;
    
    // Configuración de la paginación
    $limite = 20; // Cuántas recetas por página
    $pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1; // Página actual
    if ($pagina < 1) $pagina = 1; // Evitar páginas negativas o cero
    
    // Calculamos cuántos registros saltarnos
    $offset = ($pagina - 1) * $limite;

    try {
        if ($buscarNombre) {
            // Si están buscando por nombre, aplicamos LIMIT y OFFSET también a la búsqueda
            $sql = "SELECT id, nombre, calorias, rutaImagen FROM recetas 
                    WHERE nombre LIKE :nombre 
                    LIMIT :limite OFFSET :offset";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':nombre', "%$buscarNombre%", PDO::PARAM_STR);
        } else {
            // Listado general paginado
            $sql = "SELECT id, nombre, calorias, rutaImagen FROM recetas 
                    LIMIT :limite OFFSET :offset";
            
            $stmt = $pdo->prepare($sql);
        }
        
        // PDO requiere que LIMIT y OFFSET se enlacen explícitamente como ENTEROS (PARAM_INT)
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $resultados = $stmt->fetchAll();

        
        responderExito([
            "pagina_actual" => $pagina,
            "por_pagina"    => $limite,
            "contador_recibido" => count($resultados),
            "recetas"       => $resultados
        ]);

    } catch (\PDOException $e) {
         responderError(500, "Error al consultar las recetas paginadas: " . $e->getMessage());
    }
}
// -------------------------------------------------------------------------
// 2. GET /receta/detalle - DETALLE DE UNA RECETA CON INGREDIENTES
// -------------------------------------------------------------------------
elseif ($metodo === 'GET' && $ruta === '/receta/detalle') {
    $nombreReceta = $_GET['nombre'] ?? null;
    if (!$nombreReceta) responderError(400, "Falta el parámetro 'nombre'.");

    try {
        $stmtReceta = $pdo->prepare("SELECT * FROM recetas WHERE nombre = :nombre");
        $stmtReceta->execute(['nombre' => $nombreReceta]);
        $receta = $stmtReceta->fetch();

        if (!$receta) responderError(404, "La receta no existe.");

        $sqlIngredientes = "SELECT i.nombre AS ingrediente, ri.cantidad, ri.unidadMedida 
                            FROM recetas_ingredientes ri
                            JOIN ingredientes i ON ri.fk_id_ingrediente = i.id
                            WHERE ri.fk_nombre_receta = :nombre_receta";
        $stmtIngredientes = $pdo->prepare($sqlIngredientes);
        $stmtIngredientes->execute(['nombre_receta' => $receta['nombre']]);
        
        $receta['ingredientes'] = $stmtIngredientes->fetchAll();
        responderExito($receta);
    } catch (\PDOException $e) {
        responderError(500, "Error en el servidor: " . $e->getMessage());
    }
}

// -------------------------------------------------------------------------
// 3. POST /receta/crear - CREAR NUEVA RECETA CON INGREDIENTES
// -------------------------------------------------------------------------
elseif ($metodo === 'POST' && $ruta === '/receta/crear') {
    // Leer el JSON que llega en el cuerpo de la petición (body)
    $input = json_decode(file_get_contents('php://input'), true);

    // Validación básica de campos obligatorios
    if (empty($input['nombre']) || empty($input['pasos']) || !isset($input['calorias'])) {
        responderError(400, "Faltan campos obligatorios para crear la receta.");
    }

    try {
        // Iniciamos una transacción. Si algo falla dentro del bucle, nada se guarda en la BD.
        $pdo->beginTransaction();

        // 1. Insertar la receta base
        $sqlReceta = "INSERT INTO recetas (nombre, calorias, carbohidratos, proteinas, grasas, fibra, azucares, pasos, consejos, rutaImagen) 
                      VALUES (:nombre, :calorias, :carbohidratos, :proteinas, :grasas, :fibra, :azucares, :pasos, :consejos, :rutaImagen)";
        
        $stmt = $pdo->prepare($sqlReceta);
        $stmt->execute([
            'nombre'         => $input['nombre'],
            'calorias'       => $input['calorias'],
            'carbohidratos'  => $input['carbohidratos'] ?? 0,
            'proteinas'      => $input['proteinas'] ?? 0,
            'grasas'         => $input['grasas'] ?? 0,
            'fibra'          => $input['fibra'] ?? 0,
            'azucares'       => $input['azucares'] ?? 0,
            'pasos'          => $input['pasos'],
            'consejos'       => $input['consejos'] ?? null,
            'rutaImagen'     => $input['rutaImagen'] ?? null
        ]);

        // 2. Procesar los ingredientes si vienen en la petición
        if (!empty($input['ingredientes']) && is_array($input['ingredientes'])) {
            foreach ($input['ingredientes'] as $ing) {
                // Comprobar si el ingrediente ya existe por nombre para reutilizar su ID
                $stmtIng = $pdo->prepare("SELECT id FROM ingredientes WHERE nombre = :nombre");
                $stmtIng->execute(['nombre' => $ing['nombre']]);
                $idIngrediente = $stmtIng->fetchColumn();

                // Si no existe, lo creamos en caliente
                if (!$idIngrediente) {
                    $stmtNewIng = $pdo->prepare("INSERT INTO ingredientes (nombre) VALUES (:nombre)");
                    $stmtNewIng->execute(['nombre' => $ing['nombre']]);
                    $idIngrediente = $pdo->lastInsertId();
                }

                // Relacionamos la receta con el ingrediente en la tabla intermedia
                $sqlIntermedia = "INSERT INTO recetas_ingredientes (fk_nombre_receta, fk_id_ingrediente, cantidad, unidadMedida) 
                                  VALUES (:nombre_receta, :id_ingrediente, :cantidad, :unidad)";
                $stmtInt = $pdo->prepare($sqlIntermedia);
                $stmtInt->execute([
                    'nombre_receta'  => $input['nombre'],
                    'id_ingrediente' => $idIngrediente,
                    'cantidad'       => $ing['cantidad'] ?? null,
                    'unidad'         => $ing['unidadMedida'] ?? null
                ]);
            }
        }

        // Si todo ha ido bien, confirmamos los cambios en la BD
        $pdo->commit();
        http_response_code(201); // 201 Created
        echo json_encode(["status" => "success", "message" => "¡Receta e ingredientes creados con éxito!"]);

    } catch (\PDOException $e) {
        $pdo->rollBack(); // Cancelamos todo si hubo un error
        responderError(500, "No se pudo crear la receta: " . $e->getMessage());
    }
}

// -------------------------------------------------------------------------
// 4. PUT /receta/actualizar - MODIFICAR RECETA EXISTENTE
// -------------------------------------------------------------------------
elseif ($metodo === 'PUT' && $ruta === '/receta/actualizar') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['nombre'])) {
        responderError(400, "Es obligatorio indicar el 'nombre' de la receta que deseas actualizar.");
    }

    try {
        $pdo->beginTransaction();

        // 1. Actualizamos los datos de la tabla recetas
        $sqlUp = "UPDATE recetas SET 
                    calorias = :calorias, carbohidratos = :carbohidratos, proteinas = :proteinas, 
                    grasas = :grasas, fibra = :fibra, azucares = :azucares, pasos = :pasos, 
                    consejos = :consejos, rutaImagen = :rutaImagen 
                  WHERE nombre = :nombre";
        
        $stmt = $pdo->prepare($sqlUp);
        $stmt->execute([
            'nombre'         => $input['nombre'],
            'calorias'       => $input['calorias'],
            'carbohidratos'  => $input['carbohidratos'],
            'proteinas'      => $input['proteinas'],
            'grasas'         => $input['grasas'],
            'fibra'          => $input['fibra'],
            'azucares'       => $input['azucares'],
            'pasos'          => $input['pasos'],
            'consejos'       => $input['consejos'] ?? null,
            'rutaImagen'     => $input['rutaImagen'] ?? null
        ]);

        // 2. Si envían una nueva lista de ingredientes, reemplazamos los anteriores
        if (isset($input['ingredientes']) && is_array($input['ingredientes'])) {
            // Borramos las relaciones antiguas en la tabla intermedia
            $stmtDel = $pdo->prepare("DELETE FROM recetas_ingredientes WHERE fk_nombre_receta = :nombre");
            $stmtDel->execute(['nombre' => $input['nombre']]);

            // Insertamos los nuevos (mismo proceso que en el POST)
            foreach ($input['ingredientes'] as $ing) {
                $stmtIng = $pdo->prepare("SELECT id FROM ingredientes WHERE nombre = :nombre");
                $stmtIng->execute(['nombre' => $ing['nombre']]);
                $idIngrediente = $stmtIng->fetchColumn();

                if (!$idIngrediente) {
                    $stmtNewIng = $pdo->prepare("INSERT INTO ingredientes (nombre) VALUES (:nombre)");
                    $stmtNewIng->execute(['nombre' => $ing['nombre']]);
                    $idIngrediente = $pdo->lastInsertId();
                }

                $sqlInt = "INSERT INTO recetas_ingredientes (fk_nombre_receta, fk_id_ingrediente, cantidad, unidadMedida) 
                           VALUES (:nombre_receta, :id_ingrediente, :cantidad, :unidad)";
                $pdo->prepare($sqlInt)->execute([
                    'nombre_receta'  => $input['nombre'],
                    'id_ingrediente' => $idIngrediente,
                    'cantidad'       => $ing['cantidad'] ?? null,
                    'unidad'         => $ing['unidadMedida'] ?? null
                ]);
            }
        }

        $pdo->commit();
        responderExito(null, "Receta actualizada correctamente.");

    } catch (\PDOException $e) {
        $pdo->rollBack();
        responderError(500, "Error al actualizar la receta: " . $e->getMessage());
    }
}

// -------------------------------------------------------------------------
// 5. DELETE /receta/eliminar - ELIMINAR UNA RECETA
// -------------------------------------------------------------------------
elseif ($metodo === 'DELETE' && $ruta === '/receta/eliminar') {
    $nombreReceta = $_GET['nombre'] ?? null;
    if (!$nombreReceta) responderError(400, "Falta el parámetro 'nombre' de la receta a eliminar.");

    try {
        // Gracias a que configuraste 'ON DELETE CASCADE' en tu BD, 
        // al borrar la receta se eliminan automáticamente sus registros en recetas_ingredientes.
        $stmt = $pdo->prepare("DELETE FROM recetas WHERE nombre = :nombre");
        $stmt->execute(['nombre' => $nombreReceta]);

        if ($stmt->rowCount() === 0) {
            responderError(404, "No se encontró ninguna receta con ese nombre.");
        }

        responderExito(null, "Receta eliminada correctamente.");
    } catch (\PDOException $e) {
        responderError(500, "No se pudo eliminar la receta: " . $e->getMessage());
    }
}


// -------------------------------------------------------------------------
//6. GET /receta - OBTENER UNA RECETA POR SU ID (CON INGREDIENTES)
// GET /receta?id=15
// -------------------------------------------------------------------------
elseif ($metodo === 'GET' && $ruta === '/receta') {
    
    $idReceta = $_GET['id'] ?? null;

    if (!$idReceta) {
        responderError(400, "Falta el parámetro obligatorio 'id' de la receta.");
    }

    try {
        // 1. Buscamos los datos generales de la receta filtrando por su ID numérico
        $stmtReceta = $pdo->prepare("SELECT * FROM recetas WHERE id = :id");
        $stmtReceta->execute(['id' => (int)$idReceta]);
        $receta = $stmtReceta->fetch();

        // Si el ID no coincide con ninguna receta
        if (!$receta) {
            responderError(404, "La receta con el ID especificado no existe.");
        }

        // 2. Buscamos los ingredientes asociados en tu tabla intermedia cruzando con la de ingredientes
        // Usamos el nombre de la receta que acabamos de encontrar para mantener la compatibilidad con tus INSERTS
        $sqlIngredientes = "SELECT i.nombre AS ingrediente, ri.cantidad, ri.unidadMedida 
                            FROM recetas_ingredientes ri
                            JOIN ingredientes i ON ri.fk_id_ingrediente = i.id
                            WHERE ri.fk_nombre_receta = :nombre_receta";
        
        $stmtIngredientes = $pdo->prepare($sqlIngredientes);
        $stmtIngredientes->execute(['nombre_receta' => $receta['nombre']]);
        
        // 3. Inyectamos la lista de ingredientes dentro del array de la receta
        $receta['ingredientes'] = $stmtIngredientes->fetchAll();

        // Devolvemos la receta completa en formato JSON
        responderExito($receta);

    } catch (\PDOException $e) {
        responderError(500, "Error en el servidor al buscar por ID: " . $e->getMessage());
    }
}

// -------------------------------------------------------------------------
//7. GET /recetas/por-ingrediente (AHORA CON PAGINACIÓN)
// GET /recetas/por-ingrediente?nombre=Tomate&pagina=2
// -------------------------------------------------------------------------
elseif ($metodo === 'GET' && $ruta === '/recetas/por-ingrediente') {
    
    $nombreIngrediente = $_GET['nombre'] ?? null;

    if (!$nombreIngrediente) {
        responderError(400, "Falta el parámetro obligatorio 'nombre' del ingrediente a buscar.");
    }

    // Configuración de la paginación
    $limite = 20; // 20 recetas por página
    $pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    if ($pagina < 1) $pagina = 1;
    
    $offset = ($pagina - 1) * $limite;

    try {
        // Estructuramos la consulta con parámetros para LIMIT y OFFSET
        $sql = "SELECT r.id, r.nombre, r.calorias, r.rutaImagen 
                FROM recetas r
                JOIN recetas_ingredientes ri ON r.nombre = ri.fk_nombre_receta
                JOIN ingredientes i ON ri.fk_id_ingrediente = i.id
                WHERE i.nombre LIKE :ingrediente
                LIMIT :limite OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        
        // Enlazamos el texto del ingrediente
        $stmt->bindValue(':ingrediente', "%$nombreIngrediente%", PDO::PARAM_STR);
        // Obligatorio enlazar como INT para que MariaDB no falle con el LIMIT
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $resultados = $stmt->fetchAll();

        // Devolvemos los datos junto con los metadatos de la página actual
        responderExito([
            "busqueda_ingrediente" => $nombreIngrediente,
            "pagina_actual"        => $pagina,
            "por_pagina"           => $limite,
            "contador_recibido"    => count($resultados),
            "recetas"              => $resultados
        ]);

    } catch (\PDOException $e) {
        responderError(500, "Error al buscar recetas por ingrediente: " . $e->getMessage());
    }
}

// -------------------------------------------------------------------------
//8. GET /recetas/por-ingredientes-multiples (CON PAGINACIÓN)
// GET /recetas/por-ingredientes-multiples?nombres=Pollo,Arroz&pagina=1
// -------------------------------------------------------------------------
elseif ($metodo === 'GET' && $ruta === '/recetas/por-ingredientes-multiples') {
    
    $nombresInput = $_GET['nombres'] ?? null;
    if (!$nombresInput) responderError(400, "Falta el parámetro 'nombres' (separados por comas).");

    // Convertimos "Pollo,Arroz" en un array: ['Pollo', 'Arroz']
    $listaIngredientes = array_map('trim', explode(',', $nombresInput));
    $totalIngredientesBuscados = count($listaIngredientes);

    $limite = 20;
    $pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    if ($pagina < 1) $pagina = 1;
    $offset = ($pagina - 1) * $limite;

    try {
        // Creamos tantos marcadores (? o :param) como ingredientes haya en la lista
        // Ejemplo: :ing0, :ing1... para evitar inyección SQL
        $placeholders = [];
        $params = [];
        foreach ($listaIngredientes as $index => $ingrediente) {
            $key = "ing" . $index;
            $placeholders[] = ":" . $key;
            $params[$key] = $ingrediente; // Buscamos coincidencia exacta o con % si prefieres LIKE
        }
        $strPlaceholders = implode(', ', $placeholders);

        // SQL: Agrupamos por receta y filtramos con HAVING para asegurar que tiene TODOS los ingredientes
        $sql = "SELECT r.id, r.nombre, r.calorias, r.rutaImagen 
                FROM recetas r
                JOIN recetas_ingredientes ri ON r.nombre = ri.fk_nombre_receta
                JOIN ingredientes i ON ri.fk_id_ingrediente = i.id
                WHERE i.nombre IN ($strPlaceholders)
                GROUP BY r.id, r.nombre, r.calorias, r.rutaImagen
                HAVING COUNT(DISTINCT i.id) = :total_buscado
                LIMIT :limite OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        
        // Enlazamos los ingredientes dinámicos
        foreach ($params as $key => $val) {
            $stmt->bindValue(':' . $key, $val, PDO::PARAM_STR);
        }
        
        // Enlazamos paginación y el contador del HAVING
        $stmt->bindValue(':total_buscado', $totalIngredientesBuscados, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $resultados = $stmt->fetchAll();

        responderExito([
            "ingredientes_buscados" => $listaIngredientes,
            "pagina_actual"         => $pagina,
            "contador_recibido"     => count($resultados),
            "recetas"               => $resultados
        ]);

    } catch (\PDOException $e) {
        responderError(500, "Error en la búsqueda múltiple: " . $e->getMessage());
    }
}

// -------------------------------------------------------------------------
// RUTA POR DEFECTO (404)
// -------------------------------------------------------------------------
else {
    responderError(404, "Endpoint no encontrado o método HTTP no soportado.");
}




// -------------------------------------------------------------------------
// FUNCIONES AUXILIARES DE RESPUESTA
// -------------------------------------------------------------------------
function responderExito($datos = null, $mensaje = null) {
    http_response_code(200);
    $respuesta = ["status" => "success"];
    if ($mensaje) $respuesta["message"] = $mensaje;
    if ($datos !== null) $respuesta["data"] = $datos;
    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function responderError($codigo, $mensaje) {
    http_response_code($codigo);
    echo json_encode([
        "status" => "error",
        "message" => $mensaje
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
