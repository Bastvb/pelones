<?php
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['mesero'])) {
    header("Location: index.php");
    exit;
}

include('config.php');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mesas - Sistema de Restaurante</title>
    <link rel="stylesheet" href="paqueseveabonito.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="container">
        <header>
            <h1>Sistema de Restaurante</h1>
            <p>Mesero: <?php echo $_SESSION['mesero']; ?></p>
            <nav>
                <ul>
                    <li><a href="home.php">Inicio</a></li>
                    <li><a href="menu.php">Menú</a></li>
                    <li><a href="tables.php" class="active">Mesas</a></li>
                    <li><a href="logout.php">Cerrar Sesión</a></li>
                </ul>
            </nav>
        </header>

        <main>
            <div class="tables-section">
                <h2>Mesas del Restaurante</h2>
                
                <div class="tables-grid">
                    <?php
                    // Obtener mesas de la base de datos
                    $query = "SELECT m.*, 
                             (SELECT COUNT(*) FROM ordenes o WHERE o.mesa_id = m.id AND o.estado != 'completada') as tiene_ordenes
                             FROM mesas m ORDER BY m.numero";
                    $result = $conn->query($query);

                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $tableClass = 'table-item';
                            if ($row['tiene_ordenes'] > 0) {
                                $tableClass .= ' has-orders';
                            }
                            if ($row['estado'] == 'ocupada') {
                                $tableClass .= ' occupied';
                            }
                            
                            echo '<div class="' . $tableClass . '" data-id="' . $row['id'] . '">';
                            echo '<div class="table-number">Mesa ' . $row['numero'] . '</div>';
                            if ($row['tiene_ordenes'] > 0) {
                                echo '<div class="table-status">Con órdenes</div>';
                            } else if ($row['estado'] == 'ocupada') {
                                echo '<div class="table-status">Ocupada</div>';
                            } else {
                                echo '<div class="table-status">Libre</div>';
                            }
                            echo '</div>';
                        }
                    } else {
                        echo '<p>No hay mesas disponibles</p>';
                    }
                    ?>
                </div>
            </div>
            
            <div class="table-orders" id="table-orders">
                <h2>Órdenes de la Mesa <span id="selected-table">-</span></h2>
                <div id="orders-container">
                    <p class="select-table-message">Selecciona una mesa para ver sus órdenes</p>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal para ver detalles de orden -->
    <div id="order-details-modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Detalles de la Orden #<span id="order-id"></span></h2>
            <div id="order-details-container">
                <!-- Los detalles de la orden se cargarán aquí -->
            </div>
            <div class="form-actions">
                <button id="complete-order" class="btn-primary">Completar Orden</button>
                <button id="print-order" class="btn-secondary">Imprimir</button>
                <button id="close-details" class="btn-secondary">Cerrar</button>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Seleccionar mesa
            $('.table-item').click(function() {
                const tableId = $(this).data('id');
                const tableNumber = $(this).find('.table-number').text();
                
                $('#selected-table').text(tableNumber);
                $('.table-item').removeClass('selected');
                $(this).addClass('selected');
                
                // Cargar órdenes de la mesa
                $.ajax({
                    url: 'get_table_orders.php',
                    type: 'GET',
                    data: { mesa_id: tableId },
                    success: function(response) {
                        $('#orders-container').html(response);
                        
                        // Agregar eventos a los botones de ver detalles
                        $('.view-order-details').click(viewOrderDetails);
                    },
                    error: function() {
                        $('#orders-container').html('<p>Error al cargar las órdenes</p>');
                    }
                });
            });

            // Ver detalles de orden
            function viewOrderDetails() {
                const orderId = $(this).data('id');
                
                $('#order-id').text(orderId);
                
                // Cargar detalles de la orden
                $.ajax({
                    url: 'get_order_details.php',
                    type: 'GET',
                    data: { orden_id: orderId },
                    success: function(response) {
                        $('#order-details-container').html(response);
                        $('#order-details-modal').show();
                        
                        // Configurar el botón de completar orden
                        $('#complete-order').data('id', orderId);
                    },
                    error: function() {
                        $('#order-details-container').html('<p>Error al cargar los detalles</p>');
                    }
                });
            }

            // Completar orden
            $('#complete-order').click(function() {
                const orderId = $(this).data('id');
                
                if (confirm('¿Estás seguro de que quieres marcar esta orden como completada?')) {
                    $.ajax({
                        url: 'complete_order.php',
                        type: 'POST',
                        data: { orden_id: orderId },
                        success: function(response) {
                            alert('Orden completada correctamente');
                            $('#order-details-modal').hide();
                            
                            // Recargar órdenes de la mesa seleccionada
                            $('.table-item.selected').click();
                        },
                        error: function() {
                            alert('Error al completar la orden');
                        }
                    });
                }
            });

            // Imprimir orden
            $('#print-order').click(function() {
                const orderId = $('#order-id').text();
                window.open('print_order.php?orden_id=' + orderId, '_blank');
            });

            // Cerrar modales
            $('.close, #close-details').click(function() {
                $('#order-details-modal').hide();
            });
        });
    </script>
</body>
</html>

