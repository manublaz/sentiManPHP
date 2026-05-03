<?php
// Generado por el instalador de SentiManPHP v2
mysqli_report(MYSQLI_REPORT_OFF);
define('DB_HOST',     'localhost');
define('DB_PORT',     3306);
define('DB_USER',     'root');
define('DB_PASSWORD', '');
define('DB_NAME',     'sentiman');
define('DB_CHARSET',  'utf8mb4');

date_default_timezone_set('Europe/Madrid');

function getConexion() {
    $con = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);
    $con->set_charset(DB_CHARSET);
    if ($con->connect_error) {
        die('Error de conexion: ' . $con->connect_error);
    }
    return $con;
}
?>
