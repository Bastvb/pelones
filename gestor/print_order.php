<?php
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['mesero'])) {
    header("Location: index.php");
    exit;
}

include('config.php');

if (isset($_GET['orden_id'])) {
    $ordenId = $_GET['orden_id'];
    
    // Obtener detalles de la orden
    $query = "SELECT o.*, m.numero as mesa_numero 
              FROM ordenes o 
              JOIN mesas m ON o.mesa_id = m.id 
              WHERE o.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $ordenId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $orden = $result->fetch_assoc();
        
        // Obtener items de la orden
        $query = "SELECT * FROM orden_items WHERE orden_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $ordenId);
        $stmt->execute();
        $itemsResult = $stmt->get_result();
        
        $items = [];
        while ($item = $itemsResult->fetch_assoc()) {
            $items[] = $item;
        }
    } else {
        die("Orden no encontrada");
    }
} else {
    die("ID de orden no especificado");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir Orden #<?php echo $ordenId; ?></title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            margin: 0;
            padding: 20px;
            width: 80mm; /* Ancho típico de papel de ticket */
        }
        .ticket-header {
            text-align: center;
            margin-bottom: 10px;
        }
        .ticket-info {
            margin-bottom: 10px;
        }
        .ticket-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .ticket-table th, .ticket-table td {
            text-align: left;
            padding: 3px 0;
        }
        .ticket-table .right {
            text-align: right;
        }
        .ticket-total {
            text-align: right;
            font-weight: bold;
            margin-top: 10px;
            border-top: 1px dashed #000;
            padding-top: 5px;
        }
        .ticket-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 10px;
        }
        @media print {
            body {
                width: 100%;
                padding: 0;
                margin: 0;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="ticket-header">
        <h1>RESTAURANTE</h1>
        <p>Ticket de Orden</p>
    </div>
    
    <div class="ticket-info">
        <p><strong>Orden #:</strong> <?php echo $ordenId; ?></p>
        <p><strong>Mesa:</strong> <?php echo $orden['mesa_numero']; ?></p>
        <p><strong>Mesero:</strong> <?php echo $orden['mesero']; ?></p>
        <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($orden['fecha'])); ?></p>
    </div>
    
    <table class="ticket-table">
        <thead>
            <tr>
                <th>Cant</th>
                <th>Descripción</th>
                <th class="right">Precio</th>
                <th class="right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><?php echo $item['cantidad']; ?></td>
                <td><?php echo $item['nombre']; ?></td>
                <td class="right">$<?php echo number_format($item['precio'], 2); ?></td>
                <td class="right">$<?php echo number_format($item['subtotal'], 2); ?></td>
            </tr>
            <?php if (!empty($item['notas'])): ?>
            <tr>
                <td></td>
                <td colspan="3">Nota: <?php echo $item['notas']; ?></td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="ticket-total">
        <p>TOTAL: $<?php echo number_format($orden['total'], 2); ?></p>
    </div>
    
    <div class="ticket-footer">
        <p>¡Gracias por su preferencia!</p>
    </div>
    
    <div class="no-print" style="margin-top: 20px; text-align: center;">
        <button onclick="window.print()">Imprimir</button>
        <button onclick="window.close()">Cerrar</button>
    </div>
    
    <script>
        window.onload = function() {
            // Imprimir automáticamente
            window.print();
        }
    </script>
</body>
</html>
