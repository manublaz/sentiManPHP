<html>
<head>
	<title></title>
	<meta charset='utf-8'/>
	<link rel='stylesheet' href='style-dictionary.css' type='text/css' media='all'/>
</head>
<body>

<?php
include("config.php");

// Configuración de la paginación
$registrosPorPagina = 200;
if (isset($_GET['pagina'])) {
    $pagina = $_GET['pagina'];
} else {
    $pagina = 1;
}
$empezarDesde = ($pagina - 1) * $registrosPorPagina;

// Consulta SQL para obtener los registros
$sql = "SELECT * FROM dictionary LIMIT $empezarDesde, $registrosPorPagina";
$result = $con->query($sql);

// Paginación
$sqlTotal = "SELECT COUNT(*) AS total FROM dictionary";
$resultTotal = $con->query($sqlTotal);
$rowTotal = $resultTotal->fetch_assoc();
$totalRegistros = $rowTotal['total'];
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);

echo "<h1>Viendo página $pagina</h1>";
echo "<div class='pagination'>";
for ($i = 1; $i <= $totalPaginas; $i++) {
    echo "<a class='plink' href='?pagina=$i' title='Ver página $i'>$i</a>";
}
echo "</div>";


if ($result->num_rows > 0) {
    // Mostrar los registros en forma de tabla
    while ($row = $result->fetch_assoc()) {
        echo "<form action='$_SERVER[PHP_SELF]' method='POST'>";
        echo "<div>";
        echo "<input type='hidden' name='id' value='" . $row['id'] . "'>";
        echo "<input type='hidden' name='fecharegistro' value='" . $row['fecharegistro'] . "'>";
        echo "<input type='text' class='palabra' title='Palabra o frase' name='palabra' value='" . $row['palabra'] . "'>";
        echo "<input type='text' class='positiva' title='Positiva' name='positiva' value='" . $row['positiva'] . "'>";
        echo "<input type='text' class='negativa' title='Negativa' name='negativa' value='" . $row['negativa'] . "'>";
        echo "<input type='text' class='neutral' title='Neutral' name='neutral' value='" . $row['neutral'] . "'>";
        echo "<input type='submit' class='butsave' name='save' value='Guardar cambios'/>";
        echo "<input type='submit' class='butdele' name='delete' value='Eliminar'/>";
        echo "</div>";
        echo "</form>";
    }
} else {
    echo "<li><span>No se encontraron resultados.</span></li>";
}



echo "<div class='pagination'>";
for ($i = 1; $i <= $totalPaginas; $i++) {
    echo "<a class='plink' href='?pagina=$i' title='Ver página $i'>$i</a>";
}
echo "</div>";



// Procesar las peticiones GUARDAR Y ELIMINAR __________________
// =============================================================

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['save'])) {
        $id = $_POST['id'];
        $fechamodif = date('c');
        $palabra = $_POST['palabra'];
        $positiva = $_POST['positiva'];
        $negativa = $_POST['negativa'];
        $neutral = $_POST['neutral'];
        $sql = "UPDATE dictionary SET fechamodificacion='$fechamodif', palabra='$palabra', positiva='$positiva', negativa='$negativa', neutral='$neutral' WHERE id='$id'";
        $result = $con->query($sql);
        echo "<script>window.location.replace(window.location.href);</script>";
        exit(); // Asegurarse de que el script se detenga después de redirigir
        
    }

    if (isset($_POST['delete'])) {
        $id = $_POST['id'];
        $sql = "DELETE FROM dictionary WHERE id='$id'";
        $result = $con->query($sql);
        echo "<script>window.location.replace(window.location.href);</script>";
        exit(); // Asegurarse de que el script se detenga después de redirigir
        
    }
}



?>

</body>
</html>