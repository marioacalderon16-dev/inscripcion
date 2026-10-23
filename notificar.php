<?php
/**
 * ============================================================
 *  notificar.php — Notificaciones por email al registrar
 * ------------------------------------------------------------
 *  Envía dos correos HTML tras cada inscripción exitosa:
 *
 *  1. Notificación al ADMINISTRADOR (EMAIL_NOTIFICACION)
 *     con los datos de la nueva inscripción.
 *  2. Confirmación a la persona inscrita (a su propio email).
 *
 *  Usa la función mail() nativa de PHP, por lo que requiere
 *  un servidor de correo configurado (en hosting normal ya
 *  viene listo; en XAMPP hay que configurar sendmail).
 *
 *  IMPORTANTE: si el envío falla, la inscripción NO se pierde
 *  (ya quedó guardada) y el error se registra en el log.
 * ============================================================
 */
declare(strict_types=1);
require_once __DIR__ . '/config.php';

/**
 * Punto de entrada: envía todas las notificaciones de una inscripción.
 * Captura cualquier error para no interrumpir el flujo del formulario.
 */
function enviarNotificacionesRegistro(array $datos): void
{
    try {
        /* 1) Notificación al administrador */
        if (NOTIFICACION_ACTIVADA && EMAIL_NOTIFICACION !== '') {
            enviarCorreo($datos, EMAIL_NOTIFICACION, true);
        }

        /* 2) Confirmación a la persona inscrita */
        if (ENVIAR_CONFIRMACION_USUARIO && filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            enviarCorreo($datos, $datos['email'], false);
        }
    } catch (Throwable $e) {
        error_log('[formulario-inscripcion][notificar] ' . $e->getMessage());
    }
}

/**
 * Envía un correo individual con el contenido según el destinatario.
 *
 * @param array  $datos        Datos de la inscripción (cedula, nombre, celular, email).
 * @param string $destinatario Dirección de correo de destino.
 * @param bool   $esAdmin      true = aviso al administrador; false = confirmación al inscrito.
 */
function enviarCorreo(array $datos, string $destinatario, bool $esAdmin): void
{
    $asunto = $esAdmin
        ? 'Nueva inscripción: ' . $datos['nombre']
        : 'Confirmación de su inscripción';

    /* Codifica el asunto en UTF-8 para respetar tildes y eñes */
    $asuntoCodificado = '=?UTF-8?B?' . base64_encode($asunto) . '?=';

    $cabeceras = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . EMAIL_REMITENTE,
        'Reply-To: ' . ($esAdmin ? $datos['email'] : EMAIL_REMITENTE),
        'X-Mailer: PHP/' . PHP_VERSION,
    ]);

    $enviado = @mail($destinatario, $asuntoCodificado, construirCuerpo($datos, $esAdmin), $cabeceras);

    if (!$enviado) {
        throw new RuntimeException('No se pudo enviar el correo a ' . $destinatario);
    }
}

/**
 * Construye el cuerpo HTML del correo (con estilos en línea,
 * compatibles con los principales clientes de correo).
 */
function construirCuerpo(array $datos, bool $esAdmin): string
{
    $fecha = date('d/m/Y H:i');

    if ($esAdmin) {
        $titulo  = 'Nueva inscripción recibida';
        $mensaje = 'Se ha registrado una nueva persona en el formulario de inscripción.';
        $pie     = 'Mensaje automático generado por el formulario de inscripción.';
    } else {
        $titulo  = 'Inscripción registrada';
        $mensaje = 'Hola <strong>' . htmlspecialchars($datos['nombre'], ENT_QUOTES, 'UTF-8') . '</strong>, '
                 . 'gracias por inscribirse. A continuación los datos registrados:';
        $pie     = 'Si usted no realizó esta inscripción, ignore este mensaje.';
    }

    /* Fila de la tabla de datos */
    $fila = function (string $campo, string $valor): string {
        return '<tr>'
            . '<td style="padding:8px 12px;border:1px solid #e5e7eb;font-weight:600;background:#f8fafc;width:40%;">'
            . htmlspecialchars($campo, ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="padding:8px 12px;border:1px solid #e5e7eb;">'
            . htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') . '</td>'
            . '</tr>';
    };

    return '<!DOCTYPE html>
<html lang="es">
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;">
  <div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
    <div style="background:#4f46e5;color:#ffffff;padding:20px 24px;text-align:center;">
      <h1 style="margin:0;font-size:20px;">' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</h1>
    </div>
    <div style="padding:24px;">
      <p style="margin:0 0 16px;color:#374151;font-size:14px;line-height:1.6;">' . $mensaje . '</p>
      <table style="border-collapse:collapse;width:100%;font-size:14px;color:#1f2937;">
        ' . $fila('Cédula', $datos['cedula']) . '
        ' . $fila('Nombre', $datos['nombre']) . '
        ' . $fila('Celular', $datos['celular']) . '
        ' . $fila('Email', $datos['email']) . '
        ' . $fila('Fecha', $fecha) . '
      </table>
      <p style="margin:16px 0 0;color:#9ca3af;font-size:12px;line-height:1.5;">' . $pie . '</p>
    </div>
  </div>
</body>
</html>';
}
