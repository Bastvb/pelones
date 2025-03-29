<?php
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['mesero'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit('No autorizado');
}

include('config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['orden_id'])) {
    $ordenId = $_POST['orden_id'];
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    try {
        // Actualizar estado de la orden
        $stmt = $conn->prepare("UPDATE ordenes SET estado = 'completada' WHERE id = ?");
        $stmt->bind_param("i", $ordenId);
        $stmt->execute();
        
        // Verificar si hay más órdenes pendientes para la mesa
        $stmt = $conn->prepare("SELECT mesa_id FROM ordenes WHERE id = ?");
        $stmt->bind_param("i", $ordenId);
        $stmt->execute();
        $result = $stmt->get_result();
        $orden = $result->fetch_assoc();
        $mesaId = $orden['mesa_id'];
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM ordenes WHERE mesa_id = ? AND estado != 'completada'");
        $stmt->bind_param("i", $mesaId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        // Si no hay más órdenes pendientes, liberar la mesa
        if ($row['count'] == 0) {
            $stmt = $conn->prepare("UPDATE mesas SET estado = 'libre' WHERE id = ?");
            $stmt->bind_param("i", $mesaId);
            $stmt->execute();
        }
        
        // Confirmar transacción
        $conn->commit();
        
        echo 'Orden completada correctamente';
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $conn->rollback();
        header('HTTP/1.1 500 Internal Server Error');
        exit('Error al completar la orden: ' . $e->getMessage());
    }
} else {
    header('HTTP/1.1 400 Bad Request');
    exit('Datos inválidos');
}
