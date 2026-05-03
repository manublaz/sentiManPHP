<?php
// ============================================================
//  SentiManPHP v3 — Cargador de actualizaciones SQL
//  Acepta archivos .sql con sentencias INSERT, REPLACE o UPDATE
//  exclusivamente sobre la tabla `dictionary`.
//
//  Soporta:
//    - INSERT INTO dictionary ...
//    - REPLACE INTO dictionary ...
//    - UPDATE dictionary SET col=valor WHERE id=N
//    - UPDATE dictionary SET col=valor WHERE palabra='X'
// ============================================================

require_once 'config.php';
header('Content-Type: text/html; charset=utf-8');

$mensajes = [];
$errores  = [];
$detalles = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_sql'])) {

    $f = $_FILES['archivo_sql'];

    // ---- Validaciones ----
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $errores[] = 'Error al subir el archivo (código: ' . $f['error'] . ').';
    } elseif ($f['size'] > 5 * 1024 * 1024) {
        $errores[] = 'El archivo no puede superar los 5 MB.';
    } elseif (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'sql') {
        $errores[] = 'Solo se aceptan archivos con extensión .sql';
    } else {
        $contenido = file_get_contents($f['tmp_name']);
        if ($contenido === false) {
            $errores[] = 'No se pudo leer el archivo subido.';
        } else {
            // Limpiar comentarios SQL
            $limpio = preg_replace('/--[^\n]*\n/', "\n", $contenido);
            $limpio = preg_replace('/\/\*.*?\*\//s', '', $limpio);

            // ---- Filtro de seguridad: rechazar operaciones peligrosas ----
            $peligrosas = '/\b(DROP|TRUNCATE|DELETE|ALTER|CREATE|GRANT|REVOKE|SHUTDOWN|EXEC|UNION\s+SELECT|LOAD_FILE|INTO\s+OUTFILE)\b/i';
            if (preg_match($peligrosas, $limpio, $m)) {
                $errores[] = "⚠️ El archivo contiene la operación <code>" . htmlspecialchars($m[0]) . "</code> que no está permitida. Solo se aceptan <code>INSERT</code>, <code>REPLACE</code> y <code>UPDATE</code> sobre la tabla <code>dictionary</code>.";
            } else {
                // ---- Extraer sentencias permitidas ----
                // Capturamos INSERT/REPLACE/UPDATE seguidos por la tabla dictionary
                $patron = '/(?:INSERT\s+INTO|REPLACE\s+INTO|UPDATE)\s+`?dictionary`?\b[^;]*;/is';
                preg_match_all($patron, $limpio, $matches);

                if (empty($matches[0])) {
                    $errores[] = 'El archivo no contiene sentencias <code>INSERT/REPLACE/UPDATE</code> válidas sobre la tabla <code>dictionary</code>.';
                    $errores[] = 'Recuerda: solo se procesan instrucciones que afecten a esa tabla. Si tu SQL usa otro nombre, no se ejecutará.';
                } else {
                    // ---- Ejecutar ----
                    $con = getConexion();
                    $afectadas = 0;
                    $ejecutadas = 0;
                    $errSQL    = 0;
                    $contINSERT = 0;
                    $contREPLACE = 0;
                    $contUPDATE = 0;

                    foreach ($matches[0] as $stmt) {
                        $stmtTrim = ltrim($stmt);
                        $tipo = strtoupper(substr($stmtTrim, 0, 6));

                        if ($con->query($stmt)) {
                            $afectadas += $con->affected_rows;
                            $ejecutadas++;
                            if (stripos($stmtTrim, 'INSERT') === 0)  $contINSERT++;
                            elseif (stripos($stmtTrim, 'REPLACE') === 0) $contREPLACE++;
                            elseif (stripos($stmtTrim, 'UPDATE') === 0)  $contUPDATE++;
                        } else {
                            $errSQL++;
                            $detalles[] = "❌ Error en sentencia: <code>" . htmlspecialchars(mb_substr($stmtTrim, 0, 80)) . "…</code><br>" . htmlspecialchars($con->error);
                        }
                    }
                    $con->close();

                    if ($ejecutadas > 0) {
                        $mensajes[] = "✅ Procesadas <strong>$ejecutadas</strong> sentencias correctamente.";
                        $mensajes[] = "📊 <strong>$afectadas</strong> filas insertadas/actualizadas en total.";
                        if ($contINSERT)  $mensajes[] = "➕ INSERT: $contINSERT";
                        if ($contREPLACE) $mensajes[] = "♻️ REPLACE: $contREPLACE";
                        if ($contUPDATE)  $mensajes[] = "✏️ UPDATE: $contUPDATE";
                    }
                    if ($errSQL > 0) {
                        $mensajes[] = "⚠️ $errSQL sentencias fallaron — revisa los detalles abajo.";
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Resultado de la actualización SQL — SentiManPHP</title>
  <style>
    body{font-family:'Segoe UI',sans-serif;background:#0f172a;color:#e2e8f0;
         min-height:100vh;margin:0;padding:40px 20px;}
    .box{max-width:680px;margin:0 auto;background:#1e293b;border:1px solid #334155;
         border-radius:12px;padding:28px;}
    h2{margin-bottom:16px;}
    .al{padding:11px 15px;border-radius:8px;margin-bottom:9px;font-size:.9rem;line-height:1.6;}
    .ok{background:#dcfce7;color:#15803d;}
    .er{background:#fee2e2;color:#b91c1c;}
    .det{background:#334155;padding:10px 14px;border-radius:6px;margin-top:8px;
         font-family:monospace;font-size:.82rem;}
    code{background:#334155;padding:1px 6px;border-radius:4px;font-size:.85em;}
    a.btn{display:inline-block;padding:10px 22px;background:#6366f1;color:#fff;
          border-radius:8px;text-decoration:none;font-weight:700;margin-top:14px;}
    a.btn:hover{opacity:.85;}
  </style>
</head>
<body>
<div class="box">
  <h2>📁 Resultado de la actualización del diccionario</h2>

  <?php foreach($errores  as $e): ?><div class="al er"><?= $e ?></div><?php endforeach; ?>
  <?php foreach($mensajes as $m): ?><div class="al ok"><?= $m ?></div><?php endforeach; ?>

  <?php if (!empty($detalles)): ?>
    <h3 style="margin:18px 0 10px; font-size:1rem;">Detalles:</h3>
    <?php foreach($detalles as $d): ?>
      <div class="det"><?= $d ?></div>
    <?php endforeach; ?>
  <?php endif; ?>

  <a href="index.php?tab=diccionario" class="btn">← Volver al gestor de diccionario</a>
</div>
</body>
</html>
