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
    <title>Menú - Sistema de Restaurante</title>
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
                    <li><a href="menu.php" class="active">Menú</a></li>
                    <li><a href="tables.php">Mesas</a></li>
                    <li><a href="logout.php">Cerrar Sesión</a></li>
                </ul>
            </nav>
        </header>

        <main>
            <div class="order-section">
                <div class="menu-categories">
                    <h2>Categorías</h2>
                    <ul class="category-list">
                        <li><button class="category-btn active" data-category="comidas">Comidas</button></li>
                        <li><button class="category-btn" data-category="desayunos">Desayunos</button></li>
                        <li><button class="category-btn" data-category="bebidas">Bebidas</button></li>
                    </ul>
                </div>

                <div class="menu-items">
                    <h2>Platillos</h2>
                    <div class="items-container" id="comidas">
                        <?php
                        // Obtener comidas de la base de datos
                        $query = "SELECT * FROM menu_items WHERE categoria = 'comidas'";
                        $result = $conn->query($query);

                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo '<div class="menu-item" data-id="' . $row['id'] . '" data-name="' . $row['nombre'] . '" data-price="' . $row['precio'] . '">';
                                echo '<h3>' . $row['nombre'] . '</h3>';
                                echo '<p class="price">$' . number_format($row['precio'], 2) . '</p>';
                                echo '<button class="add-to-order">Agregar</button>';
                                echo '</div>';
                            }
                        } else {
                            echo '<p>No hay comidas disponibles</p>';
                        }
                        ?>
                    </div>

                    <div class="items-container" id="desayunos" style="display: none;">
                        <?php
                        // Obtener desayunos de la base de datos
                        $query = "SELECT * FROM menu_items WHERE categoria = 'desayunos'";
                        $result = $conn->query($query);

                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo '<div class="menu-item" data-id="' . $row['id'] . '" data-name="' . $row['nombre'] . '" data-price="' . $row['precio'] . '">';
                                echo '<h3>' . $row['nombre'] . '</h3>';
                                echo '<p class="price">$' . number_format($row['precio'], 2) . '</p>';
                                echo '<button class="add-to-order">Agregar</button>';
                                echo '</div>';
                            }
                        } else {
                            echo '<p>No hay desayunos disponibles</p>';
                        }
                        ?>
                    </div>

                    <div class="items-container" id="bebidas" style="display: none;">
                        <?php
                        // Obtener bebidas de la base de datos
                        $query = "SELECT * FROM menu_items WHERE categoria = 'bebidas'";
                        $result = $conn->query($query);

                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo '<div class="menu-item" data-id="' . $row['id'] . '" data-name="' . $row['nombre'] . '" data-price="' . $row['precio'] . '">';
                                echo '<h3>' . $row['nombre'] . '</h3>';
                                echo '<p class="price">$' . number_format($row['precio'], 2) . '</p>';
                                echo '<button class="add-to-order">Agregar</button>';
                                echo '</div>';
                            }
                        } else {
                            echo '<p>No hay bebidas disponibles</p>';
                        }
                        ?>
                    </div>
                </div>

                <div class="current-order">
                    <h2>Orden Actual</h2>
                    <div class="mesa-selector">
                        <label for="mesa">Mesa:</label>
                        <select id="mesa" name="mesa">
                            <?php
                            // Obtener mesas de la base de datos
                            $query = "SELECT * FROM mesas ORDER BY numero";
                            $result = $conn->query($query);

                            if ($result && $result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo '<option value="' . $row['id'] . '">Mesa ' . $row['numero'] . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="order-items">
                        <table id="order-table">
                            <thead>
                                <tr>
                                    <th>Platillo</th>
                                    <th>Precio</th>
                                    <th>Cantidad</th>
                                    <th>Subtotal</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Los items de la orden se agregarán aquí dinámicamente -->
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3">Total</td>
                                    <td id="total-price">$0.00</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <div class="order-actions">
                        <button id="save-order" class="btn-primary">Guardar Orden</button>
                        <button id="clear-order" class="btn-secondary">Limpiar Orden</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal para editar item -->
    <div id="edit-modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Editar Item</h2>
            <form id="edit-form">
                <input type="hidden" id="edit-item-id">
                <div class="form-group">
                    <label for="edit-item-name">Platillo:</label>
                    <input type="text" id="edit-item-name" readonly>
                </div>
                <div class="form-group">
                    <label for="edit-item-quantity">Cantidad:</label>
                    <input type="number" id="edit-item-quantity" min="1" value="1">
                </div>
                <div class="form-group">
                    <label for="edit-item-notes">Notas:</label>
                    <textarea id="edit-item-notes" placeholder="Especificaciones especiales..."></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Guardar Cambios</button>
                    <button type="button" class="btn-secondary" id="cancel-edit">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Variables para mantener el estado de la orden
            let orderItems = [];
            let totalPrice = 0;

            // Cambiar entre categorías
            $('.category-btn').click(function() {
                $('.category-btn').removeClass('active');
                $(this).addClass('active');
                
                const category = $(this).data('category');
                $('.items-container').hide();
                $('#' + category).show();
            });

            // Agregar item a la orden
            $('.add-to-order').click(function() {
                const menuItem = $(this).parent();
                const id = menuItem.data('id');
                const name = menuItem.data('name');
                const price = parseFloat(menuItem.data('price'));
                
                // Verificar si el item ya está en la orden
                const existingItem = orderItems.find(item => item.id === id);
                
                if (existingItem) {
                    // Incrementar cantidad
                    existingItem.quantity++;
                    existingItem.subtotal = existingItem.quantity * existingItem.price;
                } else {
                    // Agregar nuevo item
                    orderItems.push({
                        id: id,
                        name: name,
                        price: price,
                        quantity: 1,
                        subtotal: price,
                        notes: ''
                    });
                }
                
                updateOrderTable();
            });

            // Actualizar la tabla de orden
            function updateOrderTable() {
                const tbody = $('#order-table tbody');
                tbody.empty();
                
                totalPrice = 0;
                
                orderItems.forEach((item, index) => {
                    totalPrice += item.subtotal;
                    
                    const row = `
                        <tr data-index="${index}">
                            <td>${item.name}</td>
                            <td>$${item.price.toFixed(2)}</td>
                            <td>${item.quantity}</td>
                            <td>$${item.subtotal.toFixed(2)}</td>
                            <td>
                                <button class="edit-item">Editar</button>
                                <button class="delete-item">Eliminar</button>
                            </td>
                        </tr>
                    `;
                    
                    tbody.append(row);
                });
                
                $('#total-price').text('$' + totalPrice.toFixed(2));
                
                // Agregar eventos a los botones de editar y eliminar
                $('.edit-item').click(editItem);
                $('.delete-item').click(deleteItem);
            }

            // Editar item
            function editItem() {
                const index = $(this).closest('tr').data('index');
                const item = orderItems[index];
                
                $('#edit-item-id').val(index);
                $('#edit-item-name').val(item.name);
                $('#edit-item-quantity').val(item.quantity);
                $('#edit-item-notes').val(item.notes);
                
                $('#edit-modal').show();
            }

            // Guardar cambios de edición
            $('#edit-form').submit(function(e) {
                e.preventDefault();
                
                const index = $('#edit-item-id').val();
                const quantity = parseInt($('#edit-item-quantity').val());
                const notes = $('#edit-item-notes').val();
                
                if (quantity > 0) {
                    orderItems[index].quantity = quantity;
                    orderItems[index].subtotal = quantity * orderItems[index].price;
                    orderItems[index].notes = notes;
                    
                    updateOrderTable();
                    $('#edit-modal').hide();
                }
            });

            // Eliminar item
            function deleteItem() {
                const index = $(this).closest('tr').data('index');
                orderItems.splice(index, 1);
                updateOrderTable();
            }

            // Cerrar modal
            $('.close, #cancel-edit').click(function() {
                $('#edit-modal').hide();
            });

            // Guardar orden
            $('#save-order').click(function() {
                if (orderItems.length === 0) {
                    alert('La orden está vacía');
                    return;
                }
                
                const mesaId = $('#mesa').val();
                
                // Enviar orden al servidor
                $.ajax({
                    url: 'save_order.php',
                    type: 'POST',
                    data: {
                        mesa_id: mesaId,
                        items: JSON.stringify(orderItems),
                        total: totalPrice
                    },
                    success: function(response) {
                        alert('Orden guardada correctamente');
                        orderItems = [];
                        updateOrderTable();
                    },
                    error: function() {
                        alert('Error al guardar la orden');
                    }
                });
            });

            // Limpiar orden
            $('#clear-order').click(function() {
                if (confirm('¿Estás seguro de que quieres limpiar la orden?')) {
                    orderItems = [];
                    updateOrderTable();
                }
            });
        });
    </script>
</body>
</html>
