<?php
// ============================================================
//  SentiManPHP v2 — Interfaz principal con capas múltiples
// ============================================================
require_once 'config.php';
require_once 'sentiman.php';

$resultado    = null;
$resultadoCapas = null;
$texto        = '';
$error        = '';
$historialStats = null;
$historialReciente = [];
$ajustesMensaje = '';

$con = getConexion();

// ---- Guardar ajustes si se envían ----
if (isset($_POST['guardar_ajustes'])) {
    $nuevos = obtenerAjustes(); // cargar defaults
    $nuevos['factor_positivo']  = max(0, min(3, (float)($_POST['factor_positivo'] ?? 1.0)));
    $nuevos['factor_negativo']  = max(0, min(3, (float)($_POST['factor_negativo'] ?? 1.0)));
    $nuevos['factor_neutral']   = max(0, min(3, (float)($_POST['factor_neutral']  ?? 1.0)));
    $nuevos['umbral_peso']      = max(0, min(10,(float)($_POST['umbral_peso']     ?? 0)));
    $nuevos['factor_stemming']  = max(0, min(1, (float)($_POST['factor_stemming'] ?? 0.7)));
    $nuevos['stemming_activo']  = isset($_POST['stemming_activo']);
    $nuevos['stem_min_chars']   = max(3, min(6, (int)($_POST['stem_min_chars']    ?? 4)));
    $nuevos['suavizado']        = max(0.3, min(2, (float)($_POST['suavizado']     ?? 1.0)));
    $nuevos['correccion_sesgo'] = max(0, min(20,(float)($_POST['correccion_sesgo'] ?? 0)));
    if (guardarAjustes($nuevos)) {
        $ajustesMensaje = '✅ Ajustes guardados correctamente.';
    } else {
        $ajustesMensaje = '❌ No se pudieron guardar los ajustes. Comprueba los permisos de escritura.';
    }
} elseif (isset($_POST['reset_ajustes'])) {
    @unlink(__DIR__ . '/config_ajustes.json');
    $ajustesMensaje = '✅ Ajustes restablecidos a valores por defecto.';
}

// El sistema multicapa siempre está disponible en v3
$capasDisponibles = true;

// ---- Limpieza defensiva de texto de entrada ----
// Quita caracteres de control (excepto tab, salto de línea y retorno),
// fuerza codificación UTF-8 válida y limita la longitud.
function limpiarTextoEntrada(string $t): string {
    // Forzar UTF-8 válido (sustituye bytes inválidos por '?')
    if (!mb_check_encoding($t, 'UTF-8')) {
        $t = mb_convert_encoding($t, 'UTF-8', 'UTF-8');
    }
    // Quitar caracteres de control salvo \t \n \r
    $t = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $t);
    // Normalizar espacios (sin destruir saltos de línea)
    $t = preg_replace('/[ \t]+/', ' ', $t);
    // Limitar a 10.000 caracteres por si pegan algo enorme
    if (mb_strlen($t, 'UTF-8') > 10000) {
        $t = mb_substr($t, 0, 10000, 'UTF-8');
    }
    return trim($t);
}

// ---- Procesar formulario ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $texto = limpiarTextoEntrada($_POST['texto'] ?? '');
    if ($texto === '') {
        $error = 'Por favor, escribe o pega algún texto antes de analizar.';
    } else {
        try {
            $resultado = analizarTexto($texto, $con);
            // resultadoCapas separado para no tocar las plantillas existentes
            $resultadoCapas = $resultado['capas'];
            $resultadoCapas['_meta'] = $resultado['_meta'];
            guardarHistorico($texto, $resultado, 'web', $con);
        } catch (Throwable $e) {
            $error = 'Error procesando el texto: ' . htmlspecialchars($e->getMessage());
            $resultado = null;
            $resultadoCapas = null;
        }
    }
}

// ---- Estadísticas históricas ----
$resStats = $con->query("SELECT * FROM v_estadisticas LIMIT 1");
if ($resStats && $resStats->num_rows > 0) {
    $historialStats = $resStats->fetch_assoc();
}

// ---- Últimos 20 análisis ----
$resHist = $con->query("
    SELECT id, DATE_FORMAT(fecha,'%d/%m %H:%i') as fecha_fmt,
           porcentaje_positivo, porcentaje_negativo, porcentaje_neutral,
           polaridad, sentimiento_global, etiqueta,
           LEFT(texto_original,80) as texto_corto, fuente
    FROM analisis_historico
    ORDER BY fecha DESC LIMIT 20
");
while ($resHist && $row = $resHist->fetch_assoc()) {
    $historialReciente[] = $row;
}
$historialReciente = array_reverse($historialReciente);

// ---- Conteo de léxico por capa ----
$lexCount = contarLexiconCapas($con);

// ---- Categorías para visualizar (catálogo) ----
$catalogo = obtenerCatalogoCapas();

// ---- Listado del léxico (para gestor de diccionario) ----
$lexFiltrado = [];
$capaSel = $_GET['cap'] ?? '';
$catalogoLocal = obtenerCatalogoCapas();
if ($capaSel && isset($catalogoLocal[$capaSel]) && $capaSel !== 'general') {
    $cols = $catalogoLocal[$capaSel]['categorias'];
    // WHERE: cualquier columna de esta capa > 0
    $whereCond = implode(' OR ', array_map(fn($c) => "`$c` > 0", $cols));
    // SELECT: id, palabra y todas las columnas de la capa
    $selectCols = implode(', ', array_map(fn($c) => "`$c`", $cols));
    $sql = "SELECT id, palabra, $selectCols FROM dictionary
            WHERE $whereCond ORDER BY palabra LIMIT 500";
    $r = $con->query($sql);
    while ($r && $row = $r->fetch_assoc()) $lexFiltrado[] = $row;
}

$con->close();

// ---- JSON para JS ----
// Flags estrictas para evitar que caracteres especiales rompan el script:
// - JSON_HEX_TAG protege contra </script> en textos
// - JSON_HEX_AMP, JSON_HEX_APOS, JSON_HEX_QUOT escapan & ' " 
// - JSON_INVALID_UTF8_SUBSTITUTE reemplaza bytes no-UTF8 en lugar de fallar
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
           | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE;

$jsonResultado      = $resultado      ? json_encode($resultado,      $jsonFlags) : 'null';
$jsonResultadoCapas = $resultadoCapas ? json_encode($resultadoCapas, $jsonFlags) : 'null';
$jsonHistorial      = json_encode($historialReciente, $jsonFlags);
$jsonStats          = json_encode($historialStats,    $jsonFlags);
$jsonCatalogo       = json_encode($catalogo,          $jsonFlags);

// Salvaguarda extra: si por algo json_encode aún devuelve false → dar 'null'
foreach (['jsonResultado','jsonResultadoCapas','jsonHistorial','jsonStats','jsonCatalogo'] as $v) {
    if ($$v === false || $$v === null) $$v = 'null';
}

$tab = $_GET['tab'] ?? 'analisis';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SentiManPHP v2 — Análisis multicapa</title>
  <script src="chart.min.js"></script>
  <script src="chartjs-plugin-datalabels.min.js"></script>
  <style>
    :root {
      --pos: #22c55e; --pos-light: #dcfce7; --pos-dark: #15803d;
      --neg: #ef4444; --neg-light: #fee2e2; --neg-dark: #b91c1c;
      --neu: #f59e0b; --neu-light: #fef3c7; --neu-dark: #b45309;
      --bg: #0f172a; --surface: #1e293b; --surface2: #334155;
      --text: #e2e8f0; --text-muted: #94a3b8; --accent: #6366f1;
      --border: #334155; --radius: 12px; --shadow: 0 4px 24px rgba(0,0,0,.4);
    }
    *,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg);
           color: var(--text); min-height: 100vh; }
    a { color: var(--accent); }

    .topbar { background: var(--surface); border-bottom: 1px solid var(--border);
              padding: 12px 24px; display: flex; align-items: center; gap: 16px;
              position: sticky; top: 0; z-index: 100; }
    .topbar h1 { font-size: 1.4rem; font-weight: 700; }
    .topbar .badge { background: var(--accent); color: #fff; font-size: .7rem;
                     padding: 2px 8px; border-radius: 99px; }
    nav { display: flex; gap: 4px; margin-left: auto; flex-wrap:wrap; }
    nav a { color: var(--text-muted); text-decoration: none; padding: 6px 14px;
            border-radius: 8px; font-size: .85rem; transition: background .2s; }
    nav a:hover, nav a.active { background: var(--surface2); color: var(--text); }

    .container { max-width: 1280px; margin: 0 auto; padding: 24px; }

    .card { background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow); }
    .card-title { font-size: 1rem; font-weight: 600; margin-bottom: 16px;
                  color: var(--text-muted); text-transform: uppercase;
                  letter-spacing: .05em; display: flex; align-items: center; gap: 8px; }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
    .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
    @media(max-width: 768px) {
      .grid-2,.grid-3,.grid-4 { grid-template-columns: 1fr; }
    }

    .form-group { margin-bottom: 16px; }
    label { display: block; margin-bottom: 6px; font-size: .85rem; color: var(--text-muted); }
    textarea, input[type=text], input[type=number] {
      width: 100%; background: var(--surface2); border: 1px solid var(--border);
      border-radius: 8px; color: var(--text); padding: 10px 14px; font-size: .92rem;
      transition: border-color .2s; font-family: inherit;
    }
    textarea { min-height: 160px; resize: vertical; }
    textarea:focus, input:focus { outline: none; border-color: var(--accent); }

    .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 24px;
           border-radius: 8px; border: none; cursor: pointer; font-size: .95rem;
           font-weight: 600; transition: opacity .2s; text-decoration: none; }
    .btn-primary { background: var(--accent); color: #fff; }
    .btn-primary:hover { opacity: .85; }
    .btn-ghost { background: var(--surface2); color: var(--text); }
    .btn-sm { padding: 6px 14px; font-size: .82rem; }
    .error-msg { background: var(--neg-light); color: #7f1d1d;
                 padding: 10px 16px; border-radius: 8px; margin-bottom: 16px; }

    .metric-card { background: var(--surface); border: 1px solid var(--border);
                   border-radius: var(--radius); padding: 20px; text-align: center; }
    .metric-card .val { font-size: 2rem; font-weight: 700; }
    .metric-card .lbl { font-size: .78rem; color: var(--text-muted); margin-top: 4px; }
    .metric-card.pos { border-top: 3px solid var(--pos); }
    .metric-card.neg { border-top: 3px solid var(--neg); }
    .metric-card.neu { border-top: 3px solid var(--neu); }
    .metric-card.accent { border-top: 3px solid var(--accent); }
    .pos .val { color: var(--pos); }
    .neg .val { color: var(--neg); }
    .neu .val { color: var(--neu); }
    .accent .val { color: var(--accent); }

    .global-badge { display: inline-block; padding: 8px 24px; border-radius: 99px;
                    font-size: 1.2rem; font-weight: 700; text-transform: uppercase;
                    letter-spacing: .08em; }
    .global-badge.positivo { background: var(--pos-light); color: var(--pos-dark); }
    .global-badge.negativo { background: var(--neg-light); color: var(--neg-dark); }
    .global-badge.neutral  { background: var(--neu-light); color: var(--neu-dark); }

    .prog-bar { height: 12px; border-radius: 99px; background: var(--surface2);
                overflow: hidden; margin: 4px 0; }
    .prog-bar .fill { height: 100%; border-radius: 99px; transition: width 1s ease; }
    .fill-pos { background: linear-gradient(90deg, #16a34a, #22c55e); }
    .fill-neg { background: linear-gradient(90deg, #b91c1c, #ef4444); }
    .fill-neu { background: linear-gradient(90deg, #b45309, #f59e0b); }
    .fill-acc { background: linear-gradient(90deg, #4f46e5, #818cf8); }
    .prog-label { display: flex; justify-content: space-between; font-size: .82rem;
                  color: var(--text-muted); }

    .word-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
    .word-table th { background: var(--surface2); padding: 10px 12px;
                     text-align: left; color: var(--text-muted); }
    .word-table td { padding: 8px 12px; border-bottom: 1px solid var(--border); }
    .word-table tr:hover td { background: var(--surface2); }
    .chip { display: inline-block; padding: 2px 10px; border-radius: 99px;
            font-size: .72rem; font-weight: 600; }
    .chip.pos { background: var(--pos-light); color: var(--pos-dark); }
    .chip.neg { background: var(--neg-light); color: var(--neg-dark); }
    .chip.neu { background: var(--neu-light); color: var(--neu-dark); }
    .chip.acc { background: rgba(99,102,241,.2); color: #c7d2fe; }
    .chip.warn{ background: var(--neu-light); color: var(--neu-dark); }

    .chart-wrap { position: relative; height: 360px; width: 100%; }
    .chart-wrap canvas { max-height: 100%; }
    .chart-wrap.tall { height: 420px; }
    .chart-wrap.short { height: 280px; }

    .didact-section { line-height: 1.7; }
    .didact-section h3 { color: var(--accent); margin: 20px 0 8px; }
    .didact-section p,.didact-section ul { color: var(--text-muted); margin-bottom: 12px; }
    .didact-section ul { padding-left: 20px; }
    .didact-section li { margin-bottom: 6px; }
    .code-block { background: var(--surface2); border-radius: 8px; padding: 16px;
                  font-family: 'Courier New', monospace; font-size: .82rem;
                  color: #a5f3fc; overflow-x: auto; margin: 12px 0; white-space:pre; }
    .tip-box { background: rgba(99,102,241,.15); border-left: 4px solid var(--accent);
               padding: 12px 16px; border-radius: 0 8px 8px 0; margin: 12px 0; }
    .tip-box p { color: var(--text); margin: 0; }

    /* ---- LAYER TABS ---- */
    .layer-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 18px;
                  border-bottom: 1px solid var(--border); padding-bottom: 12px; }
    .layer-tab { background: var(--surface2); color: var(--text-muted); border: none;
                 padding: 8px 16px; border-radius: 8px; cursor: pointer;
                 font-size: .85rem; font-weight: 600; transition: all .2s; }
    .layer-tab.active { background: var(--accent); color: #fff; }
    .layer-content { display: none; }
    .layer-content.active { display: block; }

    /* ---- HISTÓRICO ---- */
    .hist-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
    .hist-table th { background: var(--surface2); padding: 8px 12px; color: var(--text-muted); text-align: left; }
    .hist-table td { padding: 7px 12px; border-bottom: 1px solid var(--border); }
    .hist-table tr:hover td { background: rgba(255,255,255,.02); }
    .source-badge { font-size: .72rem; padding: 1px 7px; border-radius: 99px; }
    .source-badge.web { background: #dbeafe; color: #1e40af; }
    .source-badge.api { background: #fce7f3; color: #9d174d; }

    .scroll-x { overflow-x: auto; }

    .meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                 gap: 12px; margin-bottom: 20px; }
    .meta-card { background: var(--surface2); padding: 14px; border-radius: 8px; text-align: center; }
    .meta-card .v { font-size: 1.1rem; font-weight: 700; color: var(--accent); }
    .meta-card .l { font-size: .72rem; color: var(--text-muted); margin-top: 3px; }

    .funnel-stages { display: flex; gap: 4px; }
    .funnel-stages .stage { flex: 1; padding: 8px; text-align: center; font-size: .78rem;
                            background: var(--surface2); color: var(--text-muted);
                            border-top: 3px solid transparent; transition: all .3s; }
    .funnel-stages .stage.active { color: var(--text); border-top-color: var(--accent);
                                    background: rgba(99,102,241,.15); font-weight: 700; }

    .upload-area { border: 2px dashed var(--border); border-radius: 8px;
                   padding: 28px; text-align: center; transition: border-color .2s; }
    .upload-area:hover { border-color: var(--accent); }
    .upload-area input[type=file] { display: block; margin: 12px auto; color: var(--text-muted); }

    .layer-disabled { opacity: 0.6; padding: 24px; text-align: center; }

    .spinner { display: none; width: 18px; height: 18px; border: 2px solid #fff6;
               border-top-color: #fff; border-radius: 50%; animation: spin .6s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body>

<!-- TOP BAR -->
<div class="topbar">
  <span style="font-size:1.4rem">🧠</span>
  <h1>SentiManPHP</h1>
  <span class="badge">v2 multicapa</span>
  <nav>
    <a href="?tab=analisis"   class="<?= $tab==='analisis'  ?'active':''?>">Análisis</a>
    <a href="?tab=historico"  class="<?= $tab==='historico' ?'active':''?>">Histórico</a>
    <a href="?tab=diccionario"class="<?= $tab==='diccionario'?'active':''?>">Diccionario</a>
    <a href="?tab=api"        class="<?= $tab==='api'       ?'active':''?>">API</a>
    <a href="?tab=aprende"    class="<?= $tab==='aprende'   ?'active':''?>">Aprende</a>
    <a href="?tab=ajustes"    class="<?= $tab==='ajustes'   ?'active':''?>">⚙️ Ajustes</a>
  </nav>
</div>

<div class="container">

<!-- ================================================================ -->
<!-- TAB: ANÁLISIS                                                     -->
<!-- ================================================================ -->
<?php if ($tab === 'analisis'): ?>

  <div class="grid-2" style="gap:24px; margin-bottom:24px;">

    <!-- Formulario -->
    <div class="card">
      <div class="card-title">📝 Introduce tu texto</div>
      <?php if ($error): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="post" id="mainForm">
        <div class="form-group">
          <label for="texto">Pega aquí el texto que quieres analizar</label>
          <textarea id="texto" name="texto" placeholder="Escribe o pega aquí tu texto…"><?= htmlspecialchars($texto) ?></textarea>
        </div>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
          <button type="submit" name="submit" class="btn btn-primary" id="submitBtn">
            <span class="spinner" id="spinner"></span>
            <span id="btnText">🔍 Analizar (5 capas)</span>
          </button>
          <button type="button" class="btn btn-ghost" onclick="document.getElementById('texto').value=''; document.getElementById('texto').focus();">
            🗑️ Limpiar
          </button>
          <span style="font-size:.8rem; color:var(--text-muted);" id="charCount">0 caracteres</span>
        </div>
      </form>
    </div>

    <!-- Explicación capas -->
    <div class="card" style="background: linear-gradient(135deg,#1e293b,#0f172a);">
      <div class="card-title">🎚️ 5 capas de análisis</div>
      <div class="didact-section">
        <p>Cada capa aporta una <strong>perspectiva distinta</strong> sobre el mismo texto:</p>
        <ul style="font-size:.88rem;">
          <li>⚖️ <strong>General</strong>: positivo/negativo/neutral clásico</li>
          <li>🎭 <strong>Emociones básicas</strong>: las 8 de Plutchik</li>
          <li>🧠 <strong>Emociones complejas</strong>: gratitud, vergüenza…</li>
          <li>🎯 <strong>Intención</strong>: queja, elogio, sarcasmo, urgencia</li>
          <li>🛍️ <strong>Comercial</strong>: compra, riesgo de abandono, objeciones</li>
        </ul>
        <div class="tip-box">
          <p>💡 Un mismo texto puede ser <em>positivo</em> en sentimiento general pero indicar <em>queja</em> en intención y <em>riesgo de abandono</em> en la capa comercial. Esa es la utilidad de las capas.</p>
        </div>
      </div>
    </div>
  </div>

  <?php if ($resultado): ?>

  <!-- TABS DE CAPAS -->
  <div class="layer-tabs">
    <button class="layer-tab active" onclick="showLayer('general', this)">⚖️ General</button>
    <?php if ($resultadoCapas): ?>
      <button class="layer-tab" onclick="showLayer('emociones_basicas', this)">🎭 Emociones básicas</button>
      <button class="layer-tab" onclick="showLayer('emociones_complejas', this)">🧠 Emociones complejas</button>
      <button class="layer-tab" onclick="showLayer('intencion', this)">🎯 Intención</button>
      <button class="layer-tab" onclick="showLayer('comercial', this)">🛍️ Comercial</button>
      <button class="layer-tab" onclick="showLayer('resumen', this)">📊 Resumen multicapa</button>
    <?php endif; ?>
  </div>

  <!-- ============ CAPA GENERAL ============ -->
  <div id="layer-general" class="layer-content active">
    <div class="card" style="margin-bottom:24px; text-align:center;">
      <div class="card-title" style="justify-content:center;">🎯 Sentimiento global</div>
      <div style="margin-bottom:12px;">
        <span class="global-badge <?= $resultado['sentimiento_global'] ?>">
          <?= strtoupper($resultado['sentimiento_global']) ?>
        </span>
      </div>
      <p style="color:var(--text-muted); font-size:.95rem;"><?= htmlspecialchars($resultado['etiqueta']) ?></p>
    </div>

    <div class="grid-4" style="margin-bottom:24px;">
      <div class="metric-card pos"><div class="val"><?= $resultado['porcentaje_positivo'] ?>%</div><div class="lbl">Positivo</div></div>
      <div class="metric-card neg"><div class="val"><?= $resultado['porcentaje_negativo'] ?>%</div><div class="lbl">Negativo</div></div>
      <div class="metric-card neu"><div class="val"><?= $resultado['porcentaje_neutral'] ?>%</div><div class="lbl">Neutral</div></div>
      <div class="metric-card accent"><div class="val"><?= $resultado['cobertura_pct'] ?>%</div><div class="lbl">Cobertura dic.</div></div>
    </div>

    <div class="card" style="margin-bottom:24px;">
      <div class="card-title">📊 Distribución</div>
      <div class="prog-label"><span>Positivo</span><span><?= $resultado['porcentaje_positivo'] ?>%</span></div>
      <div class="prog-bar"><div class="fill fill-pos" style="width:<?= $resultado['porcentaje_positivo'] ?>%"></div></div>
      <div class="prog-label" style="margin-top:10px;"><span>Negativo</span><span><?= $resultado['porcentaje_negativo'] ?>%</span></div>
      <div class="prog-bar"><div class="fill fill-neg" style="width:<?= $resultado['porcentaje_negativo'] ?>%"></div></div>
      <div class="prog-label" style="margin-top:10px;"><span>Neutral</span><span><?= $resultado['porcentaje_neutral'] ?>%</span></div>
      <div class="prog-bar"><div class="fill fill-neu" style="width:<?= $resultado['porcentaje_neutral'] ?>%"></div></div>
    </div>

    <div class="grid-2" style="margin-bottom:24px;">
      <div class="card">
        <div class="card-title">🥧 Circular</div>
        <div class="chart-wrap"><canvas id="chartPie"></canvas></div>
      </div>
      <div class="card">
        <div class="card-title">📡 Radar de métricas</div>
        <div class="chart-wrap"><canvas id="chartRadar"></canvas></div>
      </div>
    </div>
  </div>

  <?php if ($resultadoCapas): ?>

  <!-- ============ CAPA EMOCIONES BÁSICAS ============ -->
  <div id="layer-emociones_basicas" class="layer-content">
    <div class="card" style="margin-bottom:20px;">
      <div class="card-title">🎭 Emociones básicas (Plutchik)</div>
      <p style="color:var(--text-muted); font-size:.88rem; margin-bottom:18px;">
        Las 8 emociones primarias propuestas por Robert Plutchik. Toda emoción compleja resulta de combinar estas.
      </p>
      <?php
        $puntajesEB = $resultadoCapas['emociones_basicas']['puntajes'] ?? [];
        $todasEB = ['alegria','tristeza','ira','miedo','sorpresa','asco','confianza','anticipacion'];
        $detectadasEB = array_sum($puntajesEB);
      ?>
      <?php if ($detectadasEB == 0): ?>
        <div style="background:rgba(245,158,11,.1); border-left:3px solid var(--neu); padding:10px 14px; margin-bottom:14px; font-size:.85rem; color:var(--text-muted);">
          ℹ️ No se detectaron emociones básicas en este texto. Mostrando todas las categorías a 0 — prueba con un texto más expresivo (p. ej. "<em>Estoy muy feliz pero algo asustado</em>").
        </div>
      <?php endif; ?>
      <?php foreach ($todasEB as $cat):
              $val = $puntajesEB[$cat] ?? 0;
              $maxVal = !empty($puntajesEB) ? max($puntajesEB) : 1;
              $pct = ($val / $maxVal) * 100;
      ?>
        <div class="prog-label">
          <span style="text-transform:capitalize;"><?= str_replace('_',' ',$cat) ?></span>
          <span><?= $val ?></span>
        </div>
        <div class="prog-bar"><div class="fill fill-acc" style="width:<?= $pct ?>%"></div></div>
        <div style="margin-bottom:10px;"></div>
      <?php endforeach; ?>
    </div>
    <div class="grid-2">
      <div class="card">
        <div class="card-title">📡 Rueda de Plutchik</div>
        <div class="chart-wrap"><canvas id="chartPlutchik"></canvas></div>
      </div>
      <div class="card">
        <div class="card-title">📊 Barras</div>
        <div class="chart-wrap"><canvas id="chartEmocionesBarra"></canvas></div>
      </div>
    </div>
  </div>

  <!-- ============ CAPA EMOCIONES COMPLEJAS ============ -->
  <div id="layer-emociones_complejas" class="layer-content">
    <div class="card" style="margin-bottom:20px;">
      <div class="card-title">🧠 Emociones complejas y sociales</div>
      <p style="color:var(--text-muted); font-size:.88rem; margin-bottom:18px;">
        Emociones que requieren conciencia de uno mismo o relación con otros: gratitud, orgullo, vergüenza, envidia…
      </p>
      <?php
        $puntajesEC = $resultadoCapas['emociones_complejas']['puntajes'] ?? [];
        $todasEC = $catalogo['emociones_complejas']['categorias'];
        $detectadasEC = array_sum($puntajesEC);
      ?>
      <?php if ($detectadasEC == 0): ?>
        <div style="background:rgba(245,158,11,.1); border-left:3px solid var(--neu); padding:10px 14px; margin-bottom:14px; font-size:.85rem; color:var(--text-muted);">
          ℹ️ No se detectaron emociones complejas. Prueba con frases como "<em>te lo mereces</em>" (placer ajeno) o "<em>gracias, estoy muy agradecido</em>".
        </div>
      <?php endif; ?>
      <?php foreach ($todasEC as $cat):
              $val = $puntajesEC[$cat] ?? 0;
              $maxVal = !empty($puntajesEC) ? max($puntajesEC) : 1;
              $pct = ($val / $maxVal) * 100;
      ?>
        <div class="prog-label">
          <span style="text-transform:capitalize;"><?= str_replace('_',' ',$cat) ?></span>
          <span><?= $val ?></span>
        </div>
        <div class="prog-bar"><div class="fill fill-acc" style="width:<?= $pct ?>%"></div></div>
        <div style="margin-bottom:8px;"></div>
      <?php endforeach; ?>
    </div>
    <div class="card">
      <div class="card-title">📊 Polar de emociones complejas</div>
      <div class="chart-wrap"><canvas id="chartCompPolar"></canvas></div>
    </div>
  </div>

  <!-- ============ CAPA INTENCIÓN ============ -->
  <div id="layer-intencion" class="layer-content">
    <div class="card" style="margin-bottom:20px;">
      <div class="card-title">🎯 Intención del texto</div>
      <p style="color:var(--text-muted); font-size:.88rem; margin-bottom:18px;">
        ¿Para qué fue escrito este texto? Detecta queja, elogio, amenaza, petición, sarcasmo y urgencia.
      </p>

      <?php if (!empty($resultadoCapas['_meta']['sarcasmo_detectado'])): ?>
      <div style="background:rgba(245,158,11,.15); border-left:4px solid var(--neu); padding:12px 16px; margin-bottom:16px; border-radius:0 8px 8px 0;">
        ⚠️ <strong>Sarcasmo detectado.</strong> Las puntuaciones de las otras capas pueden no reflejar el sentimiento real del autor.
      </div>
      <?php endif; ?>

      <?php
        $puntajesIN = $resultadoCapas['intencion']['puntajes'] ?? [];
        $todasIN = $catalogo['intencion']['categorias'];
        $detectadasIN = array_sum($puntajesIN);
      ?>
      <?php if ($detectadasIN == 0): ?>
        <div style="background:rgba(245,158,11,.1); border-left:3px solid var(--neu); padding:10px 14px; margin-bottom:14px; font-size:.85rem; color:var(--text-muted);">
          ℹ️ No se detectó una intención clara. Prueba frases con verbos directivos como "<em>por favor</em>", "<em>quiero quejarme</em>" o "<em>recomiendo</em>".
        </div>
      <?php endif; ?>
      <?php foreach ($todasIN as $cat):
              $val = $puntajesIN[$cat] ?? 0;
              $maxVal = !empty($puntajesIN) ? max($puntajesIN) : 1;
              $pct = ($val / $maxVal) * 100;
      ?>
        <div class="prog-label">
          <span style="text-transform:capitalize;"><?= str_replace('_',' ',$cat) ?></span>
          <span><?= $val ?></span>
        </div>
        <div class="prog-bar"><div class="fill fill-acc" style="width:<?= $pct ?>%"></div></div>
        <div style="margin-bottom:8px;"></div>
      <?php endforeach; ?>
    </div>
    <div class="card">
      <div class="card-title">📊 Distribución de intenciones</div>
      <div class="chart-wrap"><canvas id="chartIntencion"></canvas></div>
    </div>
  </div>

  <!-- ============ CAPA COMERCIAL ============ -->
  <div id="layer-comercial" class="layer-content">
    <div class="card" style="margin-bottom:20px;">
      <div class="card-title">🛍️ Análisis comercial</div>
      <p style="color:var(--text-muted); font-size:.88rem; margin-bottom:18px;">
        Señales útiles para marketing y ventas: intención de compra, riesgo de fuga, objeciones, fidelización.
      </p>

      <!-- Funnel -->
      <p style="font-size:.85rem; color:var(--text-muted); margin-bottom:6px;">Etapa del funnel detectada:</p>
      <div class="funnel-stages" style="margin-bottom:18px;">
        <?php $etapa = $resultadoCapas['_meta']['etapa_funnel'] ?? 'desconocida'; ?>
        <div class="stage <?= $etapa==='atraccion'?'active':'' ?>">Atracción</div>
        <div class="stage <?= $etapa==='consideracion'?'active':'' ?>">Consideración</div>
        <div class="stage <?= $etapa==='conversion'?'active':'' ?>">Conversión</div>
        <div class="stage <?= $etapa==='retencion'?'active':'' ?>">Retención</div>
        <div class="stage <?= $etapa==='fuga'?'active':'' ?>">Fuga / Defensa</div>
      </div>

      <?php
        $puntajesCO = $resultadoCapas['comercial']['puntajes'] ?? [];
        $todasCO = $catalogo['comercial']['categorias'];
        $detectadasCO = array_sum($puntajesCO);
      ?>
      <?php if ($detectadasCO == 0): ?>
        <div style="background:rgba(245,158,11,.1); border-left:3px solid var(--neu); padding:10px 14px; margin-bottom:14px; font-size:.85rem; color:var(--text-muted);">
          ℹ️ No se detectaron señales comerciales. Prueba con frases como "<em>quiero comprar esto</em>", "<em>voy a cancelar</em>" o "<em>demasiado caro</em>".
        </div>
      <?php endif; ?>
      <?php foreach ($todasCO as $cat):
              $val = $puntajesCO[$cat] ?? 0;
              $maxVal = !empty($puntajesCO) ? max($puntajesCO) : 1;
              $pct = ($val / $maxVal) * 100;
              $esNegativa = strpos($cat,'riesgo')!==false || strpos($cat,'abandono')!==false
                            || strpos($cat,'objecion')!==false
                            || strpos($cat,'baja')!==false || strpos($cat,'malo')!==false
                            || $cat==='insatisfaccion';
      ?>
        <div class="prog-label">
          <span style="text-transform:capitalize;"><?= str_replace('_',' ',$cat) ?></span>
          <span><?= $val ?></span>
        </div>
        <div class="prog-bar">
          <div class="fill <?= $esNegativa ? 'fill-neg' : 'fill-pos' ?>" style="width:<?= $pct ?>%"></div>
        </div>
        <div style="margin-bottom:8px;"></div>
      <?php endforeach; ?>
    </div>
    <div class="card">
      <div class="card-title">📊 Mapa de señales comerciales</div>
      <div class="chart-wrap"><canvas id="chartComercial"></canvas></div>
    </div>
  </div>

  <!-- ============ RESUMEN MULTICAPA ============ -->
  <div id="layer-resumen" class="layer-content">
    <div class="card" style="margin-bottom:20px;">
      <div class="card-title">📊 Resumen multicapa</div>
      <p style="color:var(--text-muted); font-size:.88rem; margin-bottom:18px;">
        Vista combinada con las señales más importantes de todas las capas.
      </p>

      <div class="meta-grid">
        <div class="meta-card">
          <div class="v"><?= htmlspecialchars($resultado['sentimiento_global']) ?></div>
          <div class="l">Sentimiento global</div>
        </div>
        <div class="meta-card">
          <div class="v"><?= $resultadoCapas['_meta']['emocion_dominante_basica'] ?? '—' ?></div>
          <div class="l">Emoción básica top</div>
        </div>
        <div class="meta-card">
          <div class="v"><?= $resultadoCapas['_meta']['emocion_dominante_compleja'] ?? '—' ?></div>
          <div class="l">Emoción compleja top</div>
        </div>
        <div class="meta-card">
          <div class="v"><?= $resultadoCapas['_meta']['intencion_principal'] ?? '—' ?></div>
          <div class="l">Intención principal</div>
        </div>
        <div class="meta-card">
          <div class="v"><?= $resultadoCapas['_meta']['señal_comercial'] ?? '—' ?></div>
          <div class="l">Señal comercial top</div>
        </div>
        <div class="meta-card">
          <div class="v"><?= round(($resultadoCapas['_meta']['activacion'] ?? 0.5) * 100) ?>%</div>
          <div class="l">Activación (arousal)</div>
        </div>
        <div class="meta-card">
          <div class="v"><?= htmlspecialchars($resultadoCapas['_meta']['etapa_funnel'] ?? '—') ?></div>
          <div class="l">Etapa funnel</div>
        </div>
        <div class="meta-card">
          <div class="v"><?= !empty($resultadoCapas['_meta']['sarcasmo_detectado']) ? '⚠️ Sí' : 'No' ?></div>
          <div class="l">¿Sarcasmo?</div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-title">🌐 Radar comparativo entre capas</div>
      <div class="chart-wrap"><canvas id="chartMultiRadar"></canvas></div>
      <p style="font-size:.78rem; color:var(--text-muted); text-align:center; margin-top:8px;">
        Suma de puntuaciones de cada capa, normalizadas. Cuanto más extendido esté el radar en un eje, más fuerte es esa dimensión.
      </p>
    </div>
  </div>

  <?php endif; // capas disponibles ?>

  <!-- TABLA DETALLE GENERAL -->
  <?php if (count($resultado['detalle']) > 0): ?>
  <div class="card" style="margin-top:24px;">
    <div class="card-title">🔍 Palabras reconocidas — capa general (<?= count($resultado['detalle']) ?>)</div>
    <div class="scroll-x">
      <table class="word-table">
        <thead><tr>
          <th>Palabra/expresión</th><th>Tipo</th>
          <th>Pos</th><th>Neg</th><th>Neu</th>
          <th>Factor</th><th>Modificadores</th>
        </tr></thead>
        <tbody>
          <?php foreach($resultado['detalle'] as $d):
                  $pos = $d['pesos']['positiva'] ?? 0;
                  $neg = $d['pesos']['negativa'] ?? 0;
                  $neu = $d['pesos']['neutral']  ?? 0;
          ?>
          <tr>
            <td><strong><?= htmlspecialchars($d['palabra']) ?></strong></td>
            <td><?= $d['tipo']==='palabra'?'🔤 palabra':'🔗 '.htmlspecialchars($d['tipo']) ?></td>
            <td style="color:var(--pos)"><?= $pos ?></td>
            <td style="color:var(--neg)"><?= $neg ?></td>
            <td style="color:var(--neu)"><?= $neu ?></td>
            <td>×<?= number_format($d['factor'],1) ?></td>
            <td>
              <?php if($d['negado']):  ?><span class="chip acc">negada</span><?php endif; ?>
              <?php if($d['factor']>1.2): ?><span class="chip pos">intensificada</span><?php endif; ?>
              <?php if($d['factor']<0.9): ?><span class="chip warn">atenuada</span><?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <?php endif; // resultado ?>

<!-- ================================================================ -->
<!-- TAB: HISTÓRICO                                                    -->
<!-- ================================================================ -->
<?php elseif ($tab === 'historico'): ?>

  <?php if ($historialStats && (int)$historialStats['total_analisis'] > 0): ?>

  <div class="grid-4" style="margin-bottom:24px;">
    <div class="metric-card accent"><div class="val"><?= $historialStats['total_analisis'] ?></div><div class="lbl">Análisis totales</div></div>
    <div class="metric-card pos"><div class="val"><?= round($historialStats['media_positivo'],1) ?>%</div><div class="lbl">Media positivo</div></div>
    <div class="metric-card neg"><div class="val"><?= round($historialStats['media_negativo'],1) ?>%</div><div class="lbl">Media negativo</div></div>
    <div class="metric-card neu"><div class="val"><?= round($historialStats['media_neutral'],1) ?>%</div><div class="lbl">Media neutral</div></div>
  </div>

  <div class="grid-3" style="margin-bottom:24px;">
    <div class="metric-card pos"><div class="val"><?= $historialStats['total_positivos'] ?></div><div class="lbl">Análisis positivos</div></div>
    <div class="metric-card neg"><div class="val"><?= $historialStats['total_negativos'] ?></div><div class="lbl">Análisis negativos</div></div>
    <div class="metric-card neu"><div class="val"><?= $historialStats['total_neutrales'] ?></div><div class="lbl">Análisis neutrales</div></div>
  </div>

  <div class="grid-2" style="margin-bottom:24px;">
    <div class="card"><div class="card-title">📈 Evolución temporal</div><div class="chart-wrap"><canvas id="chartHistLine"></canvas></div></div>
    <div class="card"><div class="card-title">🥧 Distribución global</div><div class="chart-wrap"><canvas id="chartHistPie"></canvas></div></div>
  </div>

  <div class="grid-2" style="margin-bottom:24px;">
    <div class="card"><div class="card-title">📊 Polaridad histórica</div><div class="chart-wrap"><canvas id="chartHistPolar"></canvas></div></div>
    <div class="card"><div class="card-title">🎯 Positivo vs Negativo</div><div class="chart-wrap"><canvas id="chartHistScatter"></canvas></div></div>
  </div>

  <div class="card">
    <div class="card-title">🕐 Últimos análisis</div>
    <div class="scroll-x">
      <table class="hist-table">
        <thead><tr><th>#</th><th>Fecha</th><th>Fuente</th><th>Texto</th><th>Sent.</th><th>Pos</th><th>Neg</th><th>Neu</th><th>Polaridad</th></tr></thead>
        <tbody>
          <?php foreach(array_reverse($historialReciente) as $h): ?>
          <tr>
            <td><?= $h['id'] ?></td>
            <td><?= $h['fecha_fmt'] ?></td>
            <td><span class="source-badge <?= $h['fuente'] ?>"><?= $h['fuente'] ?></span></td>
            <td style="max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= htmlspecialchars($h['texto_corto']) ?>">
              <?= htmlspecialchars($h['texto_corto']) ?>…
            </td>
            <td><span class="chip <?= $h['sentimiento_global'] ?>"><?= $h['sentimiento_global'] ?></span></td>
            <td style="color:var(--pos)"><?= $h['porcentaje_positivo'] ?>%</td>
            <td style="color:var(--neg)"><?= $h['porcentaje_negativo'] ?>%</td>
            <td style="color:var(--neu)"><?= $h['porcentaje_neutral'] ?>%</td>
            <td><?= $h['polaridad'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php else: ?>
  <div class="card" style="text-align:center; padding:48px;">
    <div style="font-size:3rem; margin-bottom:16px;">📭</div>
    <h3 style="margin-bottom:8px;">Sin análisis aún</h3>
    <p style="color:var(--text-muted);">Analiza tu primer texto y aquí aparecerán las estadísticas históricas.</p>
  </div>
  <?php endif; ?>

<!-- ================================================================ -->
<!-- TAB: DICCIONARIO                                                  -->
<!-- ================================================================ -->
<?php elseif ($tab === 'diccionario'): ?>

  <div class="grid-4" style="margin-bottom:24px;">
    <?php foreach (['emociones_basicas','emociones_complejas','intencion','comercial'] as $c): ?>
      <div class="metric-card accent">
        <div class="val"><?= $lexCount[$c] ?? 0 ?></div>
        <div class="lbl"><?= $catalogo[$c]['icono'] ?> <?= $catalogo[$c]['nombre'] ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Subir SQL -->
  <div class="card" style="margin-bottom:24px;">
    <div class="card-title">📁 Subir actualización SQL del diccionario</div>
    <p style="color:var(--text-muted); font-size:.88rem; margin-bottom:14px;">
      Sube un archivo <code>.sql</code> con sentencias <code>INSERT</code>, <code>REPLACE</code> o <code>UPDATE</code>
      sobre la tabla <code>dictionary</code>. Por seguridad, otras operaciones
      (<code>DROP</code>, <code>DELETE</code>, <code>ALTER</code>…) son rechazadas automáticamente.
    </p>
    <form action="upload_sql.php" method="post" enctype="multipart/form-data">
      <div class="upload-area">
        <div style="font-size:2rem; margin-bottom:8px;">📤</div>
        <p style="color:var(--text-muted); font-size:.88rem;">Selecciona un archivo SQL (máx. 5 MB)</p>
        <input type="file" name="archivo_sql" accept=".sql" required>
        <button type="submit" class="btn btn-primary" style="margin-top:8px;">⬆️ Subir y aplicar</button>
      </div>
    </form>
  </div>

  <!-- Selector de capa para explorar -->
  <div class="card" style="margin-bottom:24px;">
    <div class="card-title">🔎 Explorar palabras por capa</div>
    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px;">
      <?php foreach (['emociones_basicas','emociones_complejas','intencion','comercial'] as $c): ?>
        <a href="?tab=diccionario&cap=<?= $c ?>"
           class="btn btn-sm <?= $capaSel===$c?'btn-primary':'btn-ghost' ?>">
          <?= $catalogo[$c]['icono'] ?> <?= $catalogo[$c]['nombre'] ?>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if ($capaSel && !empty($lexFiltrado)): ?>
      <?php $colsCapa = $catalogo[$capaSel]['categorias']; ?>
      <div class="scroll-x">
        <table class="word-table">
          <thead>
            <tr>
              <th>id</th>
              <th>Palabra/Expresión</th>
              <?php foreach ($colsCapa as $c): ?>
                <th style="text-align:center;"><?= str_replace('_',' ', $c) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($lexFiltrado as $r): ?>
              <tr>
                <td style="color:var(--text-muted); font-size:.78rem;"><?= $r['id'] ?></td>
                <td><strong><?= htmlspecialchars($r['palabra']) ?></strong></td>
                <?php foreach ($colsCapa as $c): ?>
                  <td style="text-align:center; <?= ($r[$c] ?? 0) > 0 ? 'color:var(--accent); font-weight:700;' : 'color:var(--text-muted);' ?>">
                    <?= $r[$c] ?? 0 ?>
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p style="color:var(--text-muted); font-size:.8rem; margin-top:10px;">
        Mostrando hasta 500 palabras de esta capa. Para edición masiva, sube un archivo SQL desde el bloque superior.
      </p>
    <?php elseif ($capaSel): ?>
      <p style="color:var(--text-muted); padding:14px;">No hay palabras con peso en esta capa todavía.</p>
    <?php else: ?>
      <p style="color:var(--text-muted); padding:14px;">Selecciona una capa para ver sus palabras y pesos.</p>
    <?php endif; ?>
  </div>

  <!-- Plantilla SQL -->
  <div class="card">
    <div class="card-title">📝 Plantillas SQL para añadir o modificar palabras</div>

    <h4 style="margin:14px 0 8px; color:var(--text);">Añadir palabras nuevas (REPLACE)</h4>
    <p style="color:var(--text-muted); font-size:.85rem;">
      Si la palabra existe se actualizan sus pesos; si no existe, se añade.
    </p>
    <div class="code-block">USE `sentiman`;

REPLACE INTO `dictionary` (palabra, positiva, negativa, neutral, alegria, ira) VALUES
('palabra_ejemplo', 8, 0, 2, 7, 0),
('frase de ejemplo', 0, 8, 2, 0, 8);

-- Solo necesitas listar las columnas que quieres llenar.
-- Las demás columnas se quedarán a 0 por defecto.</div>

    <h4 style="margin:18px 0 8px; color:var(--text);">Modificar palabras existentes (UPDATE)</h4>
    <p style="color:var(--text-muted); font-size:.85rem;">
      Puedes filtrar por <code>id</code> o por <code>palabra</code>:
    </p>
    <div class="code-block">USE `sentiman`;

-- Por id (más rápido, exige saber el id)
UPDATE `dictionary` SET alegria = 9, positiva = 8 WHERE id = 1234;

-- Por palabra exacta
UPDATE `dictionary` SET ira = 9, negativa = 9 WHERE palabra = 'odio';

-- Modificar varias columnas a la vez
UPDATE `dictionary`
SET intencion_compra = 8, satisfaccion_alta = 7
WHERE palabra = 'me lo llevo';</div>

    <p style="color:var(--text-muted); font-size:.83rem; margin-top:10px;">
      💡 <strong>Columnas válidas</strong>: <code>positiva, negativa, neutral, alegria, tristeza, ira, miedo, sorpresa, asco, confianza, anticipacion,
      gratitud, orgullo, admiracion, compasion, esperanza, aceptacion, verguenza, culpa, envidia, placer_ajeno, apatia, ambivalencia, soledad, humildad,
      queja, elogio, amenaza, peticion, sarcasmo, urgencia, intensidad_alta, intensidad_baja,
      intencion_compra, riesgo_abandono, fidelizacion, satisfaccion_alta, insatisfaccion,
      objecion_precio, objecion_valor, objecion_tiempo, objecion_necesidad, objecion_confianza,
      comparacion, escasez, calidad_alta, calidad_baja, servicio_bueno, servicio_malo</code>.
      Los pesos van de 0 a 10.
    </p>
  </div>

<!-- ================================================================ -->
<!-- TAB: API                                                          -->
<!-- ================================================================ -->
<?php elseif ($tab === 'api'): ?>

  <div class="card" style="margin-bottom:24px;">
    <div class="card-title">🔌 API de Análisis de Sentimiento</div>
    <div class="didact-section">
      <h3>📍 URL</h3>
      <div class="code-block">http://localhost/sentiman/api.php</div>

      <h3>📤 Petición simple (capa general)</h3>
      <div class="code-block">http://localhost/sentiman/api.php?texto=Esta+película+es+maravillosa</div>

      <h3>📥 Respuesta (texto plano)</h3>
      <div class="code-block">sentimiento:positivo
positivo:78.50
negativo:12.30
neutral:9.20
polaridad:0.73
etiqueta:Positivo
palabras:8
encontradas:4
cobertura:50.00</div>

      <h3>🎚️ Petición con capas</h3>
      <p>Añade <code>&capa=NOMBRE</code> para obtener una capa concreta:</p>
      <div class="code-block">api.php?texto=Quiero+cancelar+mi+suscripción&capa=comercial
api.php?texto=Estoy+furioso+con+esto&capa=emociones_basicas
api.php?texto=Sí+claro+qué+sorpresa&capa=intencion</div>

      <p>Usa <code>&capa=todas</code> para recibir todas las capas a la vez:</p>
      <div class="code-block">capa:general
sentimiento:positivo
positivo:75.00

capa:emociones_basicas
alegria:8.0
confianza:5.0

capa:intencion
elogio:7.0
...</div>

      <h3>💻 PHP — uso desde un scraper</h3>
      <div class="code-block">&lt;?php
$texto = "Estoy muy decepcionado, voy a cancelar el servicio";
$url = "http://localhost/sentiman/api.php?capa=comercial&texto="
       . urlencode($texto);
$respuesta = file_get_contents($url);
echo $respuesta;
?&gt;</div>

      <div class="tip-box">
        <p>🎯 <strong>Capas válidas:</strong> <code>general</code> (por defecto), <code>emociones_basicas</code>, <code>emociones_complejas</code>, <code>intencion</code>, <code>comercial</code>, <code>todas</code>.</p>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-title">🧪 Probar la API</div>
    <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:14px;">
      <input type="text" id="apiTestInput" placeholder="Escribe un texto…" style="flex:1; min-width:200px;">
      <select id="apiTestCapa" style="background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); padding:10px 14px; min-width:160px;">
        <option value="">general</option>
        <option value="emociones_basicas">emociones_basicas</option>
        <option value="emociones_complejas">emociones_complejas</option>
        <option value="intencion">intencion</option>
        <option value="comercial">comercial</option>
        <option value="todas">todas</option>
      </select>
      <button class="btn btn-primary" onclick="probarAPI()">▶ Probar</button>
    </div>
    <pre id="apiTestOutput" style="background:var(--surface2); border-radius:8px; padding:16px;
      font-size:.85rem; color:#a5f3fc; min-height:80px; white-space:pre-wrap;">
Aquí aparecerá la respuesta…</pre>
  </div>

<!-- ================================================================ -->
<!-- TAB: APRENDE                                                      -->
<!-- ================================================================ -->
<?php elseif ($tab === 'aprende'): ?>

  <div class="card didact-section" style="margin-bottom:24px;">
    <div class="card-title">🎚️ Por qué 5 capas</div>
    <p>Un mismo texto puede contener <strong>varias señales simultáneas</strong> que se contradicen entre sí. Ejemplo:</p>
    <div class="code-block">"Sí, claro, qué buena idea cancelar mi cuenta sin avisar."</div>
    <p>Si solo miras la <strong>capa general</strong>, las palabras "buena idea" sugieren positivo. Pero las otras capas revelan la verdad:</p>
    <ul>
      <li>🎯 <strong>Intención</strong> detecta sarcasmo en "sí, claro"</li>
      <li>🛍️ <strong>Comercial</strong> detecta "cancelar" como riesgo de abandono</li>
      <li>🎭 <strong>Emociones básicas</strong> detecta ira</li>
    </ul>
    <p>El análisis multicapa permite descubrir estas <strong>capas ocultas</strong> que un análisis simple pasaría por alto.</p>
  </div>

  <div class="grid-2" style="margin-bottom:24px;">
    <div class="card didact-section">
      <div class="card-title">🎭 Capa 2 — Emociones básicas</div>
      <p>Robert Plutchik propuso en 1980 que existen <strong>8 emociones primarias</strong> universales en todas las culturas, dispuestas en una rueda con sus opuestas:</p>
      <ul>
        <li><strong>Alegría</strong> ↔ Tristeza</li>
        <li><strong>Confianza</strong> ↔ Asco</li>
        <li><strong>Miedo</strong> ↔ Ira</li>
        <li><strong>Sorpresa</strong> ↔ Anticipación</li>
      </ul>
      <p>Las emociones complejas se forman combinando dos primarias adyacentes. Por ejemplo: <em>alegría + confianza = amor</em>.</p>
    </div>

    <div class="card didact-section">
      <div class="card-title">🧠 Capa 3 — Emociones complejas</div>
      <p>Son emociones <strong>sociales</strong> y <strong>autoconscientes</strong> que requieren un yo y un otro:</p>
      <ul>
        <li><strong>Gratitud</strong>: reconocimiento del beneficio recibido</li>
        <li><strong>Vergüenza</strong>: percepción de fallo ante los otros</li>
        <li><strong>Envidia</strong>: deseo del bien ajeno</li>
        <li><strong>Placer ajeno</strong>: alegría por el mal ajeno (lo que en alemán llaman <em>schadenfreude</em>)</li>
        <li><strong>Compasión</strong>: dolor por el dolor del otro</li>
      </ul>
      <p>Aparecen ~18 meses después que las básicas en el desarrollo infantil.</p>
    </div>

    <div class="card didact-section">
      <div class="card-title">🎯 Capa 4 — Intención y sarcasmo</div>
      <p>El análisis de intención (<em>speech act analysis</em>) clasifica el texto según su <strong>propósito comunicativo</strong>:</p>
      <ul>
        <li><strong>Asertivos</strong>: afirmar, elogiar</li>
        <li><strong>Directivos</strong>: pedir, ordenar, amenazar</li>
        <li><strong>Compromisivos</strong>: prometer</li>
        <li><strong>Expresivos</strong>: quejarse, agradecer</li>
        <li><strong>Declarativos</strong>: cancelar, despedir</li>
      </ul>
      <p>El <strong>sarcasmo</strong> es especialmente difícil porque la polaridad léxica miente: hay que detectar marcadores como <em>"sí claro"</em>, <em>"qué sorpresa"</em>, <em>"para variar"</em>.</p>
    </div>

    <div class="card didact-section">
      <div class="card-title">🛍️ Capa 5 — Comercial</div>
      <p>Aplica el análisis al <strong>customer journey</strong> y a las 5 etapas del funnel:</p>
      <ul>
        <li><strong>Atracción</strong>: interés, curiosidad</li>
        <li><strong>Consideración</strong>: comparación, dudas</li>
        <li><strong>Conversión</strong>: intención de compra, objeciones</li>
        <li><strong>Retención</strong>: satisfacción, fidelización</li>
        <li><strong>Fuga / Defensa</strong>: riesgo de abandono, queja, mala recomendación</li>
      </ul>
      <p>El <strong>NPS</strong> (Net Promoter Score) y el <strong>riesgo de abandono</strong> de clientes se pueden estimar con esta capa.</p>
    </div>
  </div>

  <div class="card didact-section">
    <div class="card-title">📐 Métricas adicionales que calcula el sistema</div>
    <ul>
      <li><strong>Polaridad</strong> (−1 a +1): dirección del sentimiento</li>
      <li><strong>Subjetividad</strong> (0 a 1): cuánto sentimiento hay vs objetividad</li>
      <li><strong>Activación / arousal</strong> (0 a 1): pasivo vs activado emocionalmente</li>
      <li><strong>Cobertura del léxico</strong>: % de palabras del texto reconocidas</li>
      <li><strong>Etapa funnel</strong>: clasificación automática del momento comercial</li>
      <li><strong>Sarcasmo</strong>: bandera booleana cuando se detecta</li>
    </ul>
    <p>Combinadas, estas métricas dan un <strong>perfil rico</strong> del texto, mucho más útil que un simple "positivo/negativo".</p>
  </div>

<?php elseif ($tab === 'ajustes'): ?>
  <?php $aj = obtenerAjustes(); ?>

  <?php if ($ajustesMensaje): ?>
    <div style="padding:12px 18px; border-radius:8px; margin-bottom:20px; background:<?= strpos($ajustesMensaje,'✅')!==false?'var(--pos-light)':'var(--neg-light)' ?>; color:<?= strpos($ajustesMensaje,'✅')!==false?'var(--pos-dark)':'var(--neg-dark)' ?>;">
      <?= $ajustesMensaje ?>
    </div>
  <?php endif; ?>

  <div class="card" style="margin-bottom:24px;">
    <div class="card-title">⚙️ Panel de ajustes del motor de análisis</div>
    <p style="color:var(--text-muted); font-size:.88rem; margin-bottom:20px;">
      Estos factores modifican en tiempo real el comportamiento del motor.
      Los cambios se aplican inmediatamente al siguiente análisis.
    </p>

    <form method="post" action="?tab=ajustes">

      <h3 style="color:var(--accent); margin:0 0 14px;">⚖️ Factores de peso por componente</h3>
      <p style="color:var(--text-muted); font-size:.85rem; margin-bottom:14px;">
        Multiplicadores para el puntaje general. Si el diccionario tiene sesgo positivo,
        sube el factor negativo o baja el positivo.
      </p>
      <div class="grid-3" style="margin-bottom:24px;">
        <div>
          <label>Factor positivo: <strong id="lbl_fp"><?= number_format($aj['factor_positivo'], 2) ?></strong></label>
          <input type="range" name="factor_positivo" min="0" max="3" step="0.05"
                 value="<?= $aj['factor_positivo'] ?>"
                 oninput="document.getElementById('lbl_fp').textContent=parseFloat(this.value).toFixed(2)"
                 style="width:100%">
          <div class="prog-label"><span>0</span><span>3.0</span></div>
        </div>
        <div>
          <label>Factor negativo: <strong id="lbl_fn"><?= number_format($aj['factor_negativo'], 2) ?></strong></label>
          <input type="range" name="factor_negativo" min="0" max="3" step="0.05"
                 value="<?= $aj['factor_negativo'] ?>"
                 oninput="document.getElementById('lbl_fn').textContent=parseFloat(this.value).toFixed(2)"
                 style="width:100%">
          <div class="prog-label"><span>0</span><span>3.0</span></div>
        </div>
        <div>
          <label>Factor neutral: <strong id="lbl_fne"><?= number_format($aj['factor_neutral'], 2) ?></strong></label>
          <input type="range" name="factor_neutral" min="0" max="3" step="0.05"
                 value="<?= $aj['factor_neutral'] ?>"
                 oninput="document.getElementById('lbl_fne').textContent=parseFloat(this.value).toFixed(2)"
                 style="width:100%">
          <div class="prog-label"><span>0</span><span>3.0</span></div>
        </div>
      </div>

      <h3 style="color:var(--accent); margin:0 0 14px;">🎛️ Corrección de sesgo</h3>
      <div class="grid-2" style="margin-bottom:24px;">
        <div>
          <label>Corrección de sesgo positivo: <strong id="lbl_cs"><?= number_format($aj['correccion_sesgo'], 1) ?></strong></label>
          <input type="range" name="correccion_sesgo" min="0" max="20" step="0.5"
                 value="<?= $aj['correccion_sesgo'] ?>"
                 oninput="document.getElementById('lbl_cs').textContent=parseFloat(this.value).toFixed(1)"
                 style="width:100%">
          <div class="prog-label"><span>0 (desactivado)</span><span>20 (muy fuerte)</span></div>
          <p style="color:var(--text-muted); font-size:.8rem; margin-top:6px;">
            Suma puntos al puntaje negativo para contrarrestar el sesgo positivo del diccionario.
          </p>
        </div>
        <div>
          <label>Suavizado de pesos: <strong id="lbl_su"><?= number_format($aj['suavizado'], 2) ?></strong></label>
          <input type="range" name="suavizado" min="0.3" max="2" step="0.05"
                 value="<?= $aj['suavizado'] ?>"
                 oninput="document.getElementById('lbl_su').textContent=parseFloat(this.value).toFixed(2)"
                 style="width:100%">
          <div class="prog-label"><span>0.3 (aplana)</span><span>2.0 (amplifica)</span></div>
          <p style="color:var(--text-muted); font-size:.8rem; margin-top:6px;">
            Valor &lt; 1 suaviza diferencias. Valor &gt; 1 las amplifica.
          </p>
        </div>
      </div>

      <h3 style="color:var(--accent); margin:0 0 14px;">🌿 Stemming (raíces de palabras)</h3>
      <p style="color:var(--text-muted); font-size:.85rem; margin-bottom:14px;">
        El stemming permite que "abandonar", "abandonado" o "abandonándose"
        hereden los pesos de "abandono" si no están en el diccionario.
      </p>
      <div class="grid-3" style="margin-bottom:24px;">
        <div>
          <label>
            <input type="checkbox" name="stemming_activo" value="1" <?= $aj['stemming_activo'] ? 'checked' : '' ?>>
            Activar stemming
          </label>
        </div>
        <div>
          <label>Factor de stemming: <strong id="lbl_fs"><?= number_format($aj['factor_stemming'], 2) ?></strong></label>
          <input type="range" name="factor_stemming" min="0" max="1" step="0.05"
                 value="<?= $aj['factor_stemming'] ?>"
                 oninput="document.getElementById('lbl_fs').textContent=parseFloat(this.value).toFixed(2)"
                 style="width:100%">
          <div class="prog-label"><span>0 (ignora)</span><span>1.0 (peso completo)</span></div>
        </div>
        <div>
          <label>Longitud mínima de raíz: <strong id="lbl_mc"><?= $aj['stem_min_chars'] ?></strong></label>
          <input type="range" name="stem_min_chars" min="3" max="6" step="1"
                 value="<?= $aj['stem_min_chars'] ?>"
                 oninput="document.getElementById('lbl_mc').textContent=this.value"
                 style="width:100%">
          <div class="prog-label"><span>3 (más matches)</span><span>6 (más preciso)</span></div>
        </div>
      </div>

      <div style="display:flex; gap:12px;">
        <button type="submit" name="guardar_ajustes" value="1" class="btn btn-primary">💾 Guardar ajustes</button>
        <button type="submit" name="reset_ajustes" value="1" class="btn btn-ghost"
                onclick="return confirm('¿Restablecer todos los ajustes a valores por defecto?')">
          🔄 Restablecer
        </button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-title">📖 Guía rápida</div>
    <div class="didact-section">
      <h3>Corregir sesgo positivo</h3>
      <p>Si los textos negativos salen como positivos, tienes dos opciones:</p>
      <ul>
        <li><strong>Corrección de sesgo</strong>: sube el valor a 3-5. Esto suma puntos al puntaje negativo por cada palabra reconocida.</li>
        <li><strong>Factor negativo</strong>: súbelo a 1.3-1.5. Multiplica todo lo negativo que ya detecta el diccionario.</li>
      </ul>
      <h3>Stemming</h3>
      <p>El stemming amplía la cobertura del diccionario. Si "abandonar" no está pero "abandono" sí,
         el motor extrae la raíz "abandon-" y hereda los pesos. El factor de stemming (0.7 por defecto)
         reduce el peso heredado para evitar falsos positivos.</p>
      <h3>Suavizado</h3>
      <p>Un texto con muchas palabras neutras y pocas emocionales puede tener las pocas emocionales muy dominantes.
         Baja el suavizado a 0.7-0.8 para atenuar ese efecto.</p>
    </div>
  </div>

<?php endif; ?>

</div><!-- /container -->

<script>
// === CRÍTICO: definir showLayer ANTES de nada para que las pestañas funcionen
//     aunque el resto del script falle por algún dato corrupto.
function showLayer(name, btn) {
  document.querySelectorAll('.layer-content').forEach(el=>el.classList.remove('active'));
  document.querySelectorAll('.layer-tab').forEach(el=>el.classList.remove('active'));
  document.getElementById('layer-'+name)?.classList.add('active');
  if (btn) btn.classList.add('active');

  // Forzar redimensión de los charts dentro de la capa recién mostrada,
  // porque Chart.js no calcula bien el tamaño si el contenedor estaba oculto.
  setTimeout(() => {
    if (typeof Chart !== 'undefined' && Chart.instances) {
      Object.values(Chart.instances).forEach(c => {
        try {
          if (c.canvas?.closest('.layer-content.active')) c.resize();
        } catch(_) {}
      });
    }
  }, 50);
}

try {
const resultado      = <?= $jsonResultado ?>;
const resultadoCapas = <?= $jsonResultadoCapas ?>;
const historial      = <?= $jsonHistorial ?>;
const stats          = <?= $jsonStats ?>;
const catalogo       = <?= $jsonCatalogo ?>;

const C = {
  pos:'rgba(34,197,94,.75)',  posB:'rgba(34,197,94,1)',
  neg:'rgba(239,68,68,.75)',  negB:'rgba(239,68,68,1)',
  neu:'rgba(245,158,11,.75)', neuB:'rgba(245,158,11,1)',
  acc:'rgba(99,102,241,.75)', accB:'rgba(99,102,241,1)',
  grid:'rgba(255,255,255,.08)', text:'#94a3b8'
};
const baseOpts = {
  responsive: true,
  maintainAspectRatio: false,
  plugins:{ legend:{labels:{color:C.text}}, datalabels:{display:false} },
  scales:{ x:{ticks:{color:C.text},grid:{color:C.grid}}, y:{ticks:{color:C.text},grid:{color:C.grid}} }
};

document.getElementById('mainForm')?.addEventListener('submit', () => {
  document.getElementById('spinner').style.display='inline-block';
  document.getElementById('btnText').textContent='Analizando…';
});

const ta = document.getElementById('texto');
const cc = document.getElementById('charCount');
if (ta && cc) {
  const update = () => cc.textContent = ta.value.length + ' caracteres';
  ta.addEventListener('input', update); update();
}

// ---- Charts capa GENERAL ----
if (resultado) {
  const p=resultado.porcentaje_positivo, n=resultado.porcentaje_negativo, u=resultado.porcentaje_neutral;

  new Chart(document.getElementById('chartPie'), {
    type:'pie',
    data:{ labels:['Positivo','Negativo','Neutral'],
      datasets:[{ data:[p,n,u], backgroundColor:[C.pos,C.neg,C.neu],
                  borderColor:[C.posB,C.negB,C.neuB], borderWidth:2 }] },
    options:{ responsive:true, maintainAspectRatio:false,
      plugins:{ legend:{labels:{color:C.text}},
      datalabels:{ color:'#fff', font:{weight:'bold'}, formatter: v=>v.toFixed(1)+'%'} } }
  });

  const pol01  = ((parseFloat(resultado.polaridad)+1)/2*100).toFixed(1);
  const subj01 = (parseFloat(resultado.subjetividad)*100).toFixed(1);
  const int01  = (parseFloat(resultado.intensidad)/10*100).toFixed(1);
  const cob    = parseFloat(resultado.cobertura_pct);

  new Chart(document.getElementById('chartRadar'), {
    type:'radar',
    data:{
      labels:['Positividad','Negatividad','Subjetividad','Polaridad','Intensidad','Cobertura'],
      datasets:[{ label:'Análisis', data:[p,n,subj01,pol01,int01,cob],
        backgroundColor:'rgba(99,102,241,.25)', borderColor:C.accB,
        pointBackgroundColor:C.accB, borderWidth:2 }]
    },
    options:{ responsive:true, maintainAspectRatio:false,
      plugins:{legend:{labels:{color:C.text}}},
      scales:{ r:{ ticks:{color:C.text,backdropColor:'transparent'},
                   grid:{color:C.grid}, pointLabels:{color:C.text}, min:0, max:100 }} }
  });
}

// ---- Charts capas adicionales ----

// Rellena las puntuaciones con todas las categorías del catálogo (las faltantes a 0)
// Esto asegura que radar/polar siempre tengan suficientes ejes para dibujarse.
function rellenarCategorias(capa, puntajes) {
  const todasCats = catalogo[capa]?.categorias || [];
  const out = {};
  todasCats.forEach(cat => { out[cat] = puntajes?.[cat] || 0; });
  return out;
}

function chartFromPuntajes(canvasId, type, puntajes, label, mostrarMensajeSiVacio = true) {
  const el = document.getElementById(canvasId);
  if (!el) return;
  const labels = Object.keys(puntajes);
  const data   = Object.values(puntajes);
  const sumaTotal = data.reduce((a,b) => a + (parseFloat(b) || 0), 0);

  // Caso especial: todas las categorías están a 0 → mostrar mensaje en lugar de gráfico vacío
  if (sumaTotal === 0 && mostrarMensajeSiVacio) {
    const ctx = el.getContext('2d');
    ctx.clearRect(0, 0, el.width, el.height);
    ctx.fillStyle = C.text;
    ctx.font = '14px Segoe UI, sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    el.height = 200;
    el.width = el.parentElement.offsetWidth;
    ctx.fillText('— sin coincidencias en esta capa —', el.width / 2, el.height / 2);
    ctx.font = '11px Segoe UI, sans-serif';
    ctx.fillStyle = '#64748b';
    ctx.fillText('prueba con un texto más expresivo', el.width / 2, el.height / 2 + 22);
    return;
  }

  if (labels.length === 0) return;

  // Etiquetas más legibles
  const labelsBonitos = labels.map(l => l.replace(/_/g, ' '));

  if (type === 'radar') {
    new Chart(el, {
      type:'radar',
      data:{ labels: labelsBonitos, datasets:[{ label, data,
        backgroundColor:'rgba(99,102,241,.30)', borderColor:C.accB,
        pointBackgroundColor:C.accB, pointRadius:4, borderWidth:2 }] },
      options:{
        responsive: true, maintainAspectRatio: false,
        plugins:{ legend:{labels:{color:C.text}}, datalabels:{display:false} },
        scales:{ r:{
          ticks:{color:C.text, backdropColor:'transparent', stepSize:2},
          grid:{color:C.grid},
          pointLabels:{color:C.text, font:{size:11}},
          beginAtZero: true,
          suggestedMax: Math.max(...data, 5)
        }}
      }
    });
  } else if (type === 'polarArea') {
    const palette = [
      'rgba(99,102,241,.65)','rgba(34,197,94,.65)','rgba(245,158,11,.65)',
      'rgba(239,68,68,.65)','rgba(168,85,247,.65)','rgba(20,184,166,.65)',
      'rgba(236,72,153,.65)','rgba(251,146,60,.65)','rgba(14,165,233,.65)',
      'rgba(132,204,22,.65)','rgba(217,70,239,.65)','rgba(244,114,182,.65)',
      'rgba(250,204,21,.65)','rgba(56,189,248,.65)','rgba(190,242,100,.65)'
    ];
    new Chart(el, {
      type:'polarArea',
      data:{ labels: labelsBonitos, datasets:[{ data,
        backgroundColor: labels.map((_,i) => palette[i % palette.length]),
        borderColor: '#1e293b', borderWidth: 1 }] },
      options:{
        responsive: true, maintainAspectRatio: false,
        plugins:{ legend:{position:'right', labels:{color:C.text, font:{size:11}}},
                  datalabels:{display:false} },
        scales:{ r:{ ticks:{color:C.text, backdropColor:'transparent'},
                     grid:{color:C.grid}, beginAtZero:true } }
      }
    });
  } else {
    // bar
    new Chart(el, {
      type:'bar',
      data:{ labels: labelsBonitos, datasets:[{ label, data,
        backgroundColor:C.acc, borderColor:C.accB, borderWidth:2, borderRadius:6 }] },
      options:{
        responsive: true, maintainAspectRatio: false,
        plugins:{ legend:{labels:{color:C.text}}, datalabels:{display:false} },
        scales:{
          x:{ ticks:{color:C.text, font:{size:10}}, grid:{color:C.grid} },
          y:{ ticks:{color:C.text}, grid:{color:C.grid}, beginAtZero:true }
        }
      }
    });
  }
}

if (resultadoCapas) {
  // Rellenar siempre con todas las categorías de la capa (faltantes = 0)
  // para que radar/polar siempre tengan suficientes ejes para dibujarse.
  const ebFull = rellenarCategorias('emociones_basicas',   resultadoCapas.emociones_basicas?.puntajes);
  const ecFull = rellenarCategorias('emociones_complejas', resultadoCapas.emociones_complejas?.puntajes);
  const inFull = rellenarCategorias('intencion',           resultadoCapas.intencion?.puntajes);
  const coFull = rellenarCategorias('comercial',           resultadoCapas.comercial?.puntajes);

  chartFromPuntajes('chartPlutchik',       'radar',     ebFull, 'Emociones básicas');
  chartFromPuntajes('chartEmocionesBarra', 'bar',       ebFull, 'Pesos');
  chartFromPuntajes('chartCompPolar',      'polarArea', ecFull, 'Emociones complejas');
  chartFromPuntajes('chartIntencion',      'polarArea', inFull, 'Intenciones');
  chartFromPuntajes('chartComercial',      'bar',       coFull, 'Señales comerciales');

  // Multi-radar comparativo: suma normalizada por capa
  const sums = {};
  ['emociones_basicas','emociones_complejas','intencion','comercial'].forEach(c => {
    const arr = Object.values(resultadoCapas[c]?.puntajes || {});
    sums[c] = arr.reduce((a,b)=>a+b, 0);
  });
  // añadir capa general
  const polNorm = (parseFloat(resultado?.polaridad || 0) + 1) / 2 * 10;
  sums['general'] = polNorm;

  const mr = document.getElementById('chartMultiRadar');
  if (mr) {
    new Chart(mr, {
      type:'radar',
      data:{
        labels:['General','Emociones básicas','Emociones complejas','Intención','Comercial'],
        datasets:[{ label:'Activación por capa',
          data:[sums.general, sums.emociones_basicas, sums.emociones_complejas, sums.intencion, sums.comercial],
          backgroundColor:'rgba(99,102,241,.3)', borderColor:C.accB,
          pointBackgroundColor:C.accB, borderWidth:2 }]
      },
      options:{ responsive:true, maintainAspectRatio:false,
        plugins:{legend:{labels:{color:C.text}}},
        scales:{ r:{ ticks:{color:C.text,backdropColor:'transparent'},
                     grid:{color:C.grid}, pointLabels:{color:C.text} }} }
    });
  }
}

// ---- Charts HISTÓRICO ----
if (historial && historial.length > 0) {
  const labels = historial.map(h=>h.fecha_fmt);
  const posArr = historial.map(h=>parseFloat(h.porcentaje_positivo));
  const negArr = historial.map(h=>parseFloat(h.porcentaje_negativo));
  const neuArr = historial.map(h=>parseFloat(h.porcentaje_neutral));
  const polArr = historial.map(h=>parseFloat(h.polaridad));

  if (document.getElementById('chartHistLine')) {
    new Chart(document.getElementById('chartHistLine'), {
      type:'line',
      data:{ labels, datasets:[
        {label:'Positivo',data:posArr,borderColor:C.posB,backgroundColor:'rgba(34,197,94,.1)',tension:.3,fill:true},
        {label:'Negativo',data:negArr,borderColor:C.negB,backgroundColor:'rgba(239,68,68,.1)',tension:.3,fill:true},
        {label:'Neutral', data:neuArr,borderColor:C.neuB,backgroundColor:'rgba(245,158,11,.1)',tension:.3,fill:true}
      ]},
      options:baseOpts
    });
  }

  if (stats && document.getElementById('chartHistPie')) {
    new Chart(document.getElementById('chartHistPie'), {
      type:'pie',
      data:{ labels:['Positivos','Negativos','Neutrales'],
        datasets:[{ data:[stats.total_positivos,stats.total_negativos,stats.total_neutrales],
          backgroundColor:[C.pos,C.neg,C.neu],borderColor:[C.posB,C.negB,C.neuB],borderWidth:2 }] },
      options:{ responsive:true, maintainAspectRatio:false,
        plugins:{legend:{labels:{color:C.text}},
        datalabels:{color:'#fff',font:{weight:'bold'},formatter:v=>v}} }
    });
  }

  if (document.getElementById('chartHistPolar')) {
    new Chart(document.getElementById('chartHistPolar'), {
      type:'bar',
      data:{ labels, datasets:[{ label:'Polaridad', data:polArr,
        backgroundColor:polArr.map(v=>v>=0?C.pos:C.neg),
        borderColor: polArr.map(v=>v>=0?C.posB:C.negB),
        borderWidth:2, borderRadius:6 }] },
      options:{ ...baseOpts,
        scales:{ ...baseOpts.scales, y:{...baseOpts.scales.y, min:-1, max:1} } }
    });
  }

  if (document.getElementById('chartHistScatter')) {
    const scatter = historial.map(h=>({x:parseFloat(h.porcentaje_positivo), y:parseFloat(h.porcentaje_negativo)}));
    new Chart(document.getElementById('chartHistScatter'), {
      type:'scatter',
      data:{ datasets:[{ label:'Análisis', data:scatter, backgroundColor:C.acc, pointRadius:7 }] },
      options:{ ...baseOpts,
        scales:{
          x:{...baseOpts.scales.x, title:{display:true,text:'% Positivo',color:C.text}},
          y:{...baseOpts.scales.y, title:{display:true,text:'% Negativo',color:C.text}}
        } }
    });
  }
}

} catch (err) {
  // Si algo falla en el bloque principal, no rompemos las pestañas (showLayer ya está definida arriba).
  console.error('SentiManPHP: error renderizando gráficos —', err);
}

async function probarAPI() {
  const txt  = document.getElementById('apiTestInput').value.trim();
  const capa = document.getElementById('apiTestCapa').value;
  const out  = document.getElementById('apiTestOutput');
  if (!txt) { out.textContent='⚠️ Escribe un texto.'; return; }
  out.textContent='⏳ Consultando API…';
  try {
    let url = 'api.php?texto=' + encodeURIComponent(txt);
    if (capa) url += '&capa=' + encodeURIComponent(capa);
    const r = await fetch(url);
    out.textContent = await r.text();
  } catch(e) { out.textContent='❌ Error: '+e.message; }
}
</script>
</body>
</html>
