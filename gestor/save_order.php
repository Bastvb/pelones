<?php
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['mesero'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit('No autorizado');
}

include('config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener datos de la orden
    $mesaId = $_POST['mesa_id'];
    $items = json_decode($_POST['items'], true);
    $total = $_POST['total'];
    $mesero = $_SESSION['mesero'];
    
    // Validar datos
    if (empty($mesaId) || empty($items) || !is_array($items)) {
        header('HTTP/1.1 400 Bad Request');
        exit('Datos inválidos');
    }
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    try {
        // Insertar orden
        $stmt = $conn->prepare("INSERT INTO ordenes (mesa_id, mesero, total, fecha, estado) VALUES (?, ?, ?, NOW(), 'pendiente')");
        $stmt->bind_param("isd", $mesaId, $mesero, $total);
        $stmt->execute();
        
        $ordenId = $conn->insert_id;
        
        // Insertar items de la orden
        $stmt = $conn->prepare("INSERT INTO orden_items (orden_id, item_id, nombre, precio, cantidad, subtotal, notas) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($items as $item) {
            $stmt->bind_param("iisdids", $ordenId, $item['id'], $item['name'], $item['price'], $item['quantity'], $item['subtotal'], $item['notes']);
            $stmt->execute();
        }
        
        // Actualizar estado de la mesa
        $stmt = $conn->prepare("UPDATE mesas SET estado = 'ocupada' WHERE id = ?");
        $stmt->bind_param("i", $mesaId);
        $stmt->execute();
        
        // Confirmar transacción
        $conn->commit();
        
        echo 'Orden guardada correctamente';
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $conn->rollback();
        header('HTTP/1.1 500 Internal Server Error');
        exit('Error al guardar la orden: ' . $e->getMessage());
    }
} else {
    header('HTTP/1.1 405 Method Not Allowed');
    exit('Método no permitido');
}

