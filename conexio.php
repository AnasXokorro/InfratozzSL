<?php
$host = '192.168.248.36';
$db = 'infratozz';
$user = 'admin';
$pass = '1234';

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Error en la conexión: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8");
?>
