<?php
/**
 * ============================================================
 *  procesar.php — Backend del formulario de inscripción
 * ------------------------------------------------------------
 *  Recibe los datos por POST, los valida en el servidor y los
 *  guarda en MySQL o en un archivo JSON (según config.php).
 *
 *  La respuesta siempre es JSON:
 *  { "ok": true|false, "mensaje": "...", "errores": {campo: msg} }
 * ============================================================
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/notificar.php';

/* 1) Solo se acepta el método POST -------------------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, 'Método no permitido.', [], 405);
}

/* 2) Honeypot anti-spam: el campo oculto debe llegar vacío --------- */
if (!empty($_POST['sitio_web'])) {
    /* Se simula éxito para no dar pistas al bot, pero no se guarda nada */
    responder(true, 'Inscripción registrada correctamente.');
}

/* 3) Recoger y limpiar los datos ----------------------------------- */
$cedula  = str_replace([' ', '-', '.'], '', limpiar($_POST['cedula']  ?? ''));
$nombre  = limpiar($_POST['nombre']  ?? '');
$celular = str_replace([' ', '-'], '', limpiar($_POST['celular'] ?? ''));
$email   = strtolower(limpiar($_POST['email']   ?? ''));
$eps     = limpiar($_POST['eps']     ?? '');
$rh      = limpiar($_POST['rh']      ?? '');
$telefonoFamiliar = str_replace([' ', '-'], '', limpiar($_POST['telefono_familiar'] ?? ''));

/* 4) Validación en el servidor (mismas reglas que en JS) ----------- */
$errores = [];

if ($cedula === '') {
    $errores['cedula'] = 'La cédula es obligatoria.';
} elseif (!preg_match('/^\d{6,15}$/', $cedula)) {
    $errores['cedula'] = 'La cédula solo debe contener números (entre 6 y 15 dígitos).';
}

if ($nombre === '') {
    $errores['nombre'] = 'El nombre completo es obligatorio.';
} elseif (mb_strlen($nombre) < 5) {
    $errores['nombre'] = 'El nombre debe tener al menos 5 caracteres.';
} elseif (!preg_match('/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]{2,}(?:[ \'\-][A-Za-zÁÉÍÓÚÜÑáéíóúüñ]{2,})+$/u', $nombre)) {
    $errores['nombre'] = 'Ingrese nombres y apellidos (solo letras).';
}

if ($celular === '') {
    $errores['celular'] = 'El número de celular es obligatorio.';
} elseif (!preg_match('/^\d{7,15}$/', $celular)) {
    $errores['celular'] = 'El celular solo debe contener números (entre 7 y 15 dígitos).';
}

if ($email === '') {
    $errores['email'] = 'El email es obligatorio.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errores['email'] = 'Ingrese un email válido.';
}

if ($eps === '') {
    $errores['eps'] = 'La EPS es obligatoria.';
} elseif (mb_strlen($eps) < 2 || mb_strlen($eps) > 100) {
    $errores['eps'] = 'Ingrese el nombre de su EPS.';
}

if (!in_array($rh, ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'], true)) {
    $errores['rh'] = 'Seleccione su tipo de sangre.';
}

if ($telefonoFamiliar === '') {
    $errores['telefono_familiar'] = 'El teléfono de un familiar es obligatorio.';
} elseif (!preg_match('/^\d{7,15}$/', $telefonoFamiliar)) {
    $errores['telefono_familiar'] = 'El teléfono solo debe contener números (entre 7 y 15 dígitos).';
} elseif ($telefonoFamiliar === $celular) {
    $errores['telefono_familiar'] = 'Debe ser diferente a su propio celular.';
}

if (count($errores) > 0) {
    responder(false, 'Revise los campos marcados.', $errores, 422);
}

/* 5) Guardar según el modo configurado ----------------------------- */
try {
    if (MODO_ALMACENAMIENTO === 'mysql') {
        guardarEnMysql($cedula, $nombre, $celular, $email, $eps, $rh, $telefonoFamiliar);
    } else {
        guardarEnJson($cedula, $nombre, $celular, $email, $eps, $rh, $telefonoFamiliar);
    }

    /* 6) Enviar notificaciones por email.
       Nunca interrumpe el flujo: si el correo falla, la
       inscripción ya quedó guardada y el error se registra. */
    enviarNotificacionesRegistro([
        'cedula'  => $cedula,
        'nombre'  => $nombre,
        'celular' => $celular,
        'email'   => $email,
        'eps'     => $eps,
        'rh'      => $rh,
        'telefono_familiar' => $telefonoFamiliar,
    ]);

    responder(true, '¡Inscripción registrada con éxito!');
} catch (DominioDuplicadoException $e) {
    if ($e->campo === 'email') {
        responder(false, 'Este email ya se encuentra registrado.',
            ['email' => 'Email ya registrado.'], 409);
    }
    responder(false, 'Esta cédula ya se encuentra registrada.',
        ['cedula' => 'Cédula ya registrada.'], 409);
} catch (Throwable $e) {
    error_log('[formulario-inscripcion] ' . $e->getMessage());
    responder(false, 'Ocurrió un error inesperado. Intente nuevamente.', [], 500);
}

/* ==================================================================
 *  Funciones de almacenamiento
 * ================================================================== */

/**
 * Guarda la inscripción en la tabla MySQL "inscripciones".
 * Lanza DominioDuplicadoException si la cédula o el email ya existen.
 */
function guardarEnMysql(string $cedula, string $nombre, string $celular, string $email,
    string $eps, string $rh, string $telefonoFamiliar): void
{
    $sql = 'INSERT INTO inscripciones (cedula, nombre, celular, email, eps, rh, telefono_familiar)
            VALUES (:cedula, :nombre, :celular, :email, :eps, :rh, :telefono_familiar)';

    try {
        $sentencia = obtenerConexion()->prepare($sql);
        $sentencia->execute([
            ':cedula'  => $cedula,
            ':nombre'  => $nombre,
            ':celular' => $celular,
            ':email'   => $email,
            ':eps'     => $eps,
            ':rh'      => $rh,
            ':telefono_familiar' => $telefonoFamiliar,
        ]);
    } catch (PDOException $e) {
        /* 23000 = violación de integridad (cédula duplicada, clave UNIQUE) */
        if ($e->getCode() === '23000') {
            throw new DominioDuplicadoException(
                str_contains($e->getMessage(), 'uq_inscripciones_email') ? 'email' : 'cedula'
            );
        }
        throw $e;
    }
}

/**
 * Guarda la inscripción en registros.json (modo sin base de datos).
 * Lanza DominioDuplicadoException si la cédula o el email ya existen.
 */
function guardarEnJson(string $cedula, string $nombre, string $celular, string $email,
    string $eps, string $rh, string $telefonoFamiliar): void
{
    $registros = leerRegistrosJson();

    foreach ($registros as $registro) {
        if (($registro['cedula'] ?? '') === $cedula) {
            throw new DominioDuplicadoException('cedula');
        }
        if (strtolower($registro['email'] ?? '') === $email) {
            throw new DominioDuplicadoException('email');
        }
    }

    $registros[] = [
        'cedula'  => $cedula,
        'nombre'  => $nombre,
        'celular' => $celular,
        'email'   => $email,
        'eps'     => $eps,
        'rh'      => $rh,
        'telefono_familiar' => $telefonoFamiliar,
        'fecha'   => date('Y-m-d H:i:s'),
    ];

    $json = json_encode($registros, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('No se pudo codificar el registro.');
    }

    /* Escritura segura con bloqueo de archivo (evita corrupción) */
    $archivo = fopen(ARCHIVO_JSON, 'c');
    if ($archivo === false) {
        throw new RuntimeException('No se pudo abrir el archivo de registros.');
    }

    flock($archivo, LOCK_EX);
    ftruncate($archivo, 0);
    fwrite($archivo, $json);
    fflush($archivo);
    flock($archivo, LOCK_UN);
    fclose($archivo);
}
