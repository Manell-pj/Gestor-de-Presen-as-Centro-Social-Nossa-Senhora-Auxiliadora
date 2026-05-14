<?php
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'gestor_assiduidade';

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die('Erro na ligacao a base de dados: ' . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8mb4');
?>
