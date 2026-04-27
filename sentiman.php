<?php
// ============================================================
//  SentiManPHP v2 — Motor de análisis de sentimiento
//  Núcleo del sistema: tokenización, búsqueda, métricas
// ============================================================

/**
 * Tokeniza el texto: limpia, normaliza y divide en palabras.
 * También genera bigramas (pares de palabras) para capturar
 * expresiones compuestas como "muy bueno" o "no malo".
 */
function tokenizarTexto(string $texto): array {
    // Normalizar a minúsculas
    $texto = mb_strtolower($texto, 'UTF-8');
    // Eliminar signos de puntuación pero conservar tildes y ñ
    $texto = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $texto);
    // Reducir espacios múltiples
    $texto = preg_replace('/\s+/', ' ', trim($texto));

    $tokens = preg_split('/\s+/', $texto, -1, PREG_SPLIT_NO_EMPTY);

    // Generar bigramas (frases de 2 palabras consecutivas)
    $bigramas = [];
    for ($i = 0; $i < count($tokens) - 1; $i++) {
        $bigramas[] = $tokens[$i] . ' ' . $tokens[$i + 1];
    }

    return ['tokens' => $tokens, 'bigramas' => $bigramas];
}

/**
 * Detecta negaciones cercanas a una palabra (ventana ±2 tokens).
 * Ej: "no es bueno" debe invertir el peso de "bueno".
 */
function detectarNegacion(array $tokens, int $indice): bool {
    $negaciones = ['no', 'ni', 'nunca', 'jamás', 'jamás', 'nada', 'nadie',
                   'ningún', 'ninguna', 'ninguno', 'sin', 'tampoco'];
    $ventana = max(0, $indice - 2);
    for ($i = $ventana; $i < $indice; $i++) {
        if (in_array($tokens[$i], $negaciones)) {
            return true;
        }
    }
    return false;
}

/**
 * Detecta intensificadores cercanos (ventana -1 token).
 * Ej: "muy bueno", "súper feliz", "extremadamente triste"
 */
function detectarIntensificador(array $tokens, int $indice): float {
    $intensificadores = [
        'muy' => 1.5, 'súper' => 1.6, 'super' => 1.6, 'extremadamente' => 1.8,
        'increíblemente' => 1.7, 'bastante' => 1.3, 'demasiado' => 1.4,
        'totalmente' => 1.5, 'absolutamente' => 1.6, 'profundamente' => 1.5,
        'genuinamente' => 1.4, 'realmente' => 1.3, 'verdaderamente' => 1.4,
        'enormemente' => 1.6, 'terriblemente' => 1.6, 'sumamente' => 1.5,
        'poco' => 0.6, 'algo' => 0.7, 'ligeramente' => 0.6, 'apenas' => 0.5,
    ];
    if ($indice > 0 && isset($intensificadores[$tokens[$indice - 1]])) {
        return $intensificadores[$tokens[$indice - 1]];
    }
    return 1.0;
}

/**
 * Función principal de análisis de sentimiento.
 * Devuelve un array rico con todas las métricas calculadas.
 */
function analizarSentimiento(string $texto, $con): array {

    $tokenizado     = tokenizarTexto($texto);
    $tokens         = $tokenizado['tokens'];
    $bigramas       = $tokenizado['bigramas'];
    $totalTokens    = count($tokens);

    if ($totalTokens === 0) {
        return _resultadoVacio();
    }

    $sumaPos  = 0.0;
    $sumaNeg  = 0.0;
    $sumaNeu  = 0.0;
    $encontradas  = 0;
    $detalle      = [];   // Para mostrar qué palabras se reconocieron

    // ---- Buscar BIGRAMAS primero (mayor prioridad) ----
    $bigramasEncontrados = [];
    foreach ($bigramas as $bigrama) {
        $bigrama_esc = $con->real_escape_string($bigrama);
        $sql = "SELECT palabra, positiva, negativa, neutral
                FROM dictionary
                WHERE LOWER(palabra) = LOWER('$bigrama_esc')
                LIMIT 1";
        $res = $con->query($sql);
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $bigramasEncontrados[] = $bigrama; // No re-procesar sus tokens
            $sumaPos += (float)$row['positiva'];
            $sumaNeg += (float)$row['negativa'];
            $sumaNeu += (float)$row['neutral'];
            $encontradas++;
            $detalle[] = [
                'palabra'  => $row['palabra'],
                'tipo'     => 'bigrama',
                'pos'      => (float)$row['positiva'],
                'neg'      => (float)$row['negativa'],
                'neu'      => (float)$row['neutral'],
                'negado'   => false,
                'factor'   => 1.0,
            ];
        }
    }

    // ---- Buscar tokens individuales ----
    for ($i = 0; $i < $totalTokens; $i++) {
        $token = $tokens[$i];
        if (mb_strlen($token, 'UTF-8') < 2) continue;

        // Saltar tokens que ya formaron parte de un bigrama encontrado
        $esBigramaParte = false;
        foreach ($bigramasEncontrados as $bg) {
            $partes = explode(' ', $bg);
            if ($token === $partes[0] || $token === $partes[1]) {
                $esBigramaParte = true;
                break;
            }
        }
        if ($esBigramaParte) continue;

        $token_esc = $con->real_escape_string($token);
        $sql = "SELECT palabra, positiva, negativa, neutral
                FROM dictionary
                WHERE LOWER(palabra) = LOWER('$token_esc')
                LIMIT 1";
        $res = $con->query($sql);

        if ($res && $res->num_rows > 0) {
            $row    = $res->fetch_assoc();
            $pos    = (float)$row['positiva'];
            $neg    = (float)$row['negativa'];
            $neu    = (float)$row['neutral'];
            $negado = detectarNegacion($tokens, $i);
            $factor = detectarIntensificador($tokens, $i);

            if ($negado) {
                // Inversión por negación: positivo ↔ negativo, reducido
                [$pos, $neg] = [$neg * 0.8, $pos * 0.8];
            }

            $sumaPos += $pos * $factor;
            $sumaNeg += $neg * $factor;
            $sumaNeu += $neu * $factor;
            $encontradas++;

            $detalle[] = [
                'palabra' => $row['palabra'],
                'tipo'    => 'unigrama',
                'pos'     => $pos,
                'neg'     => $neg,
                'neu'     => $neu,
                'negado'  => $negado,
                'factor'  => $factor,
            ];
        }
    }

    // ---- Cálculo de porcentajes ----
    $total = $sumaPos + $sumaNeg + $sumaNeu;
    if ($total == 0) $total = 1; // evitar división por cero

    $pctPos = ($sumaPos / $total) * 100;
    $pctNeg = ($sumaNeg / $total) * 100;
    $pctNeu = ($sumaNeu / $total) * 100;

    // ---- Métricas avanzadas ----
    // Polaridad: -1 (muy negativo) → +1 (muy positivo)
    $polaridad = ($sumaPos - $sumaNeg) / max($sumaPos + $sumaNeg, 0.001);

    // Subjetividad: 0 (objetivo/neutral) → 1 (muy subjetivo)
    $subjetividad = ($sumaPos + $sumaNeg) / max($total, 0.001);

    // Intensidad media: promedio del peso más alto de cada palabra encontrada
    $intensidad = 0;
    if ($encontradas > 0) {
        foreach ($detalle as $d) {
            $intensidad += max($d['pos'], $d['neg'], $d['neu']);
        }
        $intensidad = $intensidad / $encontradas;
    }

    // Cobertura: % de tokens que se encontraron en el diccionario
    $cobertura = $totalTokens > 0 ? ($encontradas / $totalTokens) * 100 : 0;

    // Sentimiento global dominante
    if ($pctPos >= $pctNeg && $pctPos >= $pctNeu) {
        $global = 'positivo';
    } elseif ($pctNeg >= $pctPos && $pctNeg >= $pctNeu) {
        $global = 'negativo';
    } else {
        $global = 'neutral';
    }

    // Etiqueta descriptiva basada en polaridad e intensidad
    $etiqueta = _etiquetaDescriptiva($polaridad, $intensidad, $subjetividad);

    return [
        // Puntuaciones brutas
        'puntaje_positivo'    => round($sumaPos, 4),
        'puntaje_negativo'    => round($sumaNeg, 4),
        'puntaje_neutral'     => round($sumaNeu, 4),
        // Porcentajes
        'porcentaje_positivo' => round($pctPos, 2),
        'porcentaje_negativo' => round($pctNeg, 2),
        'porcentaje_neutral'  => round($pctNeu, 2),
        // Métricas avanzadas
        'polaridad'           => round($polaridad, 4),
        'subjetividad'        => round($subjetividad, 4),
        'intensidad'          => round($intensidad, 4),
        'sentimiento_global'  => $global,
        'etiqueta'            => $etiqueta,
        // Estadísticas de texto
        'total_palabras'      => $totalTokens,
        'palabras_encontradas'=> $encontradas,
        'cobertura_pct'       => round($cobertura, 2),
        // Detalle por palabra (para tabla didáctica)
        'detalle'             => $detalle,
    ];
}

/**
 * Guarda el resultado en la tabla de histórico.
 */
function guardarHistorico(string $texto, array $resultado, string $fuente, $con): int {
    $texto_esc = $con->real_escape_string(mb_substr($texto, 0, 2000));
    $f = $fuente === 'api' ? 'api' : 'web';

    $sql = "INSERT INTO analisis_historico
              (fuente, texto_original, total_palabras, palabras_encontradas,
               cobertura_pct, puntaje_positivo, puntaje_negativo, puntaje_neutral,
               porcentaje_positivo, porcentaje_negativo, porcentaje_neutral,
               sentimiento_global, polaridad, subjetividad, intensidad, etiqueta)
            VALUES
              ('$f',
               '$texto_esc',
               {$resultado['total_palabras']},
               {$resultado['palabras_encontradas']},
               {$resultado['cobertura_pct']},
               {$resultado['puntaje_positivo']},
               {$resultado['puntaje_negativo']},
               {$resultado['puntaje_neutral']},
               {$resultado['porcentaje_positivo']},
               {$resultado['porcentaje_negativo']},
               {$resultado['porcentaje_neutral']},
               '{$resultado['sentimiento_global']}',
               {$resultado['polaridad']},
               {$resultado['subjetividad']},
               {$resultado['intensidad']},
               '{$con->real_escape_string($resultado['etiqueta'])}')";

    $con->query($sql);
    return (int)$con->insert_id;
}

/** Resultado vacío cuando no hay texto. */
function _resultadoVacio(): array {
    return [
        'puntaje_positivo' => 0, 'puntaje_negativo' => 0, 'puntaje_neutral' => 0,
        'porcentaje_positivo' => 0, 'porcentaje_negativo' => 0, 'porcentaje_neutral' => 0,
        'polaridad' => 0, 'subjetividad' => 0, 'intensidad' => 0,
        'sentimiento_global' => 'neutral', 'etiqueta' => 'Sin contenido',
        'total_palabras' => 0, 'palabras_encontradas' => 0, 'cobertura_pct' => 0,
        'detalle' => [],
    ];
}

/** Genera una etiqueta descriptiva combinando polaridad, intensidad y subjetividad. */
function _etiquetaDescriptiva(float $pol, float $intensidad, float $subj): string {
    if ($subj < 0.1) return 'Texto objetivo/neutral';
    if ($pol > 0.6 && $intensidad > 6) return 'Muy positivo y expresivo';
    if ($pol > 0.4)  return 'Positivo';
    if ($pol > 0.15) return 'Ligeramente positivo';
    if ($pol < -0.6 && $intensidad > 6) return 'Muy negativo e intenso';
    if ($pol < -0.4) return 'Negativo';
    if ($pol < -0.15) return 'Ligeramente negativo';
    if ($subj > 0.5) return 'Ambivalente / mixto';
    return 'Neutral';
}
?>
