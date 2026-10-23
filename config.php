<?php
/**
 * ============================================================
 *  config.php — Configuración del formulario de inscripción
 * ------------------------------------------------------------
 *  MODO DE ALMACENAMIENTO (edite MODO_ALMACENAMIENTO):
 *
 *  - 'json'  → Guarda cada inscripción en registros.json.
 *              Funciona de inmediato, no necesita base de datos.
 *              Ideal para pruebas o volúmenes pequeños.
 *
 *  - 'mysql' → Guarda en una base de datos MySQL/MariaDB.
 *              Recomendado para producción. Antes de usarlo:
 *              1) Importe database.sql (phpMyAdmin o consola:
 *                 mysql -u root -p < database.sql)
 *              2) Ajuste las credenciales de conexión de abajo.
 * ============================================================
 */
declare(strict_types=1);

define('MODO_ALMACENAMIENTO', getenv('MYSQLHOST') ? 'mysql' : 'json');   /* 'json' o 'mysql' */

/* ---------- Credenciales MySQL (solo si modo = 'mysql') ---------- */
/* En Railway se leen de las variables de entorno del servicio MySQL. */
define('DB_HOST', getenv('MYSQLHOST') ?: 'localhost');
define('DB_PORT', getenv('MYSQLPORT') ?: '3306');
define('DB_NAME', getenv('MYSQLDATABASE') ?: 'inscripciones');
define('DB_USER', getenv('MYSQLUSER') ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: '');

/* ---------- Archivo JSON (solo si modo = 'json') ----------------- */
const ARCHIVO_JSON = __DIR__ . '/registros.json';

/* ---------- Notificaciones por email ----------------------------- */
/* Se envían al guardar una inscripción usando la función mail()
   de PHP. Si el envío falla (ej. servidor sin correo configurado),
   la inscripción se guarda igual y el error queda en el log.
   Personalice estas direcciones antes de subir a producción.       */
const NOTIFICACION_ACTIVADA       = false;                    // notificar al administrador
const EMAIL_NOTIFICACION          = 'admin@tudominio.com';    // correo que recibe la notificación
const EMAIL_REMITENTE             = 'no-reply@tudominio.com'; // remitente de los correos
const ENVIAR_CONFIRMACION_USUARIO = false;                    // confirmación al inscrito

/* ==================================================================
 *  Funciones auxiliares
 * ================================================================== */

/** Limpia espacios al inicio y al final de un valor recibido. */
function limpiar(string $valor): string
{
    return trim($valor);
}

/** Devuelve una conexión PDO configurada (modo mysql). */
function obtenerConexion(): PDO
{
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);

    /* Crea la tabla si aún no existe (mismo esquema que database.sql). */
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS inscripciones (
           id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
           cedula         VARCHAR(20)  NOT NULL,
           nombre         VARCHAR(150) NOT NULL,
           celular        VARCHAR(20)  NOT NULL,
           email          VARCHAR(150) NOT NULL,
           fecha_registro TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
           UNIQUE KEY uq_inscripciones_cedula (cedula),
           UNIQUE KEY uq_inscripciones_email (email)
         ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci'
    );

    return $pdo;
}

/** Lee todas las inscripciones del archivo JSON (modo json). */
function leerRegistrosJson(): array
{
    if (!is_file(ARCHIVO_JSON)) {
        return [];
    }

    $contenido = (string) file_get_contents(ARCHIVO_JSON);
    $datos     = json_decode($contenido, true);

    return is_array($datos) ? $datos : [];
}

/** Excepción interna para registros duplicados; $campo indica cuál ('cedula' o 'email'). */
class DominioDuplicadoException extends Exception
{
    public function __construct(public readonly string $campo = 'cedula')
    {
        parent::__construct("Duplicado: $campo");
    }
}

/** Envía la respuesta JSON y finaliza el script. */
function responder(bool $ok, string $mensaje, array $errores = [], int $codigoHttp = 200): void
{
    http_response_code($codigoHttp);
    echo json_encode(
        [
            'ok'      => $ok,
            'mensaje' => $mensaje,
            'errores' => $errores,
        ],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}
