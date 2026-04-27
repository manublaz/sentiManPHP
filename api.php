<?php
// ============================================================
//  SentiManPHP v2 — API de Análisis de Sentimiento
//  Uso: http://localhost/sentiman/api.php?texto=TU_TEXTO
//  
//  FORMATO DE RESPUESTA (texto plano, sin JSON):
//    sentimiento:positivo
//    positivo:72.50
//    negativo:15.30
//    neutral:12.20
//    polaridad:0.65
//    etiqueta:Positivo
//    palabras:42
//    encontradas:18
//    cobertura:42.86
//
//  En PHP (alumno scraper):
//    $resp = file_get_contents("http://localhost/sentiman/api.php?texto=".urlencode($texto));
//    echo $resp;
//
//  Obtener solo el sentimiento:
//    $lineas = explode("\n", $resp);
//    foreach($lineas as $l){ if(str_starts_with($l,'sentimiento:')) echo $l; }
// ============================================================

header('Content-Type: text/plain; charset=utf-8');
header('Access-Control-Allow-Origin: *');   // Permite llamadas desde localhost

require_once 'config.php';
require_once 'sentiman.php';

// ----- Obtener el texto (GET o POST) -----
$texto = '';
if (!empty($_GET['texto']))  $texto = trim($_GET['texto']);
if (!empty($_POST['texto'])) $texto = trim($_POST['texto']);

if ($texto === '') {
    http_response_code(400);
    echo "error:Debes enviar el parámetro 'texto'\n";
    echo "uso:http://localhost/sentiman/api.php?texto=TU_TEXTO\n";
    exit;
}

// ----- Analizar -----
$con       = getConexion();
$resultado = analizarSentimiento($texto, $con);
guardarHistorico($texto, $resultado, 'api', $con);
$con->close();

// ----- Respuesta en texto plano (fácil de leer con echo) -----
echo "sentimiento:{$resultado['sentimiento_global']}\n";
echo "positivo:{$resultado['porcentaje_positivo']}\n";
echo "negativo:{$resultado['porcentaje_negativo']}\n";
echo "neutral:{$resultado['porcentaje_neutral']}\n";
echo "polaridad:{$resultado['polaridad']}\n";
echo "subjetividad:{$resultado['subjetividad']}\n";
echo "intensidad:{$resultado['intensidad']}\n";
echo "etiqueta:{$resultado['etiqueta']}\n";
echo "palabras:{$resultado['total_palabras']}\n";
echo "encontradas:{$resultado['palabras_encontradas']}\n";
echo "cobertura:{$resultado['cobertura_pct']}\n";
?>
