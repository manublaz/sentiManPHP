<?php
// ============================================================
//  SentiManPHP v3 — API de Análisis de Sentimiento
//
//  USO:
//    api.php?texto=TU_TEXTO                    (capa general, defecto)
//    api.php?texto=...&capa=emociones_basicas
//    api.php?texto=...&capa=emociones_complejas
//    api.php?texto=...&capa=intencion
//    api.php?texto=...&capa=comercial
//    api.php?texto=...&capa=todas              (todo en un solo response)
//
//  Respuesta: texto plano `clave:valor` por línea — fácil con echo.
// ============================================================

header('Content-Type: text/plain; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';
require_once 'sentiman.php';

// ---- Texto de entrada ----
$texto = '';
if (!empty($_GET['texto']))  $texto = $_GET['texto'];
if (!empty($_POST['texto'])) $texto = $_POST['texto'];

// Limpieza defensiva
if (!mb_check_encoding($texto, 'UTF-8')) {
    $texto = mb_convert_encoding($texto, 'UTF-8', 'UTF-8');
}
$texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $texto);
$texto = trim($texto);
if (mb_strlen($texto, 'UTF-8') > 10000) {
    $texto = mb_substr($texto, 0, 10000, 'UTF-8');
}

// ---- Capa solicitada ----
$capa = strtolower(trim($_GET['capa'] ?? $_POST['capa'] ?? 'general'));
$capasValidas = ['general','emociones_basicas','emociones_complejas','intencion','comercial','todas'];
if (!in_array($capa, $capasValidas, true)) $capa = 'general';

if ($texto === '') {
    http_response_code(400);
    echo "error:Debes enviar el parámetro 'texto'\n";
    echo "uso:http://localhost/sentiman/api.php?texto=TU_TEXTO\n";
    echo "capas_validas:" . implode(',', $capasValidas) . "\n";
    exit;
}

// ---- Análisis ----
$con = getConexion();
$r   = analizarTexto($texto, $con);
guardarHistorico($texto, $r, 'api', $con);
$con->close();

// ============================================================
//  Salida
// ============================================================

function imprimirGeneral(array $r): void {
    echo "sentimiento:{$r['sentimiento_global']}\n";
    echo "positivo:{$r['porcentaje_positivo']}\n";
    echo "negativo:{$r['porcentaje_negativo']}\n";
    echo "neutral:{$r['porcentaje_neutral']}\n";
    echo "polaridad:{$r['polaridad']}\n";
    echo "subjetividad:{$r['subjetividad']}\n";
    echo "intensidad:{$r['intensidad']}\n";
    echo "etiqueta:{$r['etiqueta']}\n";
    echo "palabras:{$r['total_palabras']}\n";
    echo "encontradas:{$r['palabras_encontradas']}\n";
    echo "cobertura:{$r['cobertura_pct']}\n";
}

function imprimirCapa(array $r, string $capa): void {
    $puntajes = $r['capas'][$capa]['puntajes'] ?? [];
    echo "capa:$capa\n";
    echo "categorias:" . count($puntajes) . "\n";
    if (empty($puntajes)) {
        echo "mensaje:no se detectaron categorias en este texto\n";
        return;
    }
    foreach ($puntajes as $cat => $val) {
        echo "$cat:$val\n";
    }
    echo "dominante:" . array_key_first($puntajes) . "\n";
}

switch ($capa) {
    case 'general':
        imprimirGeneral($r);
        break;

    case 'emociones_basicas':
    case 'emociones_complejas':
    case 'intencion':
    case 'comercial':
        imprimirCapa($r, $capa);
        break;

    case 'todas':
        echo "capa:general\n";
        imprimirGeneral($r);
        foreach (['emociones_basicas','emociones_complejas','intencion','comercial'] as $c) {
            echo "\n";
            imprimirCapa($r, $c);
        }
        echo "\ncapa:meta\n";
        echo "emocion_basica_dominante:"   . ($r['_meta']['emocion_dominante_basica']   ?? 'none') . "\n";
        echo "emocion_compleja_dominante:" . ($r['_meta']['emocion_dominante_compleja'] ?? 'none') . "\n";
        echo "intencion_principal:"        . ($r['_meta']['intencion_principal']       ?? 'none') . "\n";
        echo "señal_comercial:"            . ($r['_meta']['señal_comercial']           ?? 'none') . "\n";
        echo "etapa_funnel:"               . ($r['_meta']['etapa_funnel']              ?? 'desconocida') . "\n";
        echo "activacion:"                 . ($r['_meta']['activacion']                ?? 0) . "\n";
        echo "sarcasmo:"                   . (($r['_meta']['sarcasmo_detectado'] ?? false) ? 'si' : 'no') . "\n";
        break;
}
?>
