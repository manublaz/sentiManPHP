<?php
// ============================================================
//  SentiManPHP v2 — Configuración de base de datos
//  Este archivo es sobreescrito por el instalador (install.php)
// ============================================================

define('DB_HOST',     'localhost');
define('DB_PORT',     3306);        // Cambia a 3307 si XAMPP usa ese puerto
define('DB_USER',     'root');
define('DB_PASSWORD', '');
define('DB_NAME',     'sentiman');
define('DB_CHARSET',  'utf8mb4');

date_default_timezone_set('Europe/Madrid');

function getConexion() {
    mysqli_report(MYSQLI_REPORT_OFF);
    $con = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);
    $con->set_charset(DB_CHARSET);
    if ($con->connect_error) {
        die('Error de conexión: ' . $con->connect_error);
    }
    return $con;
}
?>
