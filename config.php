<?php

// Conexión a la base de datos
$servername = "";
$username = "";
$password = "";
$dbname = "";

$con = new mysqli($servername, $username, $password, $dbname);
if ($con->connect_error) {
    die("Error de conexión: " . $con->connect_error);
}

?>