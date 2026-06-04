# 🍳 API REST de Recetas (Docker + PHP + MariaDB)

Una API REST portátil y ligera para gestionar un recetario completo, incluyendo información nutricional y desglose de ingredientes mediante una relación de muchos a muchos. El proyecto está completamente contenedorizado con **Docker**, lo que garantiza su despliegue inmediato en cualquier entorno.

---

## 🚀 Características principales

- 🐳 **Entorno Aislado:** Configuración con Docker Compose (PHP 8.2 + Apache + MariaDB).
- 📊 **Base de Datos Relacional:** Relación muchos a muchos entre recetas e ingredientes con borrado en cascada (`ON DELETE CASCADE`).
- 🧭 **Rutas Limpias:** Enrutamiento amigable mediante `.htaccess` (ej: `/recetas` en lugar de `/index.php/recetas`).
- 📄 **Paginación Eficiente:** Control de flujo de datos mediante parámetros `?pagina=X` (20 registros por tanda).
- 🔒 **Seguridad de Entorno:** Credenciales protegidas y centralizadas en un archivo `.env`.
- 🧪 **Administración Visual:** Incluye **phpMyAdmin** preconfigurado para gestionar los datos visualmente.

---

## 🛠️ Requisitos Previos

Antes de desplegar el proyecto, asegúrate de tener instalado en tu máquina:

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (con Docker Compose habilitado).

---

## 📦 Instalación y Despliegue

Sigue estos sencillos pasos para poner la API en marcha:

### 1. Clonar o descargar el proyecto

Asegúrate de tener la estructura de carpetas en tu directorio de trabajo local.

### 2. Configurar las Variables de Entorno

Crea un archivo llamado `.env` en la raíz del proyecto (junto al archivo `docker-compose.yml`) y añade tus credenciales. Ejemplo:

```env
DB_ROOT_PASSWORD=password_root
DB_NAME=api_recetario
DB_USER=usuario
DB_PASSWORD=pass_usuario
```

### 3. Levantar los Contenedores

Abre tu terminal en la raíz del proyecto y ejecuta el siguiente comando

```
docker compose up -d --build
```

💡 Nota: La primera vez que arranque, MariaDB procesará automáticamente el archivo de inicialización e importará las recetas iniciales con sus ingredientes. Esto puede tardar entre 10 y 15 segundos

## 🗺️ Mapa de Endpoints (Documentación de la API)

La API responde por defecto en el **puerto 8080**. Todas las respuestas se devuelven en formato **JSON** con codificación **UTF-8**.

| Método     | Endpoint                              | Descripción                                                                                    | Parámetros / Cuerpo (JSON)         |
| :--------- | :------------------------------------ | :--------------------------------------------------------------------------------------------- | :--------------------------------- |
| **GET**    | `/recetas`                            | Listado general paginado (20 por página) o buscador.                                           | `?pagina=1` o `?nombre=ALBÓNDIGAS` |
| **GET**    | `/receta`                             | Obtener una receta específica mediante su ID único.                                            | `?id=5`                            |
| **GET**    | `/receta/detalle`                     | Obtener una receta específica mediante su nombre exacto.                                       | `?nombre=Nombre de la receta`      |
| **POST**   | `/receta/crear`                       | Insertar una nueva receta junto con sus ingredientes.                                          | _Ver formato JSON abajo_           |
| **PUT**    | `/receta/actualizar`                  | Modificar los datos o ingredientes de una receta.                                              | _Ver formato JSON abajo_           |
| **DELETE** | `/receta/eliminar`                    | Eliminar una receta y sus relaciones en cascada.                                               | `?nombre=Nombre de la receta`      |
| **GET**    | `/recetas/por-ingrediente`            | Buscar recetas por ingrediente con paginación.                                                 | `?nombre=Huevo&pagina=2`           |
| **`GET`**  | `/recetas/por-ingrediente`            | Buscar recetas que contengan un ingrediente específico (Paginado).                             | `?nombre=Tomate&pagina=1`          |
| **`GET`**  | `/recetas/por-ingredientes-multiples` | Buscar recetas que contengan TODOS los ingredientes indicados (Paginado).                      | `?nombres=Pollo,Arroz&pagina=1`    |
| **`GET`**  | `/recetas/sin-ingredientes-multiples` | Excluir recetas que contengan AL MENOS UNO de los ingredientes (Paginado con conteo absoluto). | `?nombres=Queso,Leche&pagina=1`    |

---

## 📝 Formato JSON para peticiones de escritura (`POST` / `PUT`)

Para crear o actualizar una receta, envía una petición con la cabecera `Content-Type: application/json` y el siguiente cuerpo:

```json
{
  "nombre": "Tortilla de Patatas API",
  "calorias": 400,
  "carbohidratos": 30,
  "proteinas": 15,
  "grasas": 25,
  "fibra": 3,
  "azucares": 1,
  "pasos": "1. Freír las patatas con cebolla. 2. Batir los huevos. 3. Cuajar la tortilla en la sartén.",
  "consejos": "No la dejes demasiado hecha si te gusta jugosa.",
  "rutaImagen": "tortilla.jpg",
  "ingredientes": [
    { "nombre": "Huevo", "cantidad": 4, "unidadMedida": "unidades" },
    { "nombre": "Patata", "cantidad": 500, "unidadMedida": "gramos" },
    { "nombre": "Cebolla", "cantidad": 0.5, "unidadMedida": "unidad" }
  ]
}
```

## 🔌 Puertos y Herramientas Locales

Una vez levantado el entorno, puedes acceder a las siguientes URLs desde tu navegador o cliente REST:

    🌐 Servidor API: http://localhost:8080/recetas

    🗄️ phpMyAdmin (Gestor BD): http://localhost:8081

        Servidor (Host): db

        Usuario / Contraseña: Los configurados en tu .env (ej. user / pass_user)

## 🛑 Detener el Entorno

Para apagar los contenedores sin perder los datos guardados en la base de datos, ejecuta:

```
docker compose down
```

Si deseas resetear por completo la base de datos y borrar los datos del volumen para realizar una instalación limpia desde el script SQL original, ejecuta:
Bash

```
docker compose down -v
```
