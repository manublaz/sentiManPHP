<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SentiManPHP</title>
    <link rel='stylesheet' href='style.css' type='text/css' media='all'/>
</head>
<body>
<?php

echo "
<form action='$_SERVER[PHP_SELF]' method='post'>
<h1>SentiManPHP</h1>
    <label for='texto'>Introduce tu texto completo:</label><br>
    <textarea id='texto' name='texto' placeholder='Escribe aquí tu texto...'></textarea><br>
    <button type='submit' name='submit'>Enviar Texto</button>
</form>
";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {

    if (!empty($_POST['texto'])) {
        $texto = $_POST['texto'];
        echo "<h2>Texto enviado:</h2>";
        echo "<div class=''>" . htmlspecialchars($texto) . "</div>";
        
        include("config.php");
        include("sentiman.php");
        
    } else {
        echo "<li><span>No se recibió ningún texto.</span></li>";
    }
}
?>

</body>
</html>
