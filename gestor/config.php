<?php
// config.php
$host = "localhost";
$usuario = "emydevco";
$password = "Cherry_may123-";
$basededatos = "emydevco_restaurante";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$basededatos;charset=utf8", $usuario, $password);
    // Para mostrar errores en modo desarrollo
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Error de conexión: " . $e->getMessage();
    exit;
}
?>
