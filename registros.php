<?php
/**
 * ============================================================
 *  registros.php — Vista de las inscripciones recibidas
 * ------------------------------------------------------------
 *  Muestra en una tabla todas las inscripciones guardadas
 *  (lee de MySQL o de registros.json según config.php).
 *
 *  IMPORTANTE (producción): proteja esta página con un sistema
 *  de login, una carpeta protegida o reglas .htaccess, ya que
 *  contiene datos personales.
 * ============================================================
 */
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$registros  = [];
$errorCarga = '';

try {
    if (MODO_ALMACENAMIENTO === 'mysql') {
        $pdo       = obtenerConexion();
        $registros = $pdo->query(
            'SELECT cedula, nombre, celular, email, fecha_registro
             FROM inscripciones
             ORDER BY fecha_registro DESC'
        )->fetchAll();
    } else {
        /* Más recientes primero */
        $registros = array_reverse(leerRegistrosJson());
    }
} catch (Throwable $e) {
    $errorCarga = 'No se pudieron cargar los registros. Verifique la configuración '
        . '(modo de almacenamiento, base de datos o permisos del archivo JSON).';
}

$total = count($registros);

/** Escapa texto para imprimirlo de forma segura en HTML. */
function escapar(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inscripciones registradas</title>
  <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
  <main class="contenedor">
    <section class="tarjeta tarjeta--ancha">

      <header class="tarjeta__cabecera">
        <h1>Inscripciones registradas</h1>
        <p class="cabecera__evento">Travesía La Diosa del Quininí</p>
        <p><span class="badge"><?= $total ?> inscripción<?= $total === 1 ? '' : 'es' ?></span></p>
      </header>

      <div class="tarjeta__cuerpo">
        <?php if ($errorCarga !== ''): ?>
          <p class="vacio"><?= escapar($errorCarga) ?></p>

        <?php elseif ($total === 0): ?>
          <p class="vacio">Aún no hay inscripciones registradas.</p>

        <?php else: ?>
          <div class="tabla-contenedor">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>Cédula</th>
                  <th>Nombre completo</th>
                  <th>Celular</th>
                  <th>Email</th>
                  <th>Fecha</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($registros as $i => $r): ?>
                  <?php $fecha = $r['fecha_registro'] ?? $r['fecha'] ?? ''; ?>
                  <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= escapar($r['cedula']  ?? '') ?></td>
                    <td><?= escapar($r['nombre']  ?? '') ?></td>
                    <td><?= escapar($r['celular'] ?? '') ?></td>
                    <td><?= escapar($r['email']   ?? '') ?></td>
                    <td><?= escapar($fecha) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

        <a class="enlace" href="index.html">&larr; Volver al formulario</a>
      </div>
    </section>
  </main>
</body>
</html>
