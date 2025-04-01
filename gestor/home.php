<?php
session_start();
require_once "config.php";

// Verificamos que exista la sesión del mesero
if (!isset($_SESSION["mesero_id"])) {
    header("Location: login.php");
    exit;
}

// --- 1) PROCESAMOS ACCIONES POR POST (Patrón POST-Redirect-GET) ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $accion = $_POST["accion"] ?? "";

    // --- MESAS ---
    if ($accion === "agregar_mesa") {
        // Insertamos una nueva mesa
        $sql = "INSERT INTO mesa (numero, estado) VALUES (0, 'libre')";
        $pdo->exec($sql);

    } elseif ($accion === "eliminar_mesa") {
        $id_mesa = $_POST["id_mesa"] ?? 0;
        $sql = "DELETE FROM mesa WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([":id" => $id_mesa]);
    }

    // --- CREAR PEDIDO (múltiples platillos) ---
    if ($accion === "crear_pedido_multiple") {
        $mesa_id     = $_POST["mesa_id"] ?? 0;
        $mesero_id   = $_SESSION["mesero_id"];
        $mesero_name = $_SESSION["mesero_nombre"] ?? "";
        $fecha       = date("Y-m-d"); 

        // 1) Insertar pedido (encabezado)
        $sqlPedido = "INSERT INTO pedido 
                      (fecha, estado, mesa_id, mesero_id, mesero, total)
                      VALUES
                      (:fecha, 'pendiente', :mesa_id, :mesero_id, :mesero, 0.00)";
        $stmtPed = $pdo->prepare($sqlPedido);
        $stmtPed->execute([
            ":fecha"     => $fecha,
            ":mesa_id"   => $mesa_id,
            ":mesero_id" => $mesero_id,
            ":mesero"    => $mesero_name
        ]);
        $pedido_id = $pdo->lastInsertId();

        // 2) Insertar ítems en "pedido_detalle"
        $items    = $_POST["items"] ?? [];    // checkboxes marcados
        $quantity = $_POST["quantity"] ?? []; // cantidades

        foreach ($items as $menuId => $onValue) {
            $cant = isset($quantity[$menuId]) ? (int)$quantity[$menuId] : 1;
            if ($cant < 1) {
                $cant = 1;
            }
            $sqlDet = "INSERT INTO pedido_detalle (pedido_id, menu_id, cantidad)
                       VALUES (:pedido_id, :menu_id, :cantidad)";
            $stmtDet = $pdo->prepare($sqlDet);
            $stmtDet->execute([
                ":pedido_id" => $pedido_id,
                ":menu_id"   => $menuId,
                ":cantidad"  => $cant
            ]);
        }

        // Cambiamos el estado de la mesa
        $sqlUpdMesa = "UPDATE mesa
                       SET estado = CONCAT('Pedido pendiente de ', :mesero)
                       WHERE id = :mesa_id";
        $stmtUM = $pdo->prepare($sqlUpdMesa);
        $stmtUM->execute([
            ":mesero"  => $mesero_name,
            ":mesa_id" => $mesa_id
        ]);
    }

    // --- COMPLETAR PEDIDO ---
    if ($accion === "completar_pedido") {
        $pedido_id = $_POST["pedido_id"] ?? 0;

        // 1) Calcular la suma real en BD
        $sqlSum = "SELECT SUM(pd.cantidad * m.precio) AS totalCalculado
                   FROM pedido_detalle pd
                   JOIN menu m ON pd.menu_id = m.id
                   WHERE pd.pedido_id = :pid";
        $stmtSum = $pdo->prepare($sqlSum);
        $stmtSum->execute([":pid" => $pedido_id]);
        $totalCalculado = $stmtSum->fetchColumn();
        if (!$totalCalculado) {
            $totalCalculado = 0;
        }

        // 2) Actualizar pedido a 'completado'
        $sqlUpd = "UPDATE pedido
                   SET estado = 'completado',
                       total  = :total
                   WHERE id = :id";
        $stmtUpd = $pdo->prepare($sqlUpd);
        $stmtUpd->execute([
            ":total" => $totalCalculado,
            ":id"    => $pedido_id
        ]);

        // Marcar mesa como "libre"
        $sqlMesaId = "SELECT mesa_id FROM pedido WHERE id = :id LIMIT 1";
        $stmtMesa = $pdo->prepare($sqlMesaId);
        $stmtMesa->execute([":id" => $pedido_id]);
        $mesaRow = $stmtMesa->fetch(PDO::FETCH_ASSOC);
        if ($mesaRow) {
            $mesaActual = $mesaRow["mesa_id"];
            $sqlMesaLibre = "UPDATE mesa
                             SET estado = 'libre'
                             WHERE id = :idm";
            $stmtML = $pdo->prepare($sqlMesaLibre);
            $stmtML->execute([":idm" => $mesaActual]);
        }
    }

    // --- ELIMINAR PEDIDO COMPLETO ---
    if ($accion === "eliminar_pedido") {
        $pedido_id = $_POST["pedido_id"] ?? 0;
        
        $sqlMesaId = "SELECT mesa_id FROM pedido WHERE id = :id LIMIT 1";
        $stmtMesa = $pdo->prepare($sqlMesaId);
        $stmtMesa->execute([":id" => $pedido_id]);
        $rowMesa = $stmtMesa->fetch(PDO::FETCH_ASSOC);

        // Eliminar detalles
        $sqlDelDet = "DELETE FROM pedido_detalle WHERE pedido_id = :pid";
        $stmtDel = $pdo->prepare($sqlDelDet);
        $stmtDel->execute([":pid" => $pedido_id]);

        // Eliminar encabezado
        $sqlDelPed = "DELETE FROM pedido WHERE id = :id";
        $stmtDel2 = $pdo->prepare($sqlDelPed);
        $stmtDel2->execute([":id" => $pedido_id]);

        // Mesa a "libre"
        if ($rowMesa) {
            $mesaLibre = $rowMesa["mesa_id"];
            $sqlMesaLibre = "UPDATE mesa
                             SET estado = 'libre'
                             WHERE id = :id_mesa";
            $stmtML = $pdo->prepare($sqlMesaLibre);
            $stmtML->execute([":id_mesa" => $mesaLibre]);
        }
    }

    // --- MENÚ: AGREGAR / ELIMINAR ---
    if ($accion === "agregar_menu") {
        $nombre    = $_POST["nombre"] ?? "";
        $precio    = $_POST["precio"] ?? 0;
        $categoria = $_POST["categoria"] ?? "";

        $sql = "INSERT INTO menu (nombre, precio, categoria)
                VALUES (:nombre, :precio, :categoria)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ":nombre"    => $nombre,
            ":precio"    => $precio,
            ":categoria" => $categoria
        ]);

    } elseif ($accion === "eliminar_menu") {
        $id_menu = $_POST["id_menu"] ?? 0;
        $sql = "DELETE FROM menu WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([":id" => $id_menu]);
    }

    header("Location: home.php");
    exit;
}

// --- 2) CARGAMOS DATOS (GET) ---

// MESAS
$sqlMesas = "SELECT * FROM mesa ORDER BY id";
$mesas = $pdo->query($sqlMesas)->fetchAll(PDO::FETCH_ASSOC);

// MENÚ
$sqlMenu = "SELECT * FROM menu ORDER BY id";
$menus = $pdo->query($sqlMenu)->fetchAll(PDO::FETCH_ASSOC);

// PEDIDOS PENDIENTES
$sqlPend = "SELECT * FROM pedido 
            WHERE estado = 'pendiente'
            ORDER BY id DESC";
$pendientes = $pdo->query($sqlPend)->fetchAll(PDO::FETCH_ASSOC);

// PEDIDOS COMPLETADOS
$sqlComp = "SELECT * FROM pedido
            WHERE estado = 'completado'
            ORDER BY id DESC";
$completados = $pdo->query($sqlComp)->fetchAll(PDO::FETCH_ASSOC);

/**
 * Función para cargar y agrupar los detalles de un pedido por categoría.
 */
function obtenerDetallesAgrupados(PDO $pdo, $pedidoId) {
    $sql = "SELECT m.categoria,
                   m.nombre,
                   m.precio,
                   pd.cantidad
            FROM pedido_detalle pd
            JOIN menu m ON pd.menu_id = m.id
            WHERE pd.pedido_id = :pid
            ORDER BY m.categoria, m.nombre";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([":pid" => $pedidoId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $agrupados = [];
    foreach ($items as $it) {
        $cat = $it['categoria'] ?? 'Otros';
        if (!isset($agrupados[$cat])) {
            $agrupados[$cat] = [];
        }
        $agrupados[$cat][] = $it; 
    }
    return $agrupados;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Home - Restaurante</title>
    <!-- Un poco de JS para el cálculo previo -->
    <script>
    function calcTotal() {
        let total = 0;
        // Para cada checkbox con clase .check-platillo
        const checkboxes = document.querySelectorAll('.check-platillo');
        checkboxes.forEach(chk => {
            // chk.dataset.price => precio del platillo
            // chk.dataset.id => id del platillo
            const price  = parseFloat(chk.dataset.price) || 0;
            const id     = chk.dataset.id;
            // Buscamos el input de cantidad correspondiente
            const qtyInput = document.getElementById('qty-' + id);
            let qty = parseFloat(qtyInput.value) || 0;

            if (chk.checked) {
                total += (price * qty);
            }
        });
        // Mostramos el total en un <span>
        document.getElementById('estimated-total').textContent = total.toFixed(2);
    }

    window.addEventListener('DOMContentLoaded', () => {
        // Iniciar el cálculo cuando cargue la página
        calcTotal();
        // Si cambian algo, recalculamos
        const inputs = document.querySelectorAll('.check-platillo, .qty-input');
        inputs.forEach(el => {
            el.addEventListener('change', calcTotal);
            el.addEventListener('keyup', calcTotal);
        });
    });
    </script>
</head>
<body>
<div class="container">
    <h1>Bienvenido, <?php echo htmlspecialchars($_SESSION["mesero_nombre"] ?? '', ENT_QUOTES); ?></h1>

    <a href="corte.php">Ir al Corte de Caja</a> |
    <a href="logout.php">Cerrar Sesión</a>
    <hr>

    <!-- SECCIÓN MESAS -->
    <h2>Mesas</h2>
    <div style="display:flex; flex-wrap: wrap;">
        <?php foreach ($mesas as $m): ?>
            <div style="border:1px solid #ccc; margin:5px; padding:10px; min-width:150px;">
                <h4>Mesa ID: <?php echo $m['id']; ?></h4>
                <p>Estado: <?php echo $m['estado']; ?></p>
                <form method="POST"
                      onsubmit="return confirm('¿Seguro que deseas eliminar esta mesa?');">
                    <input type="hidden" name="accion" value="eliminar_mesa">
                    <input type="hidden" name="id_mesa" value="<?php echo $m['id']; ?>">
                    <button type="submit">Eliminar Mesa</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
    <br>
    <form method="POST">
        <input type="hidden" name="accion" value="agregar_mesa">
        <button type="submit">Agregar Nueva Mesa</button>
    </form>
    <hr>

    <!-- CREAR PEDIDO -->
    <h2>Crear Pedido</h2>
    <p>Selecciona la mesa y los platillos. El total estimado se mostrará abajo.</p>
    <form method="POST">
        <input type="hidden" name="accion" value="crear_pedido_multiple">
        
        <label>Mesa:</label>
        <select name="mesa_id" required>
            <option value="">--Selecciona Mesa--</option>
            <?php foreach ($mesas as $m): ?>
                <option value="<?php echo $m['id']; ?>">
                    Mesa #<?php echo $m['id']; ?> (<?php echo $m['estado']; ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <br><br>

        <h4>Platillos del Menú:</h4>
        <?php foreach ($menus as $mn): ?>
            <?php 
              $idPlatillo = $mn['id'];
              $precio     = $mn['precio'];
            ?>
            <div style="margin-bottom:5px;">
                <label>
                    <input 
                        type="checkbox"
                        class="check-platillo"
                        name="items[<?php echo $idPlatillo; ?>]"
                        value="on"
                        data-price="<?php echo $precio; ?>"
                        data-id="<?php echo $idPlatillo; ?>"
                    >
                    <?php echo $mn['nombre'] . " ($" . $precio . ")"; ?>
                </label>
                &nbsp; Cantidad:
                <input 
                    type="number"
                    id="qty-<?php echo $idPlatillo; ?>"
                    name="quantity[<?php echo $idPlatillo; ?>]"
                    value="1"
                    min="1"
                    class="qty-input"
                    style="width:60px;"
                >
            </div>
        <?php endforeach; ?>
        
        <!-- Mostrar total estimado -->
        <p>Total Estimado: $<span id="estimated-total">0.00</span></p>

        <button type="submit">Crear Pedido</button>
    </form>
    <hr>

    <!-- LISTADO DE PEDIDOS PENDIENTES -->
    <h2>Pedidos Pendientes</h2>
    <?php if (count($pendientes) === 0): ?>
        <p>No hay pedidos pendientes.</p>
    <?php else: ?>
        <?php foreach ($pendientes as $p): ?>
            <?php
            // Cargar detalles
            $detalles = obtenerDetallesAgrupados($pdo, $p['id']);
            ?>
            <div style="border:1px dashed #888; margin:10px; padding:10px;">
                <strong>Pedido #<?php echo $p['id']; ?></strong><br>
                Fecha: <?php echo $p['fecha']; ?><br>
                Estado: <?php echo $p['estado']; ?><br>
                Mesa: <?php echo $p['mesa_id']; ?><br>
                Mesero: <?php echo $p['mesero']; ?><br>

                <h4>Detalle de productos:</h4>
                <?php if (empty($detalles)): ?>
                    <p><em>No se han agregado platillos a este pedido.</em></p>
                <?php else: ?>
                    <?php foreach ($detalles as $categoria => $itemsCat): ?>
                        <p><strong><?php echo $categoria; ?></strong></p>
                        <ul>
                            <?php foreach ($itemsCat as $it): ?>
                                <?php
                                $subtotal = $it['precio'] * $it['cantidad'];
                                ?>
                                <li>
                                    <?php echo $it['nombre']; ?>
                                    ( $<?php echo $it['precio']; ?> x <?php echo $it['cantidad']; ?> )
                                    = <strong>$<?php echo $subtotal; ?></strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endforeach; ?>
                <?php endif; ?>

                <form method="POST" 
                      onsubmit="return confirm('¿Seguro de COMPLETAR este pedido?');"
                      style="display:inline-block;">
                    <input type="hidden" name="accion" value="completar_pedido">
                    <input type="hidden" name="pedido_id" value="<?php echo $p['id']; ?>">
                    <button type="submit">Completar Pedido</button>
                </form>

                &nbsp;

                <form method="POST" 
                      onsubmit="return confirm('¿Seguro de ELIMINAR este pedido?');"
                      style="display:inline-block;">
                    <input type="hidden" name="accion" value="eliminar_pedido">
                    <input type="hidden" name="pedido_id" value="<?php echo $p['id']; ?>">
                    <button type="submit">Eliminar Pedido</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <hr>

    <!-- LISTADO DE PEDIDOS COMPLETADOS -->
    <h2>Pedidos Completados</h2>
    <?php if (count($completados) === 0): ?>
        <p>No hay pedidos completados.</p>
    <?php else: ?>
        <?php foreach ($completados as $c): ?>
            <?php 
            $detallesC = obtenerDetallesAgrupados($pdo, $c['id']); 
            ?>
            <div style="border:1px solid #ccc; margin:10px; padding:10px;">
                <strong>Pedido #<?php echo $c['id']; ?></strong><br>
                Fecha: <?php echo $c['fecha']; ?><br>
                Estado: <?php echo $c['estado']; ?><br>
                Total: $<?php echo $c['total']; ?><br>
                Mesa: <?php echo $c['mesa_id']; ?><br>
                Mesero: <?php echo $c['mesero']; ?><br>

                <h4>Detalle de productos:</h4>
                <?php if (empty($detallesC)): ?>
                    <p><em>No se han agregado platillos a este pedido.</em></p>
                <?php else: ?>
                    <?php foreach ($detallesC as $cat => $itemsCat): ?>
                        <p><strong><?php echo $cat; ?></strong></p>
                        <ul>
                            <?php foreach ($itemsCat as $it): ?>
                                <?php 
                                $subtotal = $it['precio'] * $it['cantidad'];
                                ?>
                                <li>
                                    <?php echo $it['nombre']; ?>
                                    ( $<?php echo $it['precio']; ?> x <?php echo $it['cantidad']; ?> )
                                    = <strong>$<?php echo $subtotal; ?></strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <hr>

    <!-- MENÚ (CRUD) -->
    <h2>Menú</h2>
    <h4>Agregar Nuevo Platillo</h4>
    <form method="POST">
        <input type="hidden" name="accion" value="agregar_menu">
        <label>Nombre:</label>
        <input type="text" name="nombre" required>

        <label>Precio:</label>
        <input type="number" step="0.01" name="precio" required>

        <label>Categoría:</label>
        <select name="categoria" required>
            <option value="Comida">Comida</option>
            <option value="Desayuno">Desayuno</option>
            <option value="Bebidas">Bebidas</option>
        </select>
        <button type="submit">Agregar</button>
    </form>

    <h4>Listado del Menú</h4>
    <table border="1" cellpadding="5" cellspacing="0">
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Precio</th>
            <th>Categoría</th>
            <th>Acciones</th>
        </tr>
        <?php foreach ($menus as $mn): ?>
            <tr>
                <td><?php echo $mn['id']; ?></td>
                <td><?php echo $mn['nombre']; ?></td>
                <td><?php echo $mn['precio']; ?></td>
                <td><?php echo $mn['categoria']; ?></td>
                <td>
                    <form method="POST"
                          onsubmit="return confirm('¿Eliminar este platillo?');"
                          style="display:inline;">
                        <input type="hidden" name="accion" value="eliminar_menu">
                        <input type="hidden" name="id_menu" value="<?php echo $mn['id']; ?>">
                        <button type="submit">Eliminar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
</body>
</html>

