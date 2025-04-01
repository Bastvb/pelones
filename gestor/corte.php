<?php
// corte.php
session_start();
require_once "config.php";

// Verificamos que exista la sesión del mesero
if (!isset($_SESSION["mesero_id"])) {
    header("Location: login.php");
    exit;
}

// Consulta del total
// Sumamos los precios de cada pedido, usando JOIN con la tabla menu
$sql = "SELECT SUM(menu.precio) as total_ventas
        FROM pedido
        INNER JOIN menu ON pedido.menu_id = menu.id";

$stmt = $pdo->query($sql);
$resultado = $stmt->fetch(PDO::FETCH_ASSOC);

$total = $resultado["total_ventas"] ?? 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Corte de Caja</title>
</head>
<body>
    <div class="container">
        <h1>Corte de Caja</h1>
        <p>Total Vendido: <strong>$<?php echo number_format($total, 2); ?></strong></p>
        <a href="home.php">Regresar al Home</a>
        <br><br>
        <a href="logout.php">Cerrar Sesión</a>
    </div>
</body>
</html>
