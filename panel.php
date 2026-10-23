<?php
/**
 * ============================================================
 *  panel.php — Panel de inscripciones (acceso libre)
 * ------------------------------------------------------------
 *  Muestra totales, buscador y exportación a CSV (?csv=1).
 *  Lee de MySQL o de registros.json según config.php.
 * ============================================================
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$registros  = [];
$errorCarga = '';

try {
    if (MODO_ALMACENAMIENTO === 'mysql') {
        $registros = obtenerConexion()->query(
            'SELECT cedula, nombre, celular, email, fecha_registro
             FROM inscripciones
             ORDER BY fecha_registro DESC'
        )->fetchAll();
    } else {
        $registros = array_reverse(leerRegistrosJson());
    }
} catch (Throwable $e) {
    $errorCarga = 'No se pudieron cargar los registros. Verifique la configuración.';
}

/* Exportar a CSV ----------------------------------------------------- */
if (isset($_GET['csv']) && $errorCarga === '') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inscripciones-' . date('Y-m-d') . '.csv"');
    $salida = fopen('php://output', 'w');
    fwrite($salida, "\xEF\xBB\xBF"); // BOM para que Excel lea bien los acentos
    fputcsv($salida, ['Cédula', 'Nombre', 'Celular', 'Email', 'Fecha'], ';');
    foreach ($registros as $r) {
        fputcsv($salida, [
            $r['cedula'] ?? '', $r['nombre'] ?? '', $r['celular'] ?? '',
            $r['email'] ?? '', $r['fecha_registro'] ?? $r['fecha'] ?? '',
        ], ';');
    }
    exit;
}

$total = count($registros);
$hoy   = date('Y-m-d');
$deHoy = count(array_filter(
    $registros,
    fn($r) => str_starts_with((string) ($r['fecha_registro'] ?? $r['fecha'] ?? ''), $hoy)
));
$ultima = $total > 0 ? (string) ($registros[0]['fecha_registro'] ?? $registros[0]['fecha'] ?? '—') : '—';

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
  <title>Panel de inscripciones</title>
  <link rel="stylesheet" href="css/estilos.css">
  <style>
    .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 20px; }
    .stat { background: var(--primario-suave); border-radius: 12px; padding: 14px 16px; }
    .stat__valor { font-size: 1.6rem; font-weight: 700; color: var(--primario-oscuro); }
    .stat__etiqueta { font-size: .85rem; color: var(--texto-suave); }
    .herramientas { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; }
    .herramientas input { flex: 1; min-width: 200px; padding: 10px 14px; border: 1px solid var(--borde); border-radius: 10px; font: inherit; }
    .boton-csv { display: inline-flex; align-items: center; padding: 10px 16px; border-radius: 10px; background: var(--primario); color: var(--blanco); text-decoration: none; font-weight: 600; }
    .boton-csv:hover { background: var(--primario-oscuro); }
  </style>
</head>
<body>
  <main class="contenedor">
    <section class="tarjeta tarjeta--ancha">

      <header class="tarjeta__cabecera">
        <h1>Panel de inscripciones</h1>
        <p class="cabecera__evento">Travesía La Diosa del Quininí</p>
      </header>

      <div class="tarjeta__cuerpo">
        <?php if ($errorCarga !== ''): ?>
          <p class="vacio"><?= escapar($errorCarga) ?></p>
        <?php else: ?>
          <div class="stats">
            <div class="stat"><div class="stat__valor"><?= $total ?></div><div class="stat__etiqueta">Inscripciones totales</div></div>
            <div class="stat"><div class="stat__valor"><?= $deHoy ?></div><div class="stat__etiqueta">Inscripciones hoy</div></div>
            <div class="stat"><div class="stat__valor" style="font-size:1rem"><?= escapar($ultima) ?></div><div class="stat__etiqueta">Última inscripción</div></div>
          </div>

          <?php if ($total === 0): ?>
            <p class="vacio">Aún no hay inscripciones registradas.</p>
          <?php else: ?>
            <div class="herramientas">
              <input type="search" id="buscar" placeholder="Buscar por cédula, nombre, celular o email…">
              <a class="boton-csv" href="?csv=1">Descargar CSV</a>
            </div>

            <div class="tabla-contenedor">
              <table>
                <thead>
                  <tr><th>#</th><th>Cédula</th><th>Nombre completo</th><th>Celular</th><th>Email</th><th>Fecha</th></tr>
                </thead>
                <tbody id="filas">
                  <?php foreach ($registros as $i => $r): ?>
                    <tr>
                      <td><?= $total - $i ?></td>
                      <td><?= escapar($r['cedula']  ?? '') ?></td>
                      <td><?= escapar($r['nombre']  ?? '') ?></td>
                      <td><?= escapar($r['celular'] ?? '') ?></td>
                      <td><?= escapar($r['email']   ?? '') ?></td>
                      <td><?= escapar($r['fecha_registro'] ?? $r['fecha'] ?? '') ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <p class="vacio" id="sin-resultados" hidden>No hay coincidencias.</p>
          <?php endif; ?>
        <?php endif; ?>

        <a class="enlace" href="index.html">&larr; Volver al formulario</a>
      </div>
    </section>
  </main>

  <script>
    const buscar = document.getElementById('buscar');
    if (buscar) {
      const filas = document.querySelectorAll('#filas tr');
      const aviso = document.getElementById('sin-resultados');
      buscar.addEventListener('input', () => {
        const q = buscar.value.trim().toLowerCase();
        let visibles = 0;
        filas.forEach(f => {
          const ok = f.textContent.toLowerCase().includes(q);
          f.hidden = !ok;
          if (ok) visibles++;
        });
        aviso.hidden = visibles > 0;
      });
    }
  </script>
</body>
</html>
