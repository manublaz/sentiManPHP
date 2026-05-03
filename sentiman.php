<?php
// ============================================================
//  SentiManPHP v3 — Motor unificado de análisis multicapa
//  Con stemming para español, factores de ajuste configurables
//  y corrección de sesgo positivo.
// ============================================================

// ============================================================
//  FACTORES DE AJUSTE — EDITABLES POR EL USUARIO
//  Se guardan en config_ajustes.json en la misma carpeta.
//  El panel de ajustes (index.php) los modifica en tiempo real.
// ============================================================
function obtenerAjustes(): array {
    $defaults = [
        // Factor multiplicador para cada componente general (1.0 = sin cambio)
        'factor_positivo'   => 1.0,
        'factor_negativo'   => 1.0,
        'factor_neutral'    => 1.0,

        // Umbral mínimo de peso para contar una palabra (filtra ruido)
        'umbral_peso'       => 0.0,

        // Factor de stemming: cuando se usa la raíz en lugar de la palabra exacta
        // (0.0 = desactivado, 1.0 = mismo peso que la palabra original)
        'factor_stemming'   => 0.7,

        // Activar/desactivar stemming
        'stemming_activo'   => true,

        // Número mínimo de caracteres de la raíz para aceptarla
        'stem_min_chars'    => 4,

        // Factor de suavizado para evitar que pocas palabras dominen.
        // Se aplica como: peso_final = peso ^ suavizado (0.5 = raíz cuadrada, 1.0 = sin cambio)
        'suavizado'         => 1.0,

        // Bonus negativo: se suma a la puntuación negativa total para contrarrestar sesgo positivo
        // del diccionario. Valor en puntos absolutos (0 = desactivado, 5 = moderado, 10 = fuerte)
        'correccion_sesgo'  => 0.0,
    ];

    $archivo = __DIR__ . '/config_ajustes.json';
    if (file_exists($archivo)) {
        $cargados = @json_decode(file_get_contents($archivo), true);
        if (is_array($cargados)) {
            return array_merge($defaults, $cargados);
        }
    }
    return $defaults;
}

function guardarAjustes(array $ajustes): bool {
    $archivo = __DIR__ . '/config_ajustes.json';
    return file_put_contents($archivo, json_encode($ajustes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}

// ============================================================
//  Catálogo de capas
// ============================================================
function obtenerCatalogoCapas(): array {
    return [
        'general' => [
            'nombre' => 'Sentimiento general',
            'icono'  => '⚖️',
            'descripcion' => 'Polaridad clásica positivo/negativo/neutral.',
            'categorias' => ['positiva', 'negativa', 'neutral'],
        ],
        'emociones_basicas' => [
            'nombre' => 'Emociones básicas (Plutchik)',
            'icono'  => '🎭',
            'descripcion' => 'Las 8 emociones primarias según Robert Plutchik.',
            'categorias' => ['alegria','tristeza','ira','miedo','sorpresa','asco','confianza','anticipacion'],
        ],
        'emociones_complejas' => [
            'nombre' => 'Emociones complejas y sociales',
            'icono'  => '🧠',
            'descripcion' => 'Emociones que implican relación con uno mismo o con otros.',
            'categorias' => ['gratitud','orgullo','admiracion','compasion','esperanza','aceptacion',
                             'verguenza','culpa','envidia','placer_ajeno','apatia','ambivalencia',
                             'soledad','humildad'],
        ],
        'intencion' => [
            'nombre' => 'Intención, sarcasmo e intensidad',
            'icono'  => '🎯',
            'descripcion' => 'Para qué fue escrito el texto: queja, elogio, amenaza, petición, sarcasmo, urgencia.',
            'categorias' => ['queja','elogio','amenaza','peticion','sarcasmo','urgencia',
                             'intensidad_alta','intensidad_baja'],
        ],
        'comercial' => [
            'nombre' => 'Análisis comercial',
            'icono'  => '🛍️',
            'descripcion' => 'Señales útiles para marketing y ventas.',
            'categorias' => ['intencion_compra','riesgo_abandono','fidelizacion','satisfaccion_alta',
                             'insatisfaccion','objecion_precio','objecion_valor','objecion_tiempo',
                             'objecion_necesidad','objecion_confianza','comparacion','escasez',
                             'calidad_alta','calidad_baja','servicio_bueno','servicio_malo'],
        ],
    ];
}

function todasLasColumnas(): array {
    $cols = [];
    foreach (obtenerCatalogoCapas() as $capa) {
        foreach ($capa['categorias'] as $c) $cols[] = $c;
    }
    return $cols;
}

// ============================================================
//  Stemmer para español (Snowball simplificado)
// ============================================================
function stemES(string $palabra): string {
    $p = mb_strtolower($palabra, 'UTF-8');
    $len = mb_strlen($p, 'UTF-8');
    if ($len < 4) return $p;

    // Paso 1: sufijos verbales, adverbiales y nominales (más largo primero)
    $sufijos = [
        // Verbales
        'aciones','imientos','amiento','imiento','ización','aciones','amente',
        'idades','idades','adores','adoras','ientes','encias','ancias','antes',
        'iendo','ación','ación','mente','istas','ables','ibles','iones','adora',
        'ador','ante','ente','anza','ando','aron','aban','aron','aran','ería',
        'ería','ible','able','ista','ismo','idad','oso','osa','ivo','iva',
        'ado','ido','ada','ida','ura','ión','cia','nte','mos','ais',
        'ís','ar','er','ir','as','es','os','an','en',
    ];

    foreach ($sufijos as $suf) {
        $sufLen = mb_strlen($suf, 'UTF-8');
        if ($len > $sufLen + 2 && mb_substr($p, -$sufLen) === $suf) {
            return mb_substr($p, 0, $len - $sufLen);
        }
    }

    return $p;
}

// ============================================================
//  Tokenización y helpers
// ============================================================
function tokenizarTexto(string $texto): array {
    $texto = mb_strtolower($texto, 'UTF-8');
    $texto = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $texto);
    $texto = preg_replace('/\s+/', ' ', trim($texto));
    $tokens = preg_split('/\s+/', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: [];

    $bigramas = $trigramas = $tetragramas = [];
    $n = count($tokens);
    for ($i = 0; $i < $n - 1; $i++) $bigramas[]    = $tokens[$i] . ' ' . $tokens[$i+1];
    for ($i = 0; $i < $n - 2; $i++) $trigramas[]   = $tokens[$i] . ' ' . $tokens[$i+1] . ' ' . $tokens[$i+2];
    for ($i = 0; $i < $n - 3; $i++) $tetragramas[] = $tokens[$i] . ' ' . $tokens[$i+1] . ' ' . $tokens[$i+2] . ' ' . $tokens[$i+3];

    return ['tokens' => $tokens, 'bigramas' => $bigramas, 'trigramas' => $trigramas, 'tetragramas' => $tetragramas];
}

function detectarNegacion(array $tokens, int $indice): bool {
    $negaciones = ['no','ni','nunca','jamás','jamas','nada','nadie',
                   'ningún','ninguna','ninguno','sin','tampoco'];
    for ($i = max(0, $indice - 2); $i < $indice; $i++) {
        if (isset($tokens[$i]) && in_array($tokens[$i], $negaciones, true)) return true;
    }
    return false;
}

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

// ============================================================
//  Función principal de análisis
// ============================================================
function analizarTexto(string $texto, $con): array {

    $catalogo   = obtenerCatalogoCapas();
    $todasCols  = todasLasColumnas();
    $ajustes    = obtenerAjustes();

    $tok = tokenizarTexto($texto);
    $tokens      = $tok['tokens'];
    $bigramas    = $tok['bigramas'];
    $trigramas   = $tok['trigramas'];
    $tetragramas = $tok['tetragramas'];
    $totalTokens = count($tokens);

    if ($totalTokens === 0) return _resultadoVacio($catalogo);

    // Acumuladores
    $sumas = array_fill_keys($todasCols, 0.0);
    $detalle = [];
    $tokensConsumidos = [];

    // ============================================================
    //  1) Buscar candidatos directos (palabras + n-gramas)
    // ============================================================
    $candidatos = array_unique(array_merge($tetragramas, $trigramas, $bigramas, $tokens));
    $candidatos = array_filter($candidatos, fn($c) => mb_strlen($c, 'UTF-8') >= 2);

    if (empty($candidatos)) return _resultadoVacio($catalogo);

    $candidatos_sql = array_map(fn($c) => "'" . $con->real_escape_string($c) . "'", $candidatos);
    $cols_sql = '`palabra`, `' . implode('`, `', $todasCols) . '`';
    $sql = "SELECT $cols_sql FROM dictionary
            WHERE LOWER(palabra) IN (" . implode(',', $candidatos_sql) . ")";
    $res = @$con->query($sql);

    $lex = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $lex[mb_strtolower($row['palabra'], 'UTF-8')] = $row;
        }
    }

    // ============================================================
    //  2) Stemming: para tokens no encontrados, buscar por raíz
    // ============================================================
    $stemMap = []; // mapa token → fila del diccionario (vía stem)
    if (!empty($ajustes['stemming_activo'])) {
        $noEncontrados = [];
        foreach ($tokens as $tok_item) {
            if (mb_strlen($tok_item, 'UTF-8') < 2) continue;
            if (isset($lex[$tok_item])) continue;
            $stem = stemES($tok_item);
            if (mb_strlen($stem, 'UTF-8') >= ($ajustes['stem_min_chars'] ?? 4)) {
                $noEncontrados[$tok_item] = $stem;
            }
        }

        if (!empty($noEncontrados)) {
            $stemsUnicos = array_unique(array_values($noEncontrados));
            // Buscar palabras del diccionario cuya raíz coincida
            // Estrategia: LIKE 'stem%' (rápido si hay índice)
            $condiciones = [];
            foreach ($stemsUnicos as $s) {
                $condiciones[] = "LOWER(palabra) LIKE '" . $con->real_escape_string($s) . "%'";
            }
            $sqlStem = "SELECT $cols_sql FROM dictionary
                        WHERE (" . implode(' OR ', $condiciones) . ")";
            $resStem = @$con->query($sqlStem);
            $stemLex = [];
            if ($resStem) {
                while ($row = $resStem->fetch_assoc()) {
                    $pw = mb_strtolower($row['palabra'], 'UTF-8');
                    // Solo quedarnos con entradas de una sola palabra (no n-gramas del dic)
                    if (strpos($pw, ' ') === false) {
                        $stemPw = stemES($pw);
                        if (!isset($stemLex[$stemPw])) {
                            $stemLex[$stemPw] = $row;
                        }
                    }
                }
            }

            // Mapear tokens originales a sus matches por stem
            foreach ($noEncontrados as $tok_item => $stem) {
                if (isset($stemLex[$stem])) {
                    $stemMap[$tok_item] = $stemLex[$stem];
                }
            }
        }
    }

    // ============================================================
    //  3) Acumular puntajes: n-gramas primero, luego tokens
    // ============================================================

    // 3a) N-gramas
    foreach ([$tetragramas, $trigramas, $bigramas] as $nivel) {
        foreach ($nivel as $idx => $ng) {
            if (!isset($lex[$ng])) continue;
            $partes = explode(' ', $ng);
            $yaUsado = false;
            foreach ($partes as $p) {
                if (isset($tokensConsumidos[$p])) { $yaUsado = true; break; }
            }
            if ($yaUsado) continue;

            $row = $lex[$ng];
            $pesosNg = [];
            foreach ($todasCols as $col) {
                $val = (float)($row[$col] ?? 0);
                $sumas[$col] += $val;
                $pesosNg[$col] = round($val, 2);
            }
            $detalle[] = [
                'palabra' => $ng, 'tipo' => count($partes) . '-grama',
                'pesos' => $pesosNg, 'negado' => false, 'factor' => 1.0,
            ];
            foreach ($partes as $p) $tokensConsumidos[$p] = true;
        }
    }

    // 3b) Tokens individuales (directos + stemming)
    $factorStem = (float)($ajustes['factor_stemming'] ?? 0.7);

    foreach ($tokens as $i => $tok_item) {
        if (mb_strlen($tok_item, 'UTF-8') < 2) continue;
        if (isset($tokensConsumidos[$tok_item])) continue;

        $row = null;
        $esStem = false;

        if (isset($lex[$tok_item])) {
            $row = $lex[$tok_item];
        } elseif (isset($stemMap[$tok_item])) {
            $row = $stemMap[$tok_item];
            $esStem = true;
        }

        if (!$row) continue;

        $negado = detectarNegacion($tokens, $i);
        $factor = detectarIntensificador($tokens, $i);
        if ($esStem) $factor *= $factorStem;

        $pesosFila = [];
        foreach ($todasCols as $col) {
            $val = (float)($row[$col] ?? 0);

            // Negación en general: positiva ↔ negativa
            if ($negado && $col === 'positiva') {
                $val = (float)($row['negativa'] ?? 0) * 0.8;
            } elseif ($negado && $col === 'negativa') {
                $val = (float)($row['positiva'] ?? 0) * 0.8;
            }

            $val *= $factor;

            // Suavizado: peso_final = peso ^ suavizado
            $suavizado = (float)($ajustes['suavizado'] ?? 1.0);
            if ($suavizado != 1.0 && $val > 0) {
                $val = pow($val, $suavizado);
            }

            $sumas[$col] += $val;
            $pesosFila[$col] = round($val, 2);
        }

        $origenTxt = $esStem ? 'stem → ' . ($row['palabra'] ?? '?') : 'palabra';
        $detalle[] = [
            'palabra' => $tok_item, 'tipo' => $origenTxt,
            'pesos' => $pesosFila, 'negado' => $negado, 'factor' => round($factor, 2),
        ];
        $tokensConsumidos[$tok_item] = true;
    }

    // ============================================================
    //  4) Cálculos de capa 1 (general) con factores de ajuste
    // ============================================================
    $sumaPos = $sumas['positiva'] * (float)$ajustes['factor_positivo'];
    $sumaNeg = $sumas['negativa'] * (float)$ajustes['factor_negativo'];
    $sumaNeu = $sumas['neutral']  * (float)$ajustes['factor_neutral'];

    // Corrección de sesgo: suma un bonus a la puntuación negativa
    $correccion = (float)($ajustes['correccion_sesgo'] ?? 0);
    if ($correccion > 0 && count($detalle) > 0) {
        $sumaNeg += $correccion * count($detalle);
    }

    $totalGen = $sumaPos + $sumaNeg + $sumaNeu;
    if ($totalGen == 0) $totalGen = 1;

    $pctPos = ($sumaPos / $totalGen) * 100;
    $pctNeg = ($sumaNeg / $totalGen) * 100;
    $pctNeu = ($sumaNeu / $totalGen) * 100;

    $polaridad = ($sumaPos - $sumaNeg) / max($sumaPos + $sumaNeg, 0.001);
    $subjetividad = ($sumaPos + $sumaNeg) / max($totalGen, 0.001);

    $intensidad = 0;
    $encontradas = count($detalle);
    if ($encontradas > 0) {
        foreach ($detalle as $d) {
            $intensidad += max($d['pesos']['positiva'] ?? 0,
                               $d['pesos']['negativa'] ?? 0,
                               $d['pesos']['neutral']  ?? 0);
        }
        $intensidad /= $encontradas;
    }

    $cobertura = $totalTokens > 0 ? ($encontradas / $totalTokens) * 100 : 0;

    if ($pctPos >= $pctNeg && $pctPos >= $pctNeu)      $global = 'positivo';
    elseif ($pctNeg >= $pctPos && $pctNeg >= $pctNeu)  $global = 'negativo';
    else                                                $global = 'neutral';

    $etiqueta = _etiquetaDescriptiva($polaridad, $intensidad, $subjetividad);

    // ============================================================
    //  5) Estructurar capas
    // ============================================================
    $capas = [];
    foreach ($catalogo as $nombreCapa => $info) {
        if ($nombreCapa === 'general') continue;
        $puntajes = [];
        foreach ($info['categorias'] as $cat) {
            $v = round($sumas[$cat], 2);
            if ($v > 0) $puntajes[$cat] = $v;
        }
        arsort($puntajes);
        $capas[$nombreCapa] = ['puntajes' => $puntajes, 'total' => count($puntajes)];
    }

    $meta = [
        'emocion_dominante_basica'   => _categoriaTop($capas['emociones_basicas']['puntajes']   ?? []),
        'emocion_dominante_compleja' => _categoriaTop($capas['emociones_complejas']['puntajes'] ?? []),
        'intencion_principal'        => _categoriaTop($capas['intencion']['puntajes']           ?? []),
        'señal_comercial'            => _categoriaTop($capas['comercial']['puntajes']           ?? []),
        'activacion'                 => _calcularActivacion($capas),
        'etapa_funnel'               => _calcularEtapaFunnel($capas['comercial']['puntajes']    ?? []),
        'sarcasmo_detectado'         => (($capas['intencion']['puntajes']['sarcasmo'] ?? 0) > 4),
    ];

    return [
        'puntaje_positivo'    => round($sumaPos, 4),
        'puntaje_negativo'    => round($sumaNeg, 4),
        'puntaje_neutral'     => round($sumaNeu, 4),
        'porcentaje_positivo' => round($pctPos, 2),
        'porcentaje_negativo' => round($pctNeg, 2),
        'porcentaje_neutral'  => round($pctNeu, 2),
        'polaridad'           => round($polaridad, 4),
        'subjetividad'        => round($subjetividad, 4),
        'intensidad'          => round($intensidad, 4),
        'sentimiento_global'  => $global,
        'etiqueta'            => $etiqueta,
        'total_palabras'      => $totalTokens,
        'palabras_encontradas'=> $encontradas,
        'cobertura_pct'       => round($cobertura, 2),
        'detalle'             => $detalle,
        'capas'               => $capas,
        '_meta'               => $meta,
        '_ajustes'            => $ajustes,
    ];
}

// ============================================================
//  Compatibilidad
// ============================================================
function analizarSentimiento(string $texto, $con): array { return analizarTexto($texto, $con); }
function analizarTodasCapas(string $texto, $con): array {
    $r = analizarTexto($texto, $con);
    $out = $r['capas'] ?? [];
    $out['_meta'] = $r['_meta'] ?? [];
    return $out;
}

// ============================================================
//  Guardar histórico
// ============================================================
function guardarHistorico(string $texto, array $r, string $fuente, $con): int {
    $texto_esc    = $con->real_escape_string(mb_substr($texto, 0, 2000));
    $fuente_esc   = $con->real_escape_string($fuente === 'api' ? 'api' : 'web');
    $etiqueta_esc = $con->real_escape_string($r['etiqueta'] ?? '');

    $eb  = $con->real_escape_string(json_encode($r['capas']['emociones_basicas']['puntajes']   ?? [], JSON_UNESCAPED_UNICODE));
    $ec  = $con->real_escape_string(json_encode($r['capas']['emociones_complejas']['puntajes'] ?? [], JSON_UNESCAPED_UNICODE));
    $int = $con->real_escape_string(json_encode($r['capas']['intencion']['puntajes']           ?? [], JSON_UNESCAPED_UNICODE));
    $com = $con->real_escape_string(json_encode($r['capas']['comercial']['puntajes']           ?? [], JSON_UNESCAPED_UNICODE));
    $act = (float)($r['_meta']['activacion'] ?? 0);

    $sql = "INSERT INTO analisis_historico
              (fuente, texto_original, total_palabras, palabras_encontradas,
               cobertura_pct, puntaje_positivo, puntaje_negativo, puntaje_neutral,
               porcentaje_positivo, porcentaje_negativo, porcentaje_neutral,
               sentimiento_global, polaridad, subjetividad, intensidad, etiqueta,
               emociones_basicas, emociones_complejas, intencion_json, comercial, activacion)
            VALUES
              ('$fuente_esc', '$texto_esc',
               {$r['total_palabras']}, {$r['palabras_encontradas']}, {$r['cobertura_pct']},
               {$r['puntaje_positivo']}, {$r['puntaje_negativo']}, {$r['puntaje_neutral']},
               {$r['porcentaje_positivo']}, {$r['porcentaje_negativo']}, {$r['porcentaje_neutral']},
               '{$r['sentimiento_global']}', {$r['polaridad']}, {$r['subjetividad']},
               {$r['intensidad']}, '$etiqueta_esc',
               '$eb', '$ec', '$int', '$com', $act)";
    @$con->query($sql);
    return (int)$con->insert_id;
}

function guardarHistoricoCapas(int $idHistorico, array $resultado, $con): void { }

// ============================================================
//  Helpers internos
// ============================================================
function _resultadoVacio(array $catalogo): array {
    $capas = [];
    foreach ($catalogo as $n => $i) {
        if ($n === 'general') continue;
        $capas[$n] = ['puntajes' => [], 'total' => 0];
    }
    return [
        'puntaje_positivo' => 0, 'puntaje_negativo' => 0, 'puntaje_neutral' => 0,
        'porcentaje_positivo' => 0, 'porcentaje_negativo' => 0, 'porcentaje_neutral' => 0,
        'polaridad' => 0, 'subjetividad' => 0, 'intensidad' => 0,
        'sentimiento_global' => 'neutral', 'etiqueta' => 'Sin contenido',
        'total_palabras' => 0, 'palabras_encontradas' => 0, 'cobertura_pct' => 0,
        'detalle' => [], 'capas' => $capas,
        '_meta' => ['emocion_dominante_basica' => null, 'emocion_dominante_compleja' => null,
                    'intencion_principal' => null, 'señal_comercial' => null,
                    'activacion' => 0.5, 'etapa_funnel' => 'desconocida',
                    'sarcasmo_detectado' => false],
        '_ajustes' => obtenerAjustes(),
    ];
}

function _etiquetaDescriptiva(float $pol, float $intensidad, float $subj): string {
    if ($subj < 0.1) return 'Texto objetivo / neutral';
    if ($pol > 0.6 && $intensidad > 6) return 'Muy positivo y expresivo';
    if ($pol > 0.4)   return 'Positivo';
    if ($pol > 0.15)  return 'Ligeramente positivo';
    if ($pol < -0.6 && $intensidad > 6) return 'Muy negativo e intenso';
    if ($pol < -0.4)  return 'Negativo';
    if ($pol < -0.15) return 'Ligeramente negativo';
    if ($subj > 0.5)  return 'Ambivalente / mixto';
    return 'Neutral';
}

function _categoriaTop(array $puntajes): ?string {
    if (empty($puntajes)) return null;
    arsort($puntajes);
    return array_key_first($puntajes);
}

function _calcularActivacion(array $capas): float {
    $altas = ['ira','miedo','sorpresa','alegria','placer_ajeno','urgencia','intensidad_alta','amenaza'];
    $bajas = ['tristeza','apatia','aceptacion','soledad','intensidad_baja'];
    $sumAlt = 0; $sumBaj = 0;
    foreach (['emociones_basicas','emociones_complejas','intencion'] as $c) {
        foreach ($capas[$c]['puntajes'] ?? [] as $cat => $p) {
            if (in_array($cat, $altas, true)) $sumAlt += $p;
            if (in_array($cat, $bajas, true)) $sumBaj += $p;
        }
    }
    $total = $sumAlt + $sumBaj;
    return $total <= 0 ? 0.5 : round($sumAlt / $total, 3);
}

function _calcularEtapaFunnel(array $com): string {
    if (empty($com)) return 'desconocida';
    $mapa = [
        'atraccion'    => ['intencion_compra','satisfaccion_alta'],
        'consideracion'=> ['comparacion','objecion_tiempo','objecion_valor','objecion_necesidad'],
        'conversion'   => ['intencion_compra','objecion_precio','objecion_confianza','escasez'],
        'retencion'    => ['fidelizacion','satisfaccion_alta','calidad_alta','servicio_bueno'],
        'fuga'         => ['riesgo_abandono','insatisfaccion','calidad_baja','servicio_malo'],
    ];
    $puntos = [];
    foreach ($mapa as $etapa => $cats) {
        $puntos[$etapa] = 0;
        foreach ($cats as $c) $puntos[$etapa] += $com[$c] ?? 0;
    }
    arsort($puntos);
    return reset($puntos) <= 0 ? 'desconocida' : array_key_first($puntos);
}

function contarLexiconCapas($con): array {
    $catalogo = obtenerCatalogoCapas();
    $out = [];
    foreach ($catalogo as $nombre => $info) {
        if ($nombre === 'general') continue;
        $cols = $info['categorias'];
        $cond = implode(' OR ', array_map(fn($c) => "`$c` > 0", $cols));
        $r = @$con->query("SELECT COUNT(*) AS n FROM dictionary WHERE $cond");
        $out[$nombre] = $r ? (int)$r->fetch_assoc()['n'] : 0;
    }
    return $out;
}
?>
