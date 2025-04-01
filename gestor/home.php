<?php
session_start();
require_once "config.php";

// Verificamos la sesión
if (!isset($_SESSION["mesero_id"])) {
    header("Location: login.php");
    exit;
}

// --- 1) PROCESAMOS ACCIONES (POST) ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $accion = $_POST["accion"] ?? "";

    // --- MESAS ---
    if ($accion === "agregar_mesa") {
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
        $mesa_id      = $_POST["mesa_id"] ?? 0;
        $mesero_id    = $_SESSION["mesero_id"];
        $mesero_name  = $_SESSION["mesero_nombre"] ?? "";
        $fecha        = date("Y-m-d");

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
        $items    = $_POST["items"] ?? [];
        $quantity = $_POST["quantity"] ?? [];
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

        // Marcar mesa => "Pedido pendiente de..."
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

        // Calcular total
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

        $sqlUpd = "UPDATE pedido
                   SET estado = 'completado',
                       total  = :total
                   WHERE id = :id";
        $stmtUpd = $pdo->prepare($sqlUpd);
        $stmtUpd->execute([
            ":total" => $totalCalculado,
            ":id"    => $pedido_id
        ]);

        // Mesa => libre
        $sqlMesaId = "SELECT mesa_id FROM pedido WHERE id = :id LIMIT 1";
        $stmtMesa = $pdo->prepare($sqlMesaId);
        $stmtMesa->execute([":id" => $pedido_id]);
        $row = $stmtMesa->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $mesaActual = $row["mesa_id"];
            $sqlMesaLibre = "UPDATE mesa SET estado='libre' WHERE id=:idm";
            $stmtML = $pdo->prepare($sqlMesaLibre);
            $stmtML->execute([":idm" => $mesaActual]);
        }
    }

    // --- ELIMINAR PEDIDO ---
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

        // Mesa => libre
        if ($rowMesa) {
            $mesaLibre = $rowMesa["mesa_id"];
            $sqlMesaLibre = "UPDATE mesa SET estado='libre' WHERE id=:idm2";
            $stmtML = $pdo->prepare($sqlMesaLibre);
            $stmtML->execute([":idm2" => $mesaLibre]);
        }
    }

    // --- MENÚ: AGREGAR / ELIMINAR / OCULTAR / EDITAR ---
    if ($accion === "agregar_menu") {
        $nombre    = $_POST["nombre"]   ?? "";
        $precio    = $_POST["precio"]   ?? 0;
        $categoria = $_POST["categoria"]?? "";

        // Para usar borrado lógico, necesitamos la columna 'activo' en 'menu'.
        // Si ya la tienes: 
        // $sql = "INSERT INTO menu (nombre, precio, categoria, activo) VALUES (:n, :p, :c, 1)";
        // Sino, sigue así:
        $sql = "INSERT INTO menu (nombre, precio, categoria)
                VALUES (:n, :p, :c)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ":n" => $nombre,
            ":p" => $precio,
            ":c" => $categoria
        ]);

    } elseif ($accion === "eliminar_menu") {
        // ***** LÓGICA ANTIGUA (eliminar físico) *****
        // Esto puede fallar si el platillo ya fue usado en un pedido, 
        // a menos que tengas ON DELETE CASCADE
        $id_menu = $_POST["id_menu"] ?? 0;
        $sql = "DELETE FROM menu WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([":id" => $id_menu]);

    } elseif ($accion === "ocultar_menu") {
        // ***** LÓGICA NUEVA (borrado lógico) *****
        // Necesita la columna 'activo' TINYINT(1) en la tabla 'menu'.
        $id_menu = $_POST["id_menu"] ?? 0;
        $sql = "UPDATE menu SET activo=0 WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([":id" => $id_menu]);

    } elseif ($accion === "editar_menu") {
        // ***** EDITAR MENÚ *****
        $id_menu   = $_POST["id_menu"]    ?? 0;
        $nombre    = $_POST["nombreEdit"] ?? "";
        $precio    = $_POST["precioEdit"] ?? 0;
        $categoria = $_POST["catEdit"]    ?? "";

        $sql = "UPDATE menu
                SET nombre=:n, precio=:p, categoria=:c
                WHERE id=:id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ":n"  => $nombre,
            ":p"  => $precio,
            ":c"  => $categoria,
            ":id" => $id_menu
        ]);
    }

    header("Location: home.php");
    exit;
}

// --- 2) CARGAMOS DATOS (GET) ---

// MESAS (todos ven todas)
$sqlMesas = "SELECT * FROM mesa ORDER BY id";
$mesas = $pdo->query($sqlMesas)->fetchAll(PDO::FETCH_ASSOC);

// MENÚ
// si tienes la columna 'activo', puedes filtrar los activos para mostrarlos en la parte de "Crear Pedido".
// Ejemplo:
//   $sqlMenu = "SELECT * FROM menu WHERE activo=1 ORDER BY id";
//   Así no muestras los “ocultos”.
// Pero en la tabla final, mostramos todos
$sqlMenu = "SELECT * FROM menu ORDER BY id";
$menus = $pdo->query($sqlMenu)->fetchAll(PDO::FETCH_ASSOC);

// Pedidos de ESTE mesero
$miMeseroId = $_SESSION["mesero_id"];

// Pendientes
$sqlPend = "SELECT * FROM pedido
            WHERE estado='pendiente'
              AND mesero_id=:m
            ORDER BY id DESC";
$stmtPend = $pdo->prepare($sqlPend);
$stmtPend->execute([":m" => $miMeseroId]);
$pendientes = $stmtPend->fetchAll(PDO::FETCH_ASSOC);

// Completados
$sqlComp = "SELECT * FROM pedido
            WHERE estado='completado'
              AND mesero_id=:m
            ORDER BY id DESC";
$stmtComp = $pdo->prepare($sqlComp);
$stmtComp->execute([":m" => $miMeseroId]);
$completados = $stmtComp->fetchAll(PDO::FETCH_ASSOC);

/** 
 * Función para agrupar detalles por categoría
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
    <script>
    function calcTotal() {
        let total = 0;
        const checks = document.querySelectorAll('.check-platillo');
        checks.forEach(chk => {
            const price = parseFloat(chk.dataset.price) || 0;
            const id    = chk.dataset.id;
            const qtyIn = document.getElementById('qty-' + id);
            const qty   = parseFloat(qtyIn.value) || 0;
            if (chk.checked) {
                total += price * qty;
            }
        });
        document.getElementById('estimated-total').textContent = total.toFixed(2);
    }

    function mostrarFormEditar(id, nombre, precio, cat) {
        document.getElementById('formEditar').style.display = 'block';
        document.getElementById('id_menu_edit').value  = id;
        document.getElementById('nombre_edit').value   = nombre;
        document.getElementById('precio_edit').value   = precio;
        document.getElementById('cat_edit').value      = cat;
    }

    window.addEventListener('DOMContentLoaded', () => {
        calcTotal();
        let inputs = document.querySelectorAll('.check-platillo, .qty-input');
        inputs.forEach(i => {
            i.addEventListener('change', calcTotal);
            i.addEventListener('keyup', calcTotal);
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

    <!-- MESAS -->
    <h2>Mesas</h2>
    <div style="display:flex; flex-wrap: wrap;">
        <?php foreach ($mesas as $m): ?>
        <div style="border:1px solid #ccc; margin:5px; padding:10px;">
            <h4>Mesa #<?php echo $m['id']; ?></h4>
            <p>Estado: <?php echo $m['estado']; ?></p>
            <form method="POST" onsubmit="return confirm('¿Seguro de eliminar esta mesa?');">
                <input type="hidden" name="accion" value="eliminar_mesa">
                <input type="hidden" name="id_mesa" value="<?php echo $m['id']; ?>">
                <button type="submit">Eliminar</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <br>
    <form method="POST">
        <input type="hidden" name="accion" value="agregar_mesa">
        <button type="submit">Agregar Mesa</button>
    </form>
    <hr>

    <!-- CREAR PEDIDO -->
    <h2>Crear Pedido</h2>
    <form method="POST">
        <input type="hidden" name="accion" value="crear_pedido_multiple">

        <label>Mesa:</label>
        <select name="mesa_id" required>
            <option value="">--Selecciona--</option>
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
            $idP = $mn['id'];
            $prc = $mn['precio'];
            ?>
            <div style="margin-bottom:5px;">
                <label>
                    <input 
                        type="checkbox"
                        class="check-platillo"
                        name="items[<?php echo $idP; ?>]"
                        value="on"
                        data-price="<?php echo $prc; ?>"
                        data-id="<?php echo $idP; ?>"
                    >
                    <?php echo $mn['nombre']." ($".$mn['precio'].")"; ?>
                </label>
                Cantidad:
                <input 
                    type="number"
                    id="qty-<?php echo $idP; ?>"
                    name="quantity[<?php echo $idP; ?>]"
                    value="1"
                    min="1"
                    class="qty-input"
                    style="width:60px;"
                >
            </div>
        <?php endforeach; ?>

        <p>Total Estimado: $<span id="estimated-total">0.00</span></p>
        <button type="submit">Crear Pedido</button>
    </form>
    <hr>

    <!-- PEDIDOS PENDIENTES (MESERO) -->
    <h2>Mis Pedidos Pendientes</h2>
    <?php if (empty($pendientes)): ?>
        <p>No tienes pedidos pendientes.</p>
    <?php else: ?>
        <?php foreach ($pendientes as $p): ?>
            <?php $det = obtenerDetallesAgrupados($pdo, $p['id']); ?>
            <div style="border:1px dashed #888; margin:10px; padding:10px;">
                <strong>Pedido #<?php echo $p['id']; ?></strong><br>
                Fecha: <?php echo $p['fecha']; ?><br>
                Estado: <?php echo $p['estado']; ?><br>
                Mesa: <?php echo $p['mesa_id']; ?><br>
                Mesero: <?php echo $p['mesero']; ?><br>

                <h4>Detalle:</h4>
                <?php if (empty($det)): ?>
                    <p><em>No hay platillos.</em></p>
                <?php else: ?>
                    <?php foreach ($det as $cat => $arr): ?>
                        <strong><?php echo $cat; ?></strong>
                        <ul>
                        <?php foreach ($arr as $itm): ?>
                            <?php 
                            $sub = $itm['precio'] * $itm['cantidad'];
                            ?>
                            <li><?php echo $itm['nombre']." ($".$itm['precio']." x ".$itm['cantidad'].") = $".$sub; ?></li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endforeach; ?>
                <?php endif; ?>

                <form method="POST"
                      onsubmit="return confirm('¿Completar este pedido?');"
                      style="display:inline;">
                    <input type="hidden" name="accion" value="completar_pedido">
                    <input type="hidden" name="pedido_id" value="<?php echo $p['id']; ?>">
                    <button type="submit">Completar</button>
                </form>
                &nbsp;
                <form method="POST"
                      onsubmit="return confirm('¿Eliminar este pedido?');"
                      style="display:inline;">
                    <input type="hidden" name="accion" value="eliminar_pedido">
                    <input type="hidden" name="pedido_id" value="<?php echo $p['id']; ?>">
                    <button type="submit">Eliminar</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <hr>

    <!-- PEDIDOS COMPLETADOS (MESERO) -->
    <h2>Mis Pedidos Completados</h2>
    <?php if (empty($completados)): ?>
        <p>No hay pedidos completados.</p>
    <?php else: ?>
        <?php foreach ($completados as $c): ?>
            <?php $detC = obtenerDetallesAgrupados($pdo, $c['id']); ?>
            <div style="border:1px solid #ccc; margin:10px; padding:10px;">
                <strong>Pedido #<?php echo $c['id']; ?></strong><br>
                Fecha: <?php echo $c['fecha']; ?><br>
                Estado: <?php echo $c['estado']; ?><br>
                Total: $<?php echo $c['total']; ?><br>
                Mesa: <?php echo $c['mesa_id']; ?><br>
                Mesero: <?php echo $c['mesero']; ?><br>

                <h4>Detalle:</h4>
                <?php if (empty($detC)): ?>
                    <p><em>No hay platillos.</em></p>
                <?php else: ?>
                    <?php foreach ($detC as $cat => $arr): ?>
                        <strong><?php echo $cat; ?></strong>
                        <ul>
                        <?php foreach ($arr as $itm): ?>
                            <?php 
                            $sub = $itm['precio'] * $itm['cantidad'];
                            ?>
                            <li><?php echo $itm['nombre']." ($".$itm['precio']." x ".$itm['cantidad'].") = $".$sub; ?></li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <hr>

    <!-- MENÚ (Aquí tenemos lógica antigua + nueva) -->
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

    <h4>Listado del Menú (Físico + Borrado Lógico + Editar)</h4>
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

                    <!-- Botón ELIMINAR (antiguo) -->
                    <form method="POST"
                          onsubmit="return confirm('¿Eliminar físicamente este platillo?');"
                          style="display:inline;">
                        <input type="hidden" name="accion" value="eliminar_menu">
                        <input type="hidden" name="id_menu" value="<?php echo $mn['id']; ?>">
                        <button type="submit">Eliminar Físico</button>
                    </form>

                    &nbsp;

                    <!-- Botón OCULTAR (borrado lógico) -->
                    <form method="POST"
                          onsubmit="return confirm('¿Ocultar este platillo (borrado lógico)?');"
                          style="display:inline;">
                        <input type="hidden" name="accion" value="ocultar_menu">
                        <input type="hidden" name="id_menu" value="<?php echo $mn['id']; ?>">
                        <button type="submit">Ocultar</button>
                    </form>

                    &nbsp;

                    <!-- Botón EDITAR (abre formulario) -->
                    <button 
                      onclick="mostrarFormEditar(
                        <?php echo $mn['id'];?>,
                        '<?php echo addslashes($mn['nombre']);?>',
                        '<?php echo $mn['precio'];?>',
                        '<?php echo addslashes($mn['categoria']);?>'
                      )"
                    >Editar</button>

                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <!-- FORMULARIO OCULTO EDITAR -->
    <div id="formEditar" style="display:none; border:1px solid #aaa; padding:10px; margin:10px 0;">
        <h4>Editar Platillo</h4>
        <form method="POST">
            <input type="hidden" name="accion" value="editar_menu">
            <input type="hidden" id="id_menu_edit" name="id_menu" value="">
            
            <label>Nombre:</label>
            <input type="text" id="nombre_edit" name="nombreEdit" required>
            <br><br>

            <label>Precio:</label>
            <input type="number" step="0.01" id="precio_edit" name="precioEdit" required>
            <br><br>

            <label>Categoría:</label>
            <input type="text" id="cat_edit" name="catEdit">
            <br><br>

            <button type="submit">Guardar Cambios</button>
            <button type="button" onclick="document.getElementById('formEditar').style.display='none';">
                Cancelar
            </button>
        </form>
    </div>
</div>

<script>
function mostrarFormEditar(id, nombre, precio, categoria) {
    document.getElementById('formEditar').style.display = 'block';
    document.getElementById('id_menu_edit').value = id;
    document.getElementById('nombre_edit').value  = nombre;
    document.getElementById('precio_edit').value  = precio;
    document.getElementById('cat_edit').value     = categoria;
}
</script>
</body>
</html>
