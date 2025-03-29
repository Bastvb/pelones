<?php
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['mesero'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit('No autorizado');
}

include('config.php');

if (isset($_GET['mesa_id'])) {
    $mesaId = $_GET['mesa_id'];
    
    // Obtener órdenes de la mesa
    $query = "SELECT o.*, m.numero as mesa_numero 
              FROM ordenes o 
              JOIN mesas m ON o.mesa_id = m.id 
              WHERE o.mesa_id = ? AND o.estado != 'completada' 
              ORDER BY o.fecha DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $mesaId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo '<div class="orders-list">';
        
        while ($row = $result->fetch_assoc()) {
            $fecha = date('d/m/Y H:i', strtotime($row['fecha']));
            $estadoClass = '';
            
            switch ($row['estado']) {
                case 'pendiente':
                    $estadoClass = 'status-pending';
                    break;
                case 'en_proceso':
                    $estadoClass = 'status-processing';
                    break;
                case 'lista':
                    $estadoClass = 'status-ready';
                    break;
            }
            
            echo '<div class="order-item">';
            echo '<div class="order-header">';
            echo '<div class="order-id">Orden #' . $row['id'] . '</div>';
            echo '<div class="order-date">' . $fecha . '</div>';
            echo '</div>';
            echo '<div class="order-info">';
            echo '<div class="order-mesero">Mesero: ' . $row['mesero'] . '</div>';
            echo '<div class="order-total">Total: $' . number_format($row['total'], 2) . '</div>';
            echo '<div class="order-status ' . $estadoClass . '">' . ucfirst(str_replace('_', ' ', $row['estado'])) . '</div>';
            echo '</div>';
            echo '<div class="order-actions">';
            echo '<button class="view-order-details" data-id="' . $row['id'] . '">Ver Detalles</button>';
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
    } else {
        echo '<p>No hay órdenes pendientes para esta mesa</p>';
    }
} else {
    echo '<p class="select-table-message">Selecciona una mesa para ver sus órdenes</p>';
}
