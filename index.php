<?php
// ============================================================
//  SentiManPHP v2 — Interfaz principal
// ============================================================
require_once 'config.php';
require_once 'sentiman.php';

$resultado    = null;
$texto        = '';
$error        = '';
$historialStats = null;
$historialReciente = [];

$con = getConexion();

// ---- Procesar formulario ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $texto = trim($_POST['texto'] ?? '');
    if ($texto === '') {
        $error = 'Por favor, escribe o pega algún texto antes de analizar.';
    } else {
        $resultado = analizarSentimiento($texto, $con);
        guardarHistorico($texto, $resultado, 'web', $con);
    }
}

// ---- Cargar estadísticas históricas ----
$resStats = $con->query("SELECT * FROM v_estadisticas LIMIT 1");
if ($resStats && $resStats->num_rows > 0) {
    $historialStats = $resStats->fetch_assoc();
}

// ---- Últimos 20 análisis para gráficas históricas ----
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
$historialReciente = array_reverse($historialReciente); // cronológico

$con->close();

// ---- Preparar datos JSON para JS ----
$jsonResultado = $resultado ? json_encode($resultado, JSON_UNESCAPED_UNICODE) : 'null';
$jsonHistorial = json_encode($historialReciente, JSON_UNESCAPED_UNICODE);
$jsonStats     = json_encode($historialStats, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SentiManPHP v2 — Análisis de Sentimiento</title>
  <script src="chart.min.js"></script>
  <script src="chartjs-plugin-datalabels.min.js"></script>
  <style>
    /* ===== VARIABLES Y RESET ===== */
    :root {
      --pos: #22c55e; --pos-light: #dcfce7; --pos-dark: #15803d;
      --neg: #ef4444; --neg-light: #fee2e2; --neg-dark: #b91c1c;
      --neu: #f59e0b; --neu-light: #fef3c7; --neu-dark: #b45309;
      --bg: #0f172a; --surface: #1e293b; --surface2: #334155;
      --text: #e2e8f0; --text-muted: #94a3b8; --accent: #6366f1;
      --border: #334155; --radius: 12px; --shadow: 0 4px 24px rgba(0,0,0,.4);
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg);
           color: var(--text); min-height: 100vh; }
    a { color: var(--accent); }

    /* ===== LAYOUT ===== */
    .topbar { background: var(--surface); border-bottom: 1px solid var(--border);
              padding: 12px 24px; display: flex; align-items: center; gap: 16px;
              position: sticky; top: 0; z-index: 100; }
    .topbar h1 { font-size: 1.4rem; font-weight: 700; }
    .topbar .badge { background: var(--accent); color: #fff; font-size: .7rem;
                     padding: 2px 8px; border-radius: 99px; }
    nav { display: flex; gap: 8px; margin-left: auto; }
    nav a { color: var(--text-muted); text-decoration: none; padding: 6px 14px;
            border-radius: 8px; font-size: .85rem; transition: background .2s; }
    nav a:hover, nav a.active { background: var(--surface2); color: var(--text); }

    .container { max-width: 1280px; margin: 0 auto; padding: 24px; }

    /* ===== TABS ===== */
    .tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--border);
            margin-bottom: 28px; }
    .tab-btn { background: none; border: none; color: var(--text-muted);
               padding: 10px 20px; cursor: pointer; font-size: .9rem;
               border-bottom: 2px solid transparent; transition: all .2s; }
    .tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); }
    .tab-content { display: none; }
    .tab-content.active { display: block; }

    /* ===== CARDS ===== */
    .card { background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow); }
    .card-title { font-size: 1rem; font-weight: 600; margin-bottom: 16px;
                  color: var(--text-muted); text-transform: uppercase;
                  letter-spacing: .05em; display: flex; align-items: center; gap: 8px; }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
    .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
    @media(max-width: 768px) {
      .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; }
    }

    /* ===== FORMULARIO ===== */
    .form-group { margin-bottom: 16px; }
    label { display: block; margin-bottom: 6px; font-size: .85rem; color: var(--text-muted); }
    textarea { width: 100%; background: var(--surface2); border: 1px solid var(--border);
               border-radius: 8px; color: var(--text); padding: 14px; font-size: .95rem;
               resize: vertical; min-height: 160px; transition: border-color .2s; }
    textarea:focus { outline: none; border-color: var(--accent); }
    .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 24px;
           border-radius: 8px; border: none; cursor: pointer; font-size: .95rem;
           font-weight: 600; transition: opacity .2s; }
    .btn-primary { background: var(--accent); color: #fff; }
    .btn-primary:hover { opacity: .85; }
    .btn-ghost { background: var(--surface2); color: var(--text); }
    .error-msg { color: var(--neg); background: var(--neg-light); color: #7f1d1d;
                 padding: 10px 16px; border-radius: 8px; margin-bottom: 16px; }

    /* ===== MÉTRICAS RÁPIDAS ===== */
    .metric-card { background: var(--surface); border: 1px solid var(--border);
                   border-radius: var(--radius); padding: 20px; text-align: center; }
    .metric-card .val { font-size: 2.2rem; font-weight: 700; }
    .metric-card .lbl { font-size: .8rem; color: var(--text-muted); margin-top: 4px; }
    .metric-card.pos { border-top: 3px solid var(--pos); }
    .metric-card.neg { border-top: 3px solid var(--neg); }
    .metric-card.neu { border-top: 3px solid var(--neu); }
    .metric-card.accent { border-top: 3px solid var(--accent); }
    .pos .val { color: var(--pos); }
    .neg .val { color: var(--neg); }
    .neu .val { color: var(--neu); }
    .accent .val { color: var(--accent); }

    /* ===== SENTIMIENTO GLOBAL ===== */
    .global-badge { display: inline-block; padding: 8px 24px; border-radius: 99px;
                    font-size: 1.2rem; font-weight: 700; text-transform: uppercase;
                    letter-spacing: .08em; }
    .global-badge.positivo { background: var(--pos-light); color: var(--pos-dark); }
    .global-badge.negativo { background: var(--neg-light); color: var(--neg-dark); }
    .global-badge.neutral  { background: var(--neu-light); color: var(--neu-dark); }

    /* ===== GAUGE POLARIDAD ===== */
    .gauge-wrap { position: relative; width: 220px; height: 120px; margin: 0 auto; }
    .gauge-label { text-align: center; font-size: .85rem; color: var(--text-muted);
                   margin-top: 4px; }

    /* ===== BARRA DE PROGRESO ===== */
    .prog-bar { height: 12px; border-radius: 99px; background: var(--surface2);
                overflow: hidden; margin: 4px 0; }
    .prog-bar .fill { height: 100%; border-radius: 99px; transition: width 1s ease; }
    .fill-pos { background: linear-gradient(90deg, #16a34a, #22c55e); }
    .fill-neg { background: linear-gradient(90deg, #b91c1c, #ef4444); }
    .fill-neu { background: linear-gradient(90deg, #b45309, #f59e0b); }
    .prog-label { display: flex; justify-content: space-between; font-size: .82rem;
                  color: var(--text-muted); }

    /* ===== TABLA DETALLE ===== */
    .word-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
    .word-table th { background: var(--surface2); padding: 10px 12px;
                     text-align: left; color: var(--text-muted); }
    .word-table td { padding: 8px 12px; border-bottom: 1px solid var(--border); }
    .word-table tr:hover td { background: var(--surface2); }
    .chip { display: inline-block; padding: 2px 10px; border-radius: 99px;
            font-size: .75rem; font-weight: 600; }
    .chip.pos { background: var(--pos-light); color: var(--pos-dark); }
    .chip.neg { background: var(--neg-light); color: var(--neg-dark); }
    .chip.neu { background: var(--neu-light); color: var(--neu-dark); }
    .chip.negado { background: #e0e7ff; color: #3730a3; }

    /* ===== CANVAS CHARTS ===== */
    .chart-wrap { position: relative; }
    .chart-wrap canvas { max-height: 320px; }

    /* ===== HISTÓRICO ===== */
    .hist-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
    .hist-table th { background: var(--surface2); padding: 8px 12px; color: var(--text-muted); }
    .hist-table td { padding: 7px 12px; border-bottom: 1px solid var(--border); }
    .hist-table tr:hover td { background: rgba(255,255,255,.02); }
    .source-badge { font-size: .72rem; padding: 1px 7px; border-radius: 99px; }
    .source-badge.web { background: #dbeafe; color: #1e40af; }
    .source-badge.api { background: #fce7f3; color: #9d174d; }

    /* ===== SECCIÓN DIDÁCTICA ===== */
    .didact-section { line-height: 1.7; }
    .didact-section h3 { color: var(--accent); margin: 20px 0 8px; }
    .didact-section p  { color: var(--text-muted); margin-bottom: 12px; }
    .didact-section ul  { color: var(--text-muted); padding-left: 20px; margin-bottom: 12px; }
    .didact-section li  { margin-bottom: 6px; }
    .code-block { background: var(--surface2); border-radius: 8px; padding: 16px;
                  font-family: 'Courier New', monospace; font-size: .82rem;
                  color: #a5f3fc; overflow-x: auto; margin: 12px 0; }
    .tip-box { background: rgba(99,102,241,.15); border-left: 4px solid var(--accent);
               padding: 12px 16px; border-radius: 0 8px 8px 0; margin: 12px 0; }
    .tip-box p { color: var(--text); margin: 0; }

    /* ===== SCROLLABLE ===== */
    .scroll-x { overflow-x: auto; }

    /* ===== SPINNER ===== */
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
  <span class="badge">v2</span>
  <nav>
    <a href="?tab=analisis" class="<?= (!isset($_GET['tab']) || $_GET['tab']==='analisis') ? 'active' : '' ?>">Análisis</a>
    <a href="?tab=historico" class="<?= ($_GET['tab']??'')=='historico' ? 'active' : '' ?>">Histórico</a>
    <a href="?tab=api" class="<?= ($_GET['tab']??'')=='api' ? 'active' : '' ?>">API</a>
    <a href="?tab=aprende" class="<?= ($_GET['tab']??'')=='aprende' ? 'active' : '' ?>">Aprende</a>
  </nav>
</div>

<div class="container">

<!-- ================================================================ -->
<!-- PESTAÑA: ANÁLISIS                                                  -->
<!-- ================================================================ -->
<div id="tab-analisis" class="tab-content <?= (!isset($_GET['tab']) || $_GET['tab']==='analisis') ? 'active' : '' ?>">

  <div class="grid-2" style="gap:24px; margin-bottom:24px;">

    <!-- Formulario -->
    <div class="card">
      <div class="card-title">📝 Introduce tu texto</div>
      <?php if ($error): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="post" id="mainForm">
        <div class="form-group">
          <label for="texto">Pega aquí el texto que quieres analizar (artículo, tweet, reseña…)</label>
          <textarea id="texto" name="texto" placeholder="Escribe o pega aquí tu texto…"><?= htmlspecialchars($texto) ?></textarea>
        </div>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
          <button type="submit" name="submit" class="btn btn-primary" id="submitBtn">
            <span class="spinner" id="spinner"></span>
            <span id="btnText">🔍 Analizar sentimiento</span>
          </button>
          <button type="button" class="btn btn-ghost" onclick="document.getElementById('texto').value=''; document.getElementById('texto').focus();">
            🗑️ Limpiar
          </button>
          <span style="font-size:.8rem; color:var(--text-muted);" id="charCount">0 caracteres</span>
        </div>
      </form>
    </div>

    <!-- Instrucciones rápidas -->
    <div class="card" style="background: linear-gradient(135deg,#1e293b,#0f172a);">
      <div class="card-title">💡 ¿Cómo funciona?</div>
      <div class="didact-section">
        <p>El <strong>análisis de sentimiento</strong> es una técnica de PLN (Procesamiento de Lenguaje Natural) que evalúa automáticamente si un texto expresa emociones <strong style="color:var(--pos)">positivas</strong>, <strong style="color:var(--neg)">negativas</strong> o <strong style="color:var(--neu)">neutras</strong>.</p>
        <p>Este sistema usa un <strong>diccionario de pesos</strong> con más de 6.500 términos en español. Cada palabra tiene asignado un valor numérico (0–10) para positiva, negativa y neutral.</p>
        <ul>
          <li>🔤 <strong>Tokeniza</strong> el texto (lo divide en palabras)</li>
          <li>📖 <strong>Busca</strong> cada palabra en el diccionario</li>
          <li>➕➖ <strong>Suma</strong> los pesos considerando negaciones e intensificadores</li>
          <li>📊 <strong>Calcula</strong> porcentajes y métricas avanzadas</li>
        </ul>
        <div class="tip-box">
          <p>✅ <strong>Novedad v2:</strong> detecta negaciones ("no es bueno") e intensificadores ("muy feliz"), bigramas y guarda un histórico de todos tus análisis.</p>
        </div>
      </div>
    </div>
  </div>

  <?php if ($resultado): ?>

  <!-- RESULTADO GLOBAL -->
  <div class="card" style="margin-bottom:24px; text-align:center;">
    <div class="card-title" style="justify-content:center;">🎯 Resultado global</div>
    <div style="margin-bottom:12px;">
      <span class="global-badge <?= $resultado['sentimiento_global'] ?>">
        <?= strtoupper($resultado['sentimiento_global']) ?>
      </span>
    </div>
    <p style="color:var(--text-muted); font-size:.95rem;"><?= htmlspecialchars($resultado['etiqueta']) ?></p>
  </div>

  <!-- MÉTRICAS RÁPIDAS -->
  <div class="grid-4" style="margin-bottom:24px;">
    <div class="metric-card pos">
      <div class="val"><?= $resultado['porcentaje_positivo'] ?>%</div>
      <div class="lbl">Positivo</div>
    </div>
    <div class="metric-card neg">
      <div class="val"><?= $resultado['porcentaje_negativo'] ?>%</div>
      <div class="lbl">Negativo</div>
    </div>
    <div class="metric-card neu">
      <div class="val"><?= $resultado['porcentaje_neutral'] ?>%</div>
      <div class="lbl">Neutral</div>
    </div>
    <div class="metric-card accent">
      <div class="val"><?= $resultado['cobertura_pct'] ?>%</div>
      <div class="lbl">Cobertura dic.</div>
    </div>
  </div>

  <!-- BARRAS DE PROGRESO -->
  <div class="card" style="margin-bottom:24px;">
    <div class="card-title">📊 Distribución de sentimiento</div>
    <div class="prog-label"><span>Positivo</span><span><?= $resultado['porcentaje_positivo'] ?>%</span></div>
    <div class="prog-bar"><div class="fill fill-pos" style="width:<?= $resultado['porcentaje_positivo'] ?>%"></div></div>
    <div class="prog-label" style="margin-top:10px;"><span>Negativo</span><span><?= $resultado['porcentaje_negativo'] ?>%</span></div>
    <div class="prog-bar"><div class="fill fill-neg" style="width:<?= $resultado['porcentaje_negativo'] ?>%"></div></div>
    <div class="prog-label" style="margin-top:10px;"><span>Neutral</span><span><?= $resultado['porcentaje_neutral'] ?>%</span></div>
    <div class="prog-bar"><div class="fill fill-neu" style="width:<?= $resultado['porcentaje_neutral'] ?>%"></div></div>
  </div>

  <!-- GRÁFICAS ANÁLISIS ACTUAL -->
  <div class="grid-2" style="margin-bottom:24px;">
    <div class="card">
      <div class="card-title">🥧 Gráfica circular</div>
      <div class="chart-wrap"><canvas id="chartPie"></canvas></div>
    </div>
    <div class="card">
      <div class="card-title">📡 Gráfica radar (métricas)</div>
      <div class="chart-wrap"><canvas id="chartRadar"></canvas></div>
    </div>
  </div>

  <div class="grid-2" style="margin-bottom:24px;">
    <div class="card">
      <div class="card-title">📈 Barras comparativas</div>
      <div class="chart-wrap"><canvas id="chartBar"></canvas></div>
    </div>
    <div class="card">
      <div class="card-title">🌡️ Polaridad y subjetividad</div>
      <div class="chart-wrap"><canvas id="chartPolar"></canvas></div>
      <p style="text-align:center; font-size:.8rem; color:var(--text-muted); margin-top:8px;">
        Polaridad: <strong><?= $resultado['polaridad'] ?></strong> &nbsp;|&nbsp;
        Subjetividad: <strong><?= $resultado['subjetividad'] ?></strong> &nbsp;|&nbsp;
        Intensidad: <strong><?= $resultado['intensidad'] ?></strong>
      </p>
    </div>
  </div>

  <!-- TABLA DETALLE PALABRAS -->
  <?php if (count($resultado['detalle']) > 0): ?>
  <div class="card" style="margin-bottom:24px;">
    <div class="card-title">🔍 Palabras reconocidas en el texto (<?= count($resultado['detalle']) ?>)</div>
    <div class="scroll-x">
      <table class="word-table">
        <thead>
          <tr>
            <th>Palabra / Expresión</th>
            <th>Tipo</th>
            <th>Positiva</th>
            <th>Negativa</th>
            <th>Neutral</th>
            <th>Factor</th>
            <th>Modificadores</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($resultado['detalle'] as $d): ?>
          <tr>
            <td><strong><?= htmlspecialchars($d['palabra']) ?></strong></td>
            <td><?= $d['tipo'] === 'bigrama' ? '🔗 bigrama' : '🔤 palabra' ?></td>
            <td style="color:var(--pos)"><?= $d['pos'] ?></td>
            <td style="color:var(--neg)"><?= $d['neg'] ?></td>
            <td style="color:var(--neu)"><?= $d['neu'] ?></td>
            <td>×<?= number_format($d['factor'],1) ?></td>
            <td>
              <?php if ($d['negado']): ?><span class="chip negado">negada</span><?php endif; ?>
              <?php if ($d['factor'] > 1.2): ?><span class="chip pos">intensificada</span><?php endif; ?>
              <?php if ($d['factor'] < 0.9): ?><span class="chip neu">atenuada</span><?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p style="font-size:.8rem; color:var(--text-muted); margin-top:10px;">
      ℹ️ Solo se muestran las <?= $resultado['total_palabras'] ?> palabras del texto (<?= $resultado['palabras_encontradas'] ?> encontradas, cobertura <?= $resultado['cobertura_pct'] ?>%).
    </p>
  </div>
  <?php endif; ?>

  <?php endif; // fin if resultado ?>
</div><!-- /tab-analisis -->


<!-- ================================================================ -->
<!-- PESTAÑA: HISTÓRICO                                                 -->
<!-- ================================================================ -->
<div id="tab-historico" class="tab-content <?= ($_GET['tab']??'')=='historico' ? 'active' : '' ?>">

  <?php if ($historialStats && (int)$historialStats['total_analisis'] > 0): ?>

  <!-- Resumen estadístico -->
  <div class="grid-4" style="margin-bottom:24px;">
    <div class="metric-card accent">
      <div class="val"><?= $historialStats['total_analisis'] ?></div>
      <div class="lbl">Análisis totales</div>
    </div>
    <div class="metric-card pos">
      <div class="val"><?= round($historialStats['media_positivo'],1) ?>%</div>
      <div class="lbl">Media positivo</div>
    </div>
    <div class="metric-card neg">
      <div class="val"><?= round($historialStats['media_negativo'],1) ?>%</div>
      <div class="lbl">Media negativo</div>
    </div>
    <div class="metric-card neu">
      <div class="val"><?= round($historialStats['media_neutral'],1) ?>%</div>
      <div class="lbl">Media neutral</div>
    </div>
  </div>

  <div class="grid-3" style="margin-bottom:24px;">
    <div class="metric-card pos">
      <div class="val"><?= $historialStats['total_positivos'] ?></div>
      <div class="lbl">Análisis positivos</div>
    </div>
    <div class="metric-card neg">
      <div class="val"><?= $historialStats['total_negativos'] ?></div>
      <div class="lbl">Análisis negativos</div>
    </div>
    <div class="metric-card neu">
      <div class="val"><?= $historialStats['total_neutrales'] ?></div>
      <div class="lbl">Análisis neutrales</div>
    </div>
  </div>

  <!-- Gráficas históricas -->
  <div class="grid-2" style="margin-bottom:24px;">
    <div class="card">
      <div class="card-title">📈 Evolución temporal (últimos 20)</div>
      <div class="chart-wrap"><canvas id="chartHistLine"></canvas></div>
    </div>
    <div class="card">
      <div class="card-title">🥧 Distribución global de sentimientos</div>
      <div class="chart-wrap"><canvas id="chartHistPie"></canvas></div>
    </div>
  </div>

  <div class="grid-2" style="margin-bottom:24px;">
    <div class="card">
      <div class="card-title">📊 Polaridad histórica</div>
      <div class="chart-wrap"><canvas id="chartHistPolar"></canvas></div>
    </div>
    <div class="card">
      <div class="card-title">🎯 Dispersión positivo vs negativo</div>
      <div class="chart-wrap"><canvas id="chartHistScatter"></canvas></div>
    </div>
  </div>

  <!-- Tabla histórico reciente -->
  <div class="card">
    <div class="card-title">🕐 Últimos análisis</div>
    <div class="scroll-x">
      <table class="hist-table">
        <thead>
          <tr>
            <th>#</th><th>Fecha</th><th>Fuente</th><th>Texto</th>
            <th>Sentimiento</th><th>Positivo</th><th>Negativo</th><th>Neutral</th><th>Polaridad</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach(array_reverse($historialReciente) as $h): ?>
          <tr>
            <td><?= $h['id'] ?></td>
            <td><?= $h['fecha_fmt'] ?></td>
            <td><span class="source-badge <?= $h['fuente'] ?>"><?= $h['fuente'] ?></span></td>
            <td style="max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"
                title="<?= htmlspecialchars($h['texto_corto']) ?>">
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
    <h3 style="margin-bottom:8px;">Aún no hay análisis guardados</h3>
    <p style="color:var(--text-muted);">Analiza tu primer texto y aquí aparecerán las estadísticas históricas.</p>
  </div>
  <?php endif; ?>
</div><!-- /tab-historico -->


<!-- ================================================================ -->
<!-- PESTAÑA: API                                                        -->
<!-- ================================================================ -->
<div id="tab-api" class="tab-content <?= ($_GET['tab']??'')=='api' ? 'active' : '' ?>">
  <div class="card" style="margin-bottom:24px;">
    <div class="card-title">🔌 API de Análisis de Sentimiento</div>
    <div class="didact-section">

      <h3>¿Qué es la API?</h3>
      <p>La API permite que tu <strong>scraper o programa</strong> envíe texto y reciba el resultado del análisis automáticamente, sin necesidad de abrir el navegador.</p>

      <h3>📍 URL de la API</h3>
      <div class="code-block">http://localhost/sentiman/api.php</div>

      <h3>📤 Cómo enviar texto</h3>
      <p>Puedes enviarlo por <strong>GET</strong> (en la URL) o <strong>POST</strong>. El parámetro se llama siempre <code>texto</code>.</p>
      <div class="code-block">http://localhost/sentiman/api.php?texto=Esta+película+es+maravillosa+y+me+encantó</div>

      <h3>📥 Respuesta (texto plano, fácil de imprimir)</h3>
      <div class="code-block">sentimiento:positivo
positivo:78.50
negativo:12.30
neutral:9.20
polaridad:0.73
subjetividad:0.68
intensidad:7.40
etiqueta:Positivo
palabras:8
encontradas:4
cobertura:50.00</div>

      <h3>💻 Código PHP para tus scrapers</h3>
      <p>Copia este código en tu scraper PHP:</p>
      <div class="code-block">&lt;?php
// === USO BÁSICO DE LA API ===
$texto = "El gobierno toma medidas urgentes ante la crisis económica";

// Enviar el texto a la API
$url = "http://localhost/sentiman/api.php?texto=" . urlencode($texto);
$respuesta = file_get_contents($url);

// Mostrar todo directamente
echo $respuesta;

// === O extraer un dato concreto ===
$lineas = explode("\n", trim($respuesta));
$datos = [];
foreach ($lineas as $linea) {
    if (strpos($linea, ':') !== false) {
        list($clave, $valor) = explode(':', $linea, 2);
        $datos[$clave] = $valor;
    }
}

echo "El sentimiento es: " . $datos['sentimiento'];
echo "Porcentaje positivo: " . $datos['positivo'] . "%";
?&gt;</div>

      <h3>💻 Código Python para tus scrapers</h3>
      <div class="code-block">import requests, urllib.parse

texto = "Me alegra ver tantos avances en educación"
url = "http://localhost/sentiman/api.php"
resp = requests.get(url, params={"texto": texto})
print(resp.text)

# Convertir a diccionario
datos = dict(l.split(':',1) for l in resp.text.strip().split('\n') if ':' in l)
print("Sentimiento:", datos['sentimiento'])
print("Positivo:", datos['positivo'], "%")</div>

      <div class="tip-box">
        <p>🚀 <strong>Pro tip:</strong> Si tu scraper extrae noticias, tweets o reseñas, envíalas una a una con un bucle. Cada llamada se guarda automáticamente en el histórico, así puedes ver la evolución del sentimiento a lo largo del tiempo.</p>
      </div>
    </div>
  </div>

  <!-- Probador en línea -->
  <div class="card">
    <div class="card-title">🧪 Probar la API desde aquí</div>
    <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px;">
      <input type="text" id="apiTestInput" placeholder="Escribe un texto de prueba…"
        style="flex:1; background:var(--surface2); border:1px solid var(--border);
               border-radius:8px; padding:10px 14px; color:var(--text); font-size:.9rem; min-width:200px;">
      <button class="btn btn-primary" onclick="probarAPI()">▶ Probar</button>
    </div>
    <pre id="apiTestOutput" style="background:var(--surface2); border-radius:8px; padding:16px;
      font-size:.85rem; color:#a5f3fc; min-height:60px; white-space:pre-wrap;">
Aquí aparecerá la respuesta de la API…</pre>
  </div>
</div><!-- /tab-api -->


<!-- ================================================================ -->
<!-- PESTAÑA: APRENDE                                                    -->
<!-- ================================================================ -->
<div id="tab-aprende" class="tab-content <?= ($_GET['tab']??'')=='aprende' ? 'active' : '' ?>">
  <div class="grid-2" style="margin-bottom:24px;">

    <div class="card didact-section">
      <div class="card-title">📚 ¿Qué es el análisis de sentimiento?</div>
      <p>El <strong>análisis de sentimiento</strong> (también llamado <em>opinion mining</em>) es una rama del <strong>Procesamiento de Lenguaje Natural (PLN)</strong> que usa algoritmos para identificar y extraer información subjetiva de un texto.</p>
      <p>Responde preguntas como: ¿Esta reseña es positiva o negativa? ¿Este tweet expresa enojo, tristeza o alegría? ¿Cómo percibe la audiencia a un político?</p>

      <h3>🌍 ¿Por qué es importante?</h3>
      <ul>
        <li><strong>Marketing:</strong> analizar opiniones de clientes en redes sociales</li>
        <li><strong>Política:</strong> medir el clima de opinión sobre candidatos o leyes</li>
        <li><strong>Finanzas:</strong> detectar si las noticias sobre una empresa son positivas o negativas (influye en bolsa)</li>
        <li><strong>Salud:</strong> detectar señales de depresión en textos de pacientes</li>
        <li><strong>Periodismo:</strong> analizar el tono de medios de comunicación</li>
      </ul>

      <h3>🔬 Enfoques principales</h3>
      <ul>
        <li><strong>Basado en diccionario</strong> (lexicón): este sistema. Rápido, interpretable, sin necesidad de datos etiquetados.</li>
        <li><strong>Machine Learning</strong>: entrena un modelo con textos etiquetados. Más preciso pero necesita miles de ejemplos.</li>
        <li><strong>Deep Learning</strong>: transformers como BERT o GPT. Estado del arte, pero requiere GPU y grandes recursos.</li>
      </ul>
    </div>

    <div class="card didact-section">
      <div class="card-title">📐 Métricas que calcula SentiManPHP</div>

      <h3>Porcentajes (positivo / negativo / neutral)</h3>
      <p>Representan qué proporción del peso léxico total del texto corresponde a cada categoría. Si <strong>positivo = 70%</strong>, el vocabulario usado es mayoritariamente positivo.</p>

      <h3>Polaridad (−1 a +1)</h3>
      <p>Mide la <em>dirección</em> del sentimiento. <strong>+1</strong> = extremadamente positivo, <strong>−1</strong> = extremadamente negativo, <strong>0</strong> = equilibrado.</p>
      <div class="code-block">polaridad = (suma_positiva − suma_negativa) / (suma_positiva + suma_negativa)</div>

      <h3>Subjetividad (0 a 1)</h3>
      <p>Indica cuánto sentimiento hay en el texto. <strong>0</strong> = texto completamente objetivo/factual, <strong>1</strong> = muy subjetivo/emocional.</p>
      <div class="code-block">subjetividad = (positiva + negativa) / total_pesos</div>

      <h3>Intensidad</h3>
      <p>Promedio del peso máximo de cada palabra reconocida. Un texto con palabras de peso 9–10 tiene mayor intensidad que uno con palabras de peso 3–4.</p>

      <h3>Cobertura del diccionario</h3>
      <p>Porcentaje de palabras del texto que existen en el diccionario. Una cobertura baja puede indicar texto muy técnico, jerga, errores ortográficos o idioma diferente.</p>
    </div>
  </div>

  <div class="card didact-section" style="margin-bottom:24px;">
    <div class="card-title">⚙️ Cómo funciona el motor de análisis (paso a paso)</div>
    <div class="grid-2">
      <div>
        <h3>1️⃣ Tokenización</h3>
        <p>El texto se convierte a minúsculas, se eliminan signos de puntuación y se divide en tokens (palabras). También se generan <strong>bigramas</strong> (pares de palabras consecutivas) para detectar expresiones compuestas.</p>
        <div class="code-block">"No me gusta nada" → ["no","me","gusta","nada"]
bigramas → ["no me", "me gusta", "gusta nada"]</div>

        <h3>2️⃣ Detección de negaciones</h3>
        <p>Si aparece una negación (no, ni, nunca, jamás…) en las 2 palabras anteriores, los pesos se invierten: lo positivo se convierte en negativo y viceversa, con un factor reductor del 20%.</p>
        <div class="code-block">"no es bueno" → "bueno" (pos:8) se convierte en neg:6.4</div>
      </div>
      <div>
        <h3>3️⃣ Intensificadores y atenuadores</h3>
        <p>Palabras como "muy", "súper", "extremadamente" multiplican el peso de la siguiente palabra. "Poco", "algo", "ligeramente" lo reducen.</p>
        <div class="code-block">"muy alegre" → alegría × 1.5
"poco triste" → tristeza × 0.6</div>

        <h3>4️⃣ Consulta al diccionario</h3>
        <p>Primero se buscan bigramas exactos (mayor precisión), luego palabras individuales. Cada coincidencia suma sus pesos a los acumuladores positivo, negativo y neutral.</p>

        <h3>5️⃣ Cálculo de métricas</h3>
        <p>Con los acumuladores se calculan porcentajes, polaridad, subjetividad e intensidad. El resultado se guarda en la base de datos para el histórico.</p>
      </div>
    </div>
  </div>

  <div class="card didact-section">
    <div class="card-title">🧩 Limitaciones y mejoras futuras</div>
    <div class="grid-2">
      <div>
        <h3>Limitaciones actuales</h3>
        <ul>
          <li>No detecta <strong>sarcasmo</strong> ni ironía</li>
          <li>El diccionario es de términos, no de contexto completo</li>
          <li>Algunos modismos o regionalismos pueden no estar recogidos</li>
          <li>No distingue el <strong>sujeto</strong> del sentimiento (aspecto-based)</li>
          <li>Cobertura baja en textos muy técnicos o con jerga</li>
        </ul>
      </div>
      <div>
        <h3>Posibles mejoras</h3>
        <ul>
          <li>Ampliar el diccionario con SlANGP o NRC Emotion Lexicon</li>
          <li>Integrar un modelo de ML ligero (Naive Bayes, SVM)</li>
          <li>Añadir análisis de emociones específicas (alegría, ira, tristeza…)</li>
          <li>Soporte multiidioma (inglés, catalán, gallego…)</li>
          <li>API REST completa con autenticación y cuotas</li>
          <li>Dashboard en tiempo real con WebSockets</li>
        </ul>
      </div>
    </div>
  </div>
</div><!-- /tab-aprende -->

</div><!-- /container -->

<!-- ================================================================ -->
<!-- SCRIPTS JAVASCRIPT + GRÁFICAS                                      -->
<!-- ================================================================ -->
<script>
// ---- Datos del servidor ----
const resultado  = <?= $jsonResultado ?>;
const historial  = <?= $jsonHistorial ?>;
const stats      = <?= $jsonStats ?>;

// ---- Helpers colores ----
const C = {
  pos:  'rgba(34,197,94,.75)',  posB: 'rgba(34,197,94,1)',
  neg:  'rgba(239,68,68,.75)',  negB: 'rgba(239,68,68,1)',
  neu:  'rgba(245,158,11,.75)', neuB: 'rgba(245,158,11,1)',
  acc:  'rgba(99,102,241,.75)', accB: 'rgba(99,102,241,1)',
  grid: 'rgba(255,255,255,.08)', text: '#94a3b8',
};

const darkGrid = {
  plugins: { legend: { labels: { color: C.text } } },
  scales: {
    x: { ticks:{color:C.text}, grid:{color:C.grid} },
    y: { ticks:{color:C.text}, grid:{color:C.grid} },
  }
};

// ---- Spinner al enviar ----
document.getElementById('mainForm')?.addEventListener('submit', () => {
  document.getElementById('spinner').style.display = 'inline-block';
  document.getElementById('btnText').textContent = 'Analizando…';
});

// ---- Contador de caracteres ----
const ta = document.getElementById('texto');
const cc = document.getElementById('charCount');
if (ta && cc) {
  const update = () => cc.textContent = ta.value.length + ' caracteres';
  ta.addEventListener('input', update);
  update();
}

// ---- Gráficas del ANÁLISIS ACTUAL ----
if (resultado) {
  const p = resultado.porcentaje_positivo;
  const n = resultado.porcentaje_negativo;
  const u = resultado.porcentaje_neutral;

  // PIE
  new Chart(document.getElementById('chartPie'), {
    type: 'pie',
    data: {
      labels: ['Positivo','Negativo','Neutral'],
      datasets: [{ data: [p, n, u],
        backgroundColor: [C.pos, C.neg, C.neu],
        borderColor: [C.posB, C.negB, C.neuB], borderWidth: 2 }]
    },
    options: { plugins: {
      legend: { labels:{color:C.text} },
      datalabels: { color:'#fff', font:{weight:'bold'},
        formatter: v => v.toFixed(1)+'%' }
    }}
  });

  // RADAR
  const pol01 = ((parseFloat(resultado.polaridad) + 1) / 2 * 100).toFixed(1);
  const subj01 = (parseFloat(resultado.subjetividad) * 100).toFixed(1);
  const intens01 = (parseFloat(resultado.intensidad) / 10 * 100).toFixed(1);
  const cob = parseFloat(resultado.cobertura_pct);

  new Chart(document.getElementById('chartRadar'), {
    type: 'radar',
    data: {
      labels: ['Positividad','Negatividad','Subjetividad','Polaridad (+)','Intensidad','Cobertura'],
      datasets: [{
        label: 'Análisis actual',
        data: [p, n, subj01, pol01, intens01, cob],
        backgroundColor: 'rgba(99,102,241,.25)',
        borderColor: C.accB, pointBackgroundColor: C.accB, borderWidth: 2
      }]
    },
    options: { plugins: { legend:{labels:{color:C.text}} },
      scales: { r: { ticks:{color:C.text,backdropColor:'transparent'},
                     grid:{color:C.grid}, pointLabels:{color:C.text}, min:0, max:100 }} }
  });

  // BAR comparativo (puntuaciones brutas)
  new Chart(document.getElementById('chartBar'), {
    type: 'bar',
    data: {
      labels: ['Positivo','Negativo','Neutral'],
      datasets: [{
        label: 'Puntuación bruta',
        data: [resultado.puntaje_positivo, resultado.puntaje_negativo, resultado.puntaje_neutral],
        backgroundColor: [C.pos, C.neg, C.neu],
        borderColor: [C.posB, C.negB, C.neuB], borderWidth: 2, borderRadius: 8
      }]
    },
    options: { ...darkGrid, plugins:{ legend:{labels:{color:C.text}},
      datalabels:{color:'#fff',font:{weight:'bold'}, formatter:v=>v.toFixed(2)} } }
  });

  // DOUGHNUT polaridad / subjetividad
  const pol = parseFloat(resultado.polaridad);
  const polPct = Math.round(((pol + 1) / 2) * 100);
  new Chart(document.getElementById('chartPolar'), {
    type: 'doughnut',
    data: {
      labels: ['Polo negativo','Polo positivo'],
      datasets: [{
        data: [100 - polPct, polPct],
        backgroundColor: [C.neg, C.pos],
        borderColor: [C.negB, C.posB], borderWidth: 2
      }]
    },
    options: { plugins: {
      legend:{labels:{color:C.text}},
      datalabels:{ color:'#fff', font:{weight:'bold'}, formatter:v=>v+'%' }
    }, cutout:'60%' }
  });
}

// ---- Gráficas HISTÓRICAS ----
if (historial && historial.length > 0) {
  const labels = historial.map(h => h.fecha_fmt);
  const posArr = historial.map(h => parseFloat(h.porcentaje_positivo));
  const negArr = historial.map(h => parseFloat(h.porcentaje_negativo));
  const neuArr = historial.map(h => parseFloat(h.porcentaje_neutral));
  const polArr = historial.map(h => parseFloat(h.polaridad));

  // LINE evolución
  new Chart(document.getElementById('chartHistLine'), {
    type: 'line',
    data: {
      labels,
      datasets: [
        { label:'Positivo', data:posArr, borderColor:C.posB, backgroundColor:'rgba(34,197,94,.1)',
          tension:.3, fill:true },
        { label:'Negativo', data:negArr, borderColor:C.negB, backgroundColor:'rgba(239,68,68,.1)',
          tension:.3, fill:true },
        { label:'Neutral',  data:neuArr, borderColor:C.neuB, backgroundColor:'rgba(245,158,11,.1)',
          tension:.3, fill:true },
      ]
    },
    options: { ...darkGrid, plugins:{legend:{labels:{color:C.text}},datalabels:{display:false}} }
  });

  // PIE global histórico
  if (stats) {
    new Chart(document.getElementById('chartHistPie'), {
      type: 'pie',
      data: {
        labels: ['Positivos','Negativos','Neutrales'],
        datasets: [{ data: [stats.total_positivos, stats.total_negativos, stats.total_neutrales],
          backgroundColor:[C.pos, C.neg, C.neu], borderColor:[C.posB,C.negB,C.neuB], borderWidth:2 }]
      },
      options: { plugins:{ legend:{labels:{color:C.text}},
        datalabels:{color:'#fff',font:{weight:'bold'},formatter:v=>v+' textos'} } }
    });
  }

  // BAR polaridad histórica
  new Chart(document.getElementById('chartHistPolar'), {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Polaridad',
        data: polArr,
        backgroundColor: polArr.map(v => v >= 0 ? C.pos : C.neg),
        borderColor:      polArr.map(v => v >= 0 ? C.posB : C.negB),
        borderWidth: 2, borderRadius: 6
      }]
    },
    options: { ...darkGrid,
      plugins:{legend:{labels:{color:C.text}},datalabels:{display:false}},
      scales: { ...darkGrid.scales, y:{...darkGrid.scales.y, min:-1, max:1} } }
  });

  // SCATTER positivo vs negativo
  const scatterData = historial.map(h => ({
    x: parseFloat(h.porcentaje_positivo),
    y: parseFloat(h.porcentaje_negativo),
    label: h.fecha_fmt
  }));
  new Chart(document.getElementById('chartHistScatter'), {
    type: 'scatter',
    data: {
      datasets: [{
        label: 'Análisis (positivo vs negativo)',
        data: scatterData,
        backgroundColor: C.acc, pointRadius: 7, pointHoverRadius: 9
      }]
    },
    options: { ...darkGrid,
      plugins:{legend:{labels:{color:C.text}},datalabels:{display:false}},
      scales:{
        x:{...darkGrid.scales.x, title:{display:true,text:'% Positivo',color:C.text}},
        y:{...darkGrid.scales.y, title:{display:true,text:'% Negativo',color:C.text}},
      }
    }
  });
}

// ---- Probador API ----
async function probarAPI() {
  const txt = document.getElementById('apiTestInput').value.trim();
  const out = document.getElementById('apiTestOutput');
  if (!txt) { out.textContent = '⚠️ Escribe un texto de prueba.'; return; }
  out.textContent = '⏳ Consultando API…';
  try {
    const r = await fetch('api.php?texto=' + encodeURIComponent(txt));
    out.textContent = await r.text();
  } catch(e) {
    out.textContent = '❌ Error: ' + e.message;
  }
}
</script>

</body>
</html>
