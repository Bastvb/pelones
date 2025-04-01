<?php
session_start();
require_once "config.php";

if (!isset($_SESSION["mesero_id"])) {
    header("Location: login.php");
    exit;
}

// Obtenemos la SUMA total de todos los pedidos completados, agrupados por fecha
$sql = "SELECT fecha, SUM(total) AS total_dia
        FROM pedido
        WHERE estado = 'completado'
        GROUP BY fecha
        ORDER BY fecha DESC";
$result = $pdo->query($sql);
$registros = $result->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Corte de Caja</title>
</head>
<body>
<h1>Corte de Caja (Pedidos Completados)</h1>

<a href="home.php">Regresar al Home</a> |
<a href="logout.php">Cerrar Sesión</a>
<hr>

<?php if (count($registros) === 0): ?>
    <p>No hay pedidos completados aún.</p>
<?php else: ?>
    <table border="1" cellpadding="5" cellspacing="0">
        <tr>
            <th>Fecha</th>
            <th>Total del día</th>
        </tr>
        <?php 
        $granTotal = 0; 
        foreach ($registros as $row): 
            $granTotal += $row['total_dia'];
        ?>
        <tr>
            <td><?php echo $row['fecha']; ?></td>
            <td>$<?php echo number_format($row['total_dia'], 2); ?></td>
        </tr>
        <?php endforeach; ?>
        <tr>
            <td><strong>TOTAL GENERAL</strong></td>
            <td><strong>$<?php echo number_format($granTotal, 2); ?></strong></td>
        </tr>
    </table>
<?php endif; ?>
</body>
</html>
