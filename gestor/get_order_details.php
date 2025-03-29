<?php
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['mesero'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit('No autorizado');
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
        $fecha = date('d/m/Y H:i', strtotime($orden['fecha']));
        
        echo '<div class="order-details">';
        echo '<div class="order-details-header">';
        echo '<div class="order-details-info">';
        echo '<p><strong>Mesa:</strong> ' . $orden['mesa_numero'] . '</p>';
        echo '<p><strong>Mesero:</strong> ' . $orden['mesero'] . '</p>';
        echo '<p><strong>Fecha:</strong> ' . $fecha . '</p>';
        echo '<p><strong>Estado:</strong> ' . ucfirst(str_replace('_', ' ', $orden['estado'])) . '</p>';
        echo '</div>';
        echo '</div>';
        
        // Obtener items de la orden
        $query = "SELECT * FROM orden_items WHERE orden_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $ordenId);
        $stmt->execute();
        $itemsResult = $stmt->get_result();
        
        if ($itemsResult->num_rows > 0) {
            echo '<table class="order-details-table">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>Platillo</th>';
            echo '<th>Precio</th>';
            echo '<th>Cantidad</th>';
            echo '<th>Subtotal</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            while ($item = $itemsResult->fetch_assoc()) {
                echo '<tr>';
                echo '<td>' . $item['nombre'] . '</td>';
                echo '<td>$' . number_format($item['precio'], 2) . '</td>';
                echo '<td>' . $item['cantidad'] . '</td>';
                echo '<td>$' . number_format($item['subtotal'], 2) . '</td>';
                echo '</tr>';
                
                if (!empty($item['notas'])) {
                    echo '<tr class="item-notes">';
                    echo '<td colspan="4"><strong>Notas:</strong> ' . $item['notas'] . '</td>';
                    echo '</tr>';
                }
            }
            
            echo '</tbody>';
            echo '<tfoot>';
            echo '<tr>';
            echo '<td colspan="3"><strong>Total</strong></td>';
            echo '<td><strong>$' . number_format($orden['total'], 2) . '</strong></td>';
            echo '</tr>';
            echo '</tfoot>';
            echo '</table>';
        } else {
            echo '<p>No hay detalles disponibles para esta orden</p>';
        }
        
        echo '</div>';
    } else {
        echo '<p>Orden no encontrada</p>';
    }
} else {
    echo '<p>ID de orden no especificado</p>';
}
