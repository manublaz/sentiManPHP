<?php

// Función para tokenizar el texto
function tokenizarTexto($texto) {
    return preg_split('/\s+/', $texto);
}

// Función para analizar el sentimiento del texto
function analizarSentimiento($texto, $con) {

    $palabras = tokenizarTexto($texto);
    $totalPuntosPositivos = 0;
    $totalPuntosNegativos = 0;
    $totalPuntosNeutros = 0;
    $totalPalabras = count($palabras);
    
    // Buscar cada palabra en la tabla de diccionario y sumar sus puntos
    foreach ($palabras as $palabra) {
        $sql = "SELECT * FROM dictionary WHERE palabra LIKE '%$palabra%'";
        $result = $con->query($sql);
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $totalPuntosPositivos += $row['positiva'];
            $totalPuntosNegativos += $row['negativa'];
            $totalPuntosNeutros += $row['neutral'];
        }
    }
    
    // Calcular el puntaje promedio de sentimiento para el texto
    $puntajePositivoPromedio = $totalPuntosPositivos / $totalPalabras;
    $puntajeNegativoPromedio = $totalPuntosNegativos / $totalPalabras;
    $puntajeNeutroPromedio = $totalPuntosNeutros / $totalPalabras;
    
    // Retornar los puntajes promedio
    return [
        'positiva' => $puntajePositivoPromedio,
        'negativa' => $puntajeNegativoPromedio,
        'neutral' => $puntajeNeutroPromedio
    ];
}

// Uso de la función para analizar el sentimiento del texto
$resultado = analizarSentimiento($texto, $con);
$punPos=$resultado['positiva'];
$punNeg=$resultado['negativa'];
$punNeu=$resultado['neutral'];

// Calcular porcentajes
$totalPuntos = $punPos + $punNeg + $punNeu;
$porcentajePositivo = ($punPos / $totalPuntos) * 100;
$porcentajeNegativo = ($punNeg / $totalPuntos) * 100;
$porcentajeNeutral = ($punNeu / $totalPuntos) * 100;

// Porcentajes redondeados
$porPosRound = round($porcentajePositivo, 2);
$porNegRound = round($porcentajeNegativo, 2);
$porNeuRound = round($porcentajeNeutral, 2);

echo "
<div class='resultado'>
    <h1 class='titulo'>Resultado del Análisis de Sentimiento</h1>
    <div class='puntajes'>
        <div class='puntaje'>Puntaje Positivo: $punPos</div>
        <div class='puntaje'>Puntaje Negativo: $punNeg</div>
        <div class='puntaje'>Puntaje Neutral: $punNeu</div>
    </div>
    <div class='porcentajes'>
        <div class='puntaje'>Porcentaje Positivo: $porPosRound%</div>
        <div class='puntaje'>Porcentaje Negativo: $porNegRound%</div>
        <div class='puntaje'>Porcentaje Neutral: $porNeuRound%</div>
    </div>
</div>

<canvas id='myCircleGraph' style='width: 100%;'></canvas>
<script src='chart.min.js'></script>
<script src='chartjs-plugin-datalabels.min.js'></script>
<script id='rendered-js'>
    var circle = document.getElementById('myCircleGraph').getContext('2d');
    var myCircleGraph = new Chart(circle, {
        type: 'pie',
        data: {
            labels: ['Positivo', 'Negativo', 'Neutral'],
            datasets: [
                { label: 'Porcentajes',
                  data: [$porPosRound, $porNegRound, $porNeuRound],
                  borderWidth: 0,
                  backgroundColor: [
                    'rgba(54, 162, 235, 0.6)',
                    'rgba(255, 99, 132, 0.6)',
                    'rgba(255, 206, 86, 0.6)'],
                  borderColor: [
                    'rgba(54, 162, 235, 0.5)',
                    'rgba(255, 99, 132, 0.5)',
                    'rgba(255, 206, 86, 0.5)'],
            lineTension: 0.1 }] },
            options: {
                legend: {
                display: true,
                position: 'top',
                labels: {
                    boxWidth: 20,
                    fontColor: '#444444' } },
            plugins: {
                datalabels: {
                formatter: (value, ctx) => {
                    let sum = 0;
                    let dataArr = ctx.chart.data.datasets[0].data;
                    dataArr.map(data => {
                        sum += data;
                    });
                    let percentage = (value * 100 / sum).toFixed(2) + '%';
                    return percentage;
            },
    color: '#fff' } } } });
</script>
";


?>