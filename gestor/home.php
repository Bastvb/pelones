<?php
// home.php
session_start();
require_once "config.php";

// Verificamos que exista la sesión del mesero
if (!isset($_SESSION["mesero_id"])) {
    header("Location: login.php");
    exit;
}

// Si recibimos acciones de CRUD (muy simplificado)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Identificamos el tipo de acción con un campo oculto por ejemplo
    $accion = $_POST["accion"] ?? "";

    // --- Agregar/Editar/Eliminar Mesas ---
    if ($accion === "agregar_mesa") {
        // Insertar nueva mesa
        $sql = "INSERT INTO mesa () VALUES ()"; // Simplemente inserta un nuevo id autoincremental
        $pdo->exec($sql);
    } elseif ($accion === "eliminar_mesa") {
        $id_mesa = $_POST["id_mesa"] ?? 0;
        $sql = "DELETE FROM mesa WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([":id" => $id_mesa]);
    }

    // --- Agregar Pedido ---
    if ($accion === "agregar_pedido") {
        $id_mesa  = $_POST["id_mesa"] ?? 0;
        $id_menu  = $_POST["id_menu"] ?? 0;
        $fecha = date("Y-m-d");  // Ejemplo: la fecha actual
        $mesero_id = $_SESSION["mesero_id"] ?? 0;

        // En este ejemplo no estamos asignando cocinero, lo dejamos NULL
        $sql = "INSERT INTO pedido (fecha, mesa_id, mesero_id, menu_id) 
                VALUES (:fecha, :mesa_id, :mesero_id, :menu_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ":fecha" => $fecha,
            ":mesa_id" => $id_mesa,
            ":mesero_id" => $mesero_id,
            ":menu_id" => $id_menu
        ]);
    } elseif ($accion === "eliminar_pedido") {
        $id_pedido = $_POST["id_pedido"] ?? 0;
        $sql = "DELETE FROM pedido WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([":id" => $id_pedido]);
    }

    // --- Agregar/Editar/Eliminar Menú ---
    if ($accion === "agregar_menu") {
        $nombre   = $_POST["nombre"] ?? "";
        $precio   = $_POST["precio"] ?? 0;
        $categoria= $_POST["categoria"] ?? "";

        $sql = "INSERT INTO menu (nombre, precio, categoria) 
                VALUES (:nombre, :precio, :categoria)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ":nombre" => $nombre,
            ":precio" => $precio,
            ":categoria" => $categoria
        ]);
    } elseif ($accion === "eliminar_menu") {
        $id_menu = $_POST["id_menu"] ?? 0;
        $sql = "DELETE FROM menu WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([":id" => $id_menu]);
    }
}

// Cargamos la información para mostrarla en la página
// Mesas
$sqlMesas = "SELECT * FROM mesa";
$mesas = $pdo->query($sqlMesas)->fetchAll(PDO::FETCH_ASSOC);

// Menú
$sqlMenu = "SELECT * FROM menu";
$menus = $pdo->query($sqlMenu)->fetchAll(PDO::FETCH_ASSOC);

// Pedidos (unidos con la tabla menu para ver nombre y precio)
$sqlPedidos = "SELECT p.*, m.nombre AS nombre_platillo, m.precio AS precio_platillo
               FROM pedido p
               LEFT JOIN menu m ON p.menu_id = m.id
               ORDER BY p.id DESC";
$pedidos = $pdo->query($sqlPedidos)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Home - Restaurante</title>
    <!-- Incluye Bootstrap u otro CSS a tu gusto -->
</head>
<body>
<div class="container">
    <h1>Bienvenido, <?php echo $_SESSION["mesero_nombre"]; ?> </h1>
    <a href="corte.php">Ir al Corte de Caja</a> |
    <a href="logout.php">Cerrar Sesión</a>
    <hr>

    <!-- Sección Mesas -->
    <h2>Mesas</h2>
    <div class="row">
        <?php foreach ($mesas as $m): ?>
            <div class="col-sm-3" style="border:1px solid #ccc; margin:5px; padding:10px;">
                <h4>Mesa #<?php echo $m['id']; ?></h4>
                <!-- Botón eliminar mesa -->
                <form method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar esta mesa?')">
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

    <!-- Sección Pedidos -->
    <h2>Pedidos</h2>
    <p>Puedes “agregar un pedido” asociándolo a una mesa y un platillo del menú:</p>
    <form method="POST">
        <input type="hidden" name="accion" value="agregar_pedido">
        <label>Mesa:</label>
        <select name="id_mesa" required>
            <option value="">--Selecciona--</option>
            <?php foreach ($mesas as $m): ?>
                <option value="<?php echo $m['id']; ?>">MESA #<?php echo $m['id']; ?></option>
            <?php endforeach; ?>
        </select>
        <label>Platillo:</label>
        <select name="id_menu" required>
            <option value="">--Selecciona--</option>
            <?php foreach ($menus as $mn): ?>
                <option value="<?php echo $mn['id']; ?>">
                    <?php echo $mn['nombre'] . " ($".$mn['precio'].")"; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Agregar Pedido</button>
    </form>

    <h3>Lista de pedidos recientes</h3>
    <div>
        <?php foreach ($pedidos as $p): ?>
            <div style="border:1px dashed #aaa; margin:5px; padding:5px;">
                <strong>Pedido #<?php echo $p['id']; ?></strong><br>
                Fecha: <?php echo $p['fecha']; ?><br>
                Mesa: <?php echo $p['mesa_id']; ?><br>
                Platillo: <?php echo $p['nombre_platillo']; ?>
                ( $<?php echo $p['precio_platillo']; ?> )<br>
                
                <!-- Eliminar pedido -->
                <form method="POST" style="margin-top:5px;"
                      onsubmit="return confirm('¿Seguro que deseas eliminar este pedido?');">
                    <input type="hidden" name="accion" value="eliminar_pedido">
                    <input type="hidden" name="id_pedido" value="<?php echo $p['id']; ?>">
                    <button type="submit">Eliminar</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
    <hr>

    <!-- Sección Menú -->
    <h2>Menú</h2>
    <h4>Agregar Nuevo Platillo</h4>
    <form method="POST">
        <input type="hidden" name="accion" value="agregar_menu">
        <label>Nombre:</label>
        <input type="text" name="nombre" required>
        <label>Precio:</label>
        <input type="number" step="0.01" name="precio" required>
        <label>Categoría:</label>
        <input type="text" name="categoria">
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
                <!-- Eliminar -->
                <form method="POST" style="display:inline;"
                      onsubmit="return confirm('¿Seguro que deseas eliminar este platillo?');">
                    <input type="hidden" name="accion" value="eliminar_menu">
                    <input type="hidden" name="id_menu" value="<?php echo $mn['id']; ?>">
                    <button type="submit">Eliminar</button>
                </form>
                <!-- Si deseas editar, puedes implementar un formulario adicional o un modal -->
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
</body>
</html>