<?php
// =================================================================
// 1) INICIO DE SESIÓN Y CONFIGURACIÓN
// =================================================================

// Arrancamos la sesión para saber quién está usando la aplicación
session_start();

// Cargamos la conexión a la base de datos desde config.php
require_once "config.php";

// Verificamos que el mesero haya iniciado sesión
// Si no, lo redirigimos al login y detenemos el script
if (!isset($_SESSION["mesero_id"])) {
    header("Location: login.php");
    exit;
}

// =================================================================
// 2) PROCESAR FORMULARIOS (POST)
// =================================================================
// Aquí detectamos si el navegador envió datos (método POST)
// y ejecutamos la acción correspondiente (CRUD: Create, Delete, Update)

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Leemos qué acción pide el formulario
    $accion = $_POST["accion"] ?? "";

    // -------------------------------------------------
    // 2.a) MESAS
    // -------------------------------------------------
    // Crear nueva mesa vacía
    if ($accion === "agregar_mesa") {
        $sql = "INSERT INTO mesa (numero, estado) VALUES (0, 'libre')";
        $pdo->exec($sql);

    // Eliminar mesa
    } elseif ($accion === "eliminar_mesa") {
        $id_mesa = $_POST["id_mesa"] ?? 0;
        $sql = "DELETE FROM mesa WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([":id" => $id_mesa]);
    }

    // -------------------------------------------------
    // 2.b) CREAR PEDIDO (múltiples platillos) – Create
    // -------------------------------------------------
    if ($accion === "crear_pedido_multiple") {
        // Recogemos datos del formulario y de la sesión
        $mesa_id     = $_POST["mesa_id"] ?? 0;
        $mesero_id   = $_SESSION["mesero_id"];
        $mesero_name = $_SESSION["mesero_nombre"] ?? "";
        $fecha       = date("Y-m-d"); // Fecha actual

        // 1) Insertar encabezado de pedido, con total inicial 0
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
        $pedido_id = $pdo->lastInsertId(); // ID del pedido recién creado

        // 2) Insertar cada platillo en pedido_detalle
        $items    = $_POST["items"]    ?? []; // Checkboxes marcados
        $quantity = $_POST["quantity"] ?? []; // Cantidades

        foreach ($items as $menuId => $on) {
            $cant = isset($quantity[$menuId]) ? (int)$quantity[$menuId] : 1;
            if ($cant < 1) {
                $cant = 1; // Mínimo 1
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

        // 3) Actualizar estado de la mesa a "pendiente"
        $sqlUpdMesa = "UPDATE mesa
                       SET estado = CONCAT('Pedido pendiente de ', :mesero)
                       WHERE id = :mesa_id";
        $stmtUM = $pdo->prepare($sqlUpdMesa);
        $stmtUM->execute([
            ":mesero"  => $mesero_name,
            ":mesa_id" => $mesa_id
        ]);
    }

    // -------------------------------------------------
    // 2.c) COMPLETAR PEDIDO – Update
    // -------------------------------------------------
    if ($accion === "completar_pedido") {
        $pedido_id = $_POST["pedido_id"] ?? 0;

        // 1) Calcular total sumando precio × cantidad
        $sqlSum = "SELECT SUM(pd.cantidad * m.precio) AS totalCalculado
                   FROM pedido_detalle pd
                   JOIN menu m ON pd.menu_id = m.id
                   WHERE pd.pedido_id = :pid";
        $stmtSum = $pdo->prepare($sqlSum);
        $stmtSum->execute([":pid" => $pedido_id]);
        $totalCalculado = $stmtSum->fetchColumn() ?: 0;

        // 2) Marcar pedido como completado y guardar total
        $sqlUpd = "UPDATE pedido
                   SET estado = 'completado',
                       total  = :total
                   WHERE id = :id";
        $stmtUpd = $pdo->prepare($sqlUpd);
        $stmtUpd->execute([
            ":total" => $totalCalculado,
            ":id"    => $pedido_id
        ]);

        // 3) Liberar la mesa asociada
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

    // -------------------------------------------------
    // 2.d) ELIMINAR PEDIDO – Delete
    // -------------------------------------------------
    if ($accion === "eliminar_pedido") {
        $pedido_id = $_POST["pedido_id"] ?? 0;

        // 1) Obtener mesa para liberarla luego
        $sqlMesaId = "SELECT mesa_id FROM pedido WHERE id = :id LIMIT 1";
        $stmtMesa = $pdo->prepare($sqlMesaId);
        $stmtMesa->execute([":id" => $pedido_id]);
        $rowMesa = $stmtMesa->fetch(PDO::FETCH_ASSOC);

        // 2) Borrar detalles del pedido
        $sqlDelDet = "DELETE FROM pedido_detalle WHERE pedido_id = :pid";
        $stmtDel = $pdo->prepare($sqlDelDet);
        $stmtDel->execute([":pid" => $pedido_id]);

        // 3) Borrar encabezado del pedido
        $sqlDelPed = "DELETE FROM pedido WHERE id = :id";
        $stmtDel2 = $pdo->prepare($sqlDelPed);
        $stmtDel2->execute([":id" => $pedido_id]);

        // 4) Liberar la mesa si existía
        if ($rowMesa) {
            $mesaLibre = $rowMesa["mesa_id"];
            $sqlMesaLibre = "UPDATE mesa SET estado='libre' WHERE id=:idm2";
            $stmtML = $pdo->prepare($sqlMesaLibre);
            $stmtML->execute([":idm2" => $mesaLibre]);
        }
    }

    // -------------------------------------------------
    // 2.e) GESTIÓN DEL MENÚ – Create / Update / Delete
    // -------------------------------------------------
    if ($accion === "agregar_menu") {
        // Insertar un nuevo platillo en 'menu'
        $nombre    = $_POST["nombre"]    ?? "";
        $precio    = $_POST["precio"]    ?? 0;
        $categoria = $_POST["categoria"] ?? "";
        $sql = "INSERT INTO menu (nombre, precio, categoria)
                VALUES (:n, :p, :c)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([":n" => $nombre, ":p" => $precio, ":c" => $categoria]);

    } elseif ($accion === "eliminar_menu") {
        // Borrar físicamente un platillo (siempre y cuando no rompa relaciones)
        $id_menu = $_POST["id_menu"] ?? 0;
        $sql = "DELETE FROM menu WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([":id" => $id_menu]);

    } elseif ($accion === "ocultar_menu") {
        // Opción de borrado lógico: dejarlo en base pero no mostrarlo
        $id_menu = $_POST["id_menu"] ?? 0;
        $sql = "UPDATE menu SET activo=0 WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([":id" => $id_menu]);

    } elseif ($accion === "editar_menu") {
        // Actualizar datos de un platillo existente
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

    // Después de procesar cualquiera de estas acciones, recargamos la página
    header("Location: home.php");
    exit;
}

// =================================================================
// 3) CARGAR DATOS PARA MOSTRAR (GET)
// =================================================================
// Aquí hacemos las consultas SELECT para traer:
// - Todas las mesas
// - El menú completo
// - Pedidos pendientes y completados de este mesero

// 3.a) Mesas
$sqlMesas = "SELECT * FROM mesa ORDER BY id";
$mesas = $pdo->query($sqlMesas)->fetchAll(PDO::FETCH_ASSOC);

// 3.b) Menú
// Si tienes campo 'activo', filtra WHERE activo=1 para no mostrar ocultos
$sqlMenu = "SELECT * FROM menu ORDER BY id";
$menus = $pdo->query($sqlMenu)->fetchAll(PDO::FETCH_ASSOC);

// 3.c) Pedidos de este mesero
$miMeseroId = $_SESSION["mesero_id"];
// Pendientes
$sqlPend = "SELECT * FROM pedido
            WHERE estado='pendiente' AND mesero_id=:m
            ORDER BY id DESC";
$stmtPend = $pdo->prepare($sqlPend);
$stmtPend->execute([":m" => $miMeseroId]);
$pendientes = $stmtPend->fetchAll(PDO::FETCH_ASSOC);
// Completados
$sqlComp = "SELECT * FROM pedido
            WHERE estado='completado' AND mesero_id=:m
            ORDER BY id DESC";
$stmtComp = $pdo->prepare($sqlComp);
$stmtComp->execute([":m" => $miMeseroId]);
$completados = $stmtComp->fetchAll(PDO::FETCH_ASSOC);

// Función auxiliar para obtener y agrupar detalles de un pedido
function obtenerDetallesAgrupados(PDO $pdo, $pedidoId) {
    $sql = "SELECT m.categoria, m.nombre, m.precio, pd.cantidad
            FROM pedido_detalle pd
            JOIN menu m ON pd.menu_id = m.id
            WHERE pd.pedido_id = :pid
            ORDER BY m.categoria, m.nombre";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([":pid" => $pedidoId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Agrupamos por categoría para mostrar ordenado
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
    <!-- ===============================================
         METADATOS HTML & ENLACES A ESTILOS E ICONOS
         =============================================== -->
    <meta charset="UTF-8"> <!-- Para tildes y ñ -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Responsive -->
    <title>Sistema de Restaurante</title>
    <!-- Fuente Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Iconos Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Tu CSS personalizado -->
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="app-container">
        <!-- ===============================================
             HEADER: TÍTULO, NOMBRE DE MESERO, DARK MODE, SALIR
             =============================================== -->
        <header class="app-header">
            <div class="container">
                <div class="header-content">
                    <h1 class="app-title">
                        <i class="fas fa-utensils"></i> Sistema de Restaurante
                    </h1>
                    <div class="user-info">
                        <!-- Mostramos nombre del mesero -->
                        <span class="user-name">
                            <?php echo htmlspecialchars($_SESSION["mesero_nombre"] ?? '', ENT_QUOTES); ?>
                        </span>
                        <!-- Botón modo oscuro -->
                        <button class="dark-mode-toggle" id="darkModeToggle">
                            <i class="fas fa-moon"></i>
                        </button>
                        <!-- Enlace cerrar sesión -->
                        <a href="logout.php" class="btn btn-sm btn-danger btn-icon">
                            <i class="fas fa-sign-out-alt"></i> Salir
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- ===============================================
             NAVEGACIÓN PRINCIPAL POR SECCIONES
             =============================================== -->
        <nav class="app-nav">
            <div class="container">
                <ul class="nav-tabs" id="navTabs">
                    <li class="nav-item">
                        <a href="#mesas" class="nav-link active" data-view="mesas">
                            <i class="fas fa-chair"></i> Mesas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#nuevo-pedido" class="nav-link" data-view="nuevo-pedido">
                            <i class="fas fa-plus-circle"></i> Nuevo Pedido
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#pedidos-pendientes" class="nav-link" data-view="pedidos-pendientes">
                            <i class="fas fa-clock"></i> Pendientes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#pedidos-completados" class="nav-link" data-view="pedidos-completados">
                            <i class="fas fa-check-circle"></i> Completados
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#menu" class="nav-link" data-view="menu">
                            <i class="fas fa-book-open"></i> Menú
                        </a>
                    </li>
                    <li class="nav-item">
                        <!-- Enlace al corte de caja (otra página) -->
                        <a href="corte.php" class="nav-link">
                            <i class="fas fa-cash-register"></i> Corte
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- ===============================================
             CONTENIDO PRINCIPAL (SECCIONES VISTAS OCULTAS/MOSTRADAS)
             =============================================== -->
        <main class="app-content">
            <div class="container">
                <!-- ===============================================
                     SECCIÓN 1: Gestión de Mesas
                     =============================================== -->
                <section id="mesas" class="view active fade-in">
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-chair"></i> Gestión de Mesas</h2>
                            <!-- Botón para agregar mesa -->
                            <form method="POST">
                                <input type="hidden" name="accion" value="agregar_mesa">
                                <button type="submit" class="btn btn-primary btn-icon">
                                    <i class="fas fa-plus"></i> Agregar Mesa
                                </button>
                            </form>
                        </div>
                        <div class="card-body">
                            <!-- Recorremos cada mesa y mostramos su estado -->
                            <div class="mesa-grid">
                                <?php foreach ($mesas as $m): ?>
                                    <?php
                                        // Definimos clase y etiqueta de estado según valor
                                        $mesaClass   = 'mesa-card';
                                        $estadoBadge = '';
                                        if ($m['estado'] === 'libre') {
                                            $mesaClass   .= ' libre';
                                            $estadoBadge  = '<span class="badge badge-success">Libre</span>';
                                        } elseif (strpos($m['estado'], 'Pedido pendiente') !== false) {
                                            $mesaClass   .= ' pendiente';
                                            $estadoBadge  = '<span class="badge badge-warning">Pendiente</span>';
                                        } else {
                                            $mesaClass   .= ' ocupada';
                                            $estadoBadge  = '<span class="badge badge-danger">Ocupada</span>';
                                        }
                                    ?>
                                    <div class="<?php echo $mesaClass; ?>">
                                        <div class="mesa-header">
                                            <h3 class="mesa-title">Mesa #<?php echo $m['id']; ?></h3>
                                            <?php echo $estadoBadge; ?>
                                        </div>
                                        <p class="mesa-status"><?php echo $m['estado']; ?></p>
                                        <div class="mesa-actions">
                                            <!-- Botón para eliminar mesa -->
                                            <button type="button"
                                                    class="btn btn-danger btn-sm btn-icon"
                                                    onclick="showDeleteMesaModal(<?php echo $m['id']; ?>)">
                                                <i class="fas fa-trash"></i> Eliminar
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ===============================================
                     SECCIÓN 2: Crear Nuevo Pedido
                     =============================================== -->
                <section id="nuevo-pedido" class="view fade-in">
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-plus-circle"></i> Crear Nuevo Pedido</h2>
                        </div>
                        <div class="card-body">
                            <!-- Formulario para elegir mesa y platillos -->
                            <form method="POST" id="nuevoPedidoForm">
                                <input type="hidden" name="accion" value="crear_pedido_multiple">
                                <!-- Seleccionar mesa -->
                                <div class="form-group">
                                    <label for="mesa_id" class="form-label">Seleccionar Mesa:</label>
                                    <select name="mesa_id" id="mesa_id" required class="form-select">
                                        <option value="">--Selecciona una mesa--</option>
                                        <?php foreach ($mesas as $m): ?>
                                            <option value="<?php echo $m['id']; ?>">
                                                Mesa #<?php echo $m['id']; ?> (<?php echo $m['estado']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <!-- Lista de platillos con checkbox y cantidad -->
                                <h3 class="mb-3">Seleccionar Platillos:</h3>
                                <div class="menu-items">
                                    <?php foreach ($menus as $mn): ?>
                                        <?php 
                                            $idP  = $mn['id'];
                                            $prc  = $mn['precio'];
                                        ?>
                                        <div class="menu-item">
                                            <div class="menu-item-check">
                                                <input 
                                                    type="checkbox"
                                                    class="form-check-input check-platillo"
                                                    name="items[<?php echo $idP; ?>]"
                                                    value="on"
                                                    data-price="<?php echo $prc; ?>"
                                                    data-id="<?php echo $idP; ?>"
                                                    id="item-<?php echo $idP; ?>"
                                                >
                                            </div>
                                            <div class="menu-item-info">
                                                <label for="item-<?php echo $idP; ?>" class="menu-item-name">
                                                    <?php echo $mn['nombre']; ?>
                                                </label>
                                                <div class="menu-item-price">
                                                    $<?php echo $mn['precio']; ?> - <?php echo $mn['categoria']; ?>
                                                </div>
                                            </div>
                                            <div class="menu-item-quantity">
                                                <label for="qty-<?php echo $idP; ?>" class="menu-item-quantity-label">Cantidad:</label>
                                                <input 
                                                    type="number"
                                                    id="qty-<?php echo $idP; ?>"
                                                    name="quantity[<?php echo $idP; ?>]"
                                                    value="1"
                                                    min="1"
                                                    class="menu-item-quantity-input qty-input"
                                                >
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <!-- Mostramos total estimado y botón para confirmar -->
                                <div class="total-box">
                                    <span class="total-label">Total Estimado:</span>
                                    <span class="total-value">
                                        $<span id="estimated-total">0.00</span>
                                    </span>
                                </div>
                                <button type="button"
                                        class="btn btn-success btn-lg btn-icon"
                                        onclick="showCreatePedidoModal()">
                                    <i class="fas fa-save"></i> Crear Pedido
                                </button>
                            </form>
                        </div>
                    </div>
                </section>

                <!-- ===============================================
                     SECCIÓN 3: Pedidos Pendientes
                     =============================================== -->
                <section id="pedidos-pendientes" class="view fade-in">
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-clock"></i> Pedidos Pendientes</h2>
                        </div>
                        <div class="card-body">
                            <?php if (empty($pendientes)): ?>
                                <p class="text-center">No tienes pedidos pendientes.</p>
                            <?php else: ?>
                                <?php foreach ($pendientes as $p): ?>
                                    <?php $det = obtenerDetallesAgrupados($pdo, $p['id']); ?>
                                    <div class="pedido-card pendiente">
                                        <div class="pedido-header">
                                            <h3 class="pedido-title">
                                                <i class="fas fa-receipt"></i> Pedido #<?php echo $p['id']; ?>
                                            </h3>
                                            <span class="badge badge-warning">Pendiente</span>
                                        </div>
                                        <div class="pedido-body">
                                            <!-- Información básica -->
                                            <div class="pedido-info">
                                                <div class="pedido-info-item">
                                                    <span class="pedido-info-label">Fecha:</span>
                                                    <span class="pedido-info-value"><?php echo $p['fecha']; ?></span>
                                                </div>
                                                <div class="pedido-info-item">
                                                    <span class="pedido-info-label">Mesa:</span>
                                                    <span class="pedido-info-value">#<?php echo $p['mesa_id']; ?></span>
                                                </div>
                                                <div class="pedido-info-item">
                                                    <span class="pedido-info-label">Mesero:</span>
                                                    <span class="pedido-info-value"><?php echo $p['mesero']; ?></span>
                                                </div>
                                            </div>
                                            <!-- Detalle de platillos -->
                                            <div class="pedido-detalle">
                                                <h4>Detalle del Pedido:</h4>
                                                <?php if (empty($det)): ?>
                                                    <p><em>No hay platillos en este pedido.</em></p>
                                                <?php else: ?>
                                                    <?php foreach ($det as $cat => $arr): ?>
                                                        <h5 class="pedido-categoria"><?php echo $cat; ?></h5>
                                                        <ul class="pedido-items">
                                                        <?php foreach ($arr as $itm): ?>
                                                            <?php $sub = $itm['precio'] * $itm['cantidad']; ?>
                                                            <li class="pedido-item">
                                                                <span>
                                                                    <?php echo "{$itm['nombre']} ({$itm['precio']} x {$itm['cantidad']})"; ?>
                                                                </span>
                                                                <span>$<?php echo number_format($sub,2); ?></span>
                                                            </li>
                                                        <?php endforeach; ?>
                                                        </ul>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                            <!-- Botones Completar / Eliminar -->
                                            <div class="pedido-actions">
                                                <button type="button"
                                                        class="btn btn-success btn-icon"
                                                        onclick="showCompletePedidoModal(<?php echo $p['id']; ?>)">
                                                    <i class="fas fa-check"></i> Completar
                                                </button>
                                                <button type="button"
                                                        class="btn btn-danger btn-icon"
                                                        onclick="showDeletePedidoModal(<?php echo $p['id']; ?>)">
                                                    <i class="fas fa-trash"></i> Eliminar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <!-- ===============================================
                     SECCIÓN 4: Pedidos Completados
                     =============================================== -->
                <section id="pedidos-completados" class="view fade-in">
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-check-circle"></i> Pedidos Completados</h2>
                        </div>
                        <div class="card-body">
                            <?php if (empty($completados)): ?>
                                <p class="text-center">No hay pedidos completados.</p>
                            <?php else: ?>
                                <?php foreach ($completados as $c): ?>
                                    <?php $detC = obtenerDetallesAgrupados($pdo, $c['id']); ?>
                                    <div class="pedido-card completado">
                                        <div class="pedido-header">
                                            <h3 class="pedido-title">
                                                <i class="fas fa-receipt"></i> Pedido #<?php echo $c['id']; ?>
                                            </h3>
                                            <span class="badge badge-success">Completado</span>
                                        </div>
                                        <div class="pedido-body">
                                            <!-- Información y total -->
                                            <div class="pedido-info">
                                                <div class="pedido-info-item">
                                                    <span class="pedido-info-label">Fecha:</span>
                                                    <span class="pedido-info-value"><?php echo $c['fecha']; ?></span>
                                                </div>
                                                <div class="pedido-info-item">
                                                    <span class="pedido-info-label">Mesa:</span>
                                                    <span class="pedido-info-value">#<?php echo $c['mesa_id']; ?></span>
                                                </div>
                                                <div class="pedido-info-item">
                                                    <span class="pedido-info-label">Mesero:</span>
                                                    <span class="pedido-info-value"><?php echo $c['mesero']; ?></span>
                                                </div>
                                                <div class="pedido-info-item">
                                                    <span class="pedido-info-label">Total:</span>
                                                    <span class="pedido-info-value text-success fw-bold">
                                                        $<?php echo number_format($c['total'],2); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <!-- Detalle de platillos completados -->
                                            <div class="pedido-detalle">
                                                <h4>Detalle del Pedido:</h4>
                                                <?php if (empty($detC)): ?>
                                                    <p><em>No hay platillos en este pedido.</em></p>
                                                <?php else: ?>
                                                    <?php foreach ($detC as $cat => $arr): ?>
                                                        <h5 class="pedido-categoria"><?php echo $cat; ?></h5>
                                                        <ul class="pedido-items">
                                                        <?php foreach ($arr as $itm): ?>
                                                            <?php $sub = $itm['precio'] * $itm['cantidad']; ?>
                                                            <li class="pedido-item">
                                                                <span>
                                                                    <?php echo "{$itm['nombre']} ({$itm['precio']} x {$itm['cantidad']})"; ?>
                                                                </span>
                                                                <span>$<?php echo number_format($sub,2); ?></span>
                                                            </li>
                                                        <?php endforeach; ?>
                                                        </ul>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <!-- ===============================================
                     SECCIÓN 5: Gestión del Menú
                     =============================================== -->
                <section id="menu" class="view fade-in">
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-book-open"></i> Gestión del Menú</h2>
                        </div>
                        <div class="card-body">
                            <!-- A) Agregar Nuevo Platillo -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h3>Agregar Nuevo Platillo</h3>
                                </div>
                                <div class="card-body">
                                    <form method="POST" id="agregarMenuForm">
                                        <input type="hidden" name="accion" value="agregar_menu">
                                        <div class="row">
                                            <div class="col-md-6 col-lg-4">
                                                <div class="form-group">
                                                    <label class="form-label">Nombre:</label>
                                                    <input type="text" name="nombre" required class="form-control">
                                                </div>
                                            </div>
                                            <div class="col-md-6 col-lg-4">
                                                <div class="form-group">
                                                    <label class="form-label">Precio:</label>
                                                    <input type="number" step="0.01" name="precio" required class="form-control">
                                                </div>
                                            </div>
                                            <div class="col-md-6 col-lg-4">
                                                <div class="form-group">
                                                    <label class="form-label">Categoría:</label>
                                                    <select name="categoria" required class="form-select">
                                                        <option value="Comida">Comida</option>
                                                        <option value="Desayuno">Desayuno</option>
                                                        <option value="Bebidas">Bebidas</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-12 mt-3">
                                                <button type="button" class="btn btn-primary btn-icon" onclick="showAddMenuModal()">
                                                    <i class="fas fa-plus"></i> Agregar Platillo
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <!-- B) Listado del Menú -->
                            <div class="card">
                                <div class="card-header">
                                    <h3>Listado del Menú</h3>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Nombre</th>
                                                    <th>Precio</th>
                                                    <th>Categoría</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($menus as $mn): ?>
                                                    <tr>
                                                        <td><?php echo $mn['id']; ?></td>
                                                        <td><?php echo $mn['nombre']; ?></td>
                                                        <td>$<?php echo number_format($mn['precio'],2); ?></td>
                                                        <td><?php echo $mn['categoria']; ?></td>
                                                        <td>
                                                            <div class="d-flex gap-2">
                                                                <!-- EDITAR -->
                                                                <button class="btn btn-sm btn-primary btn-icon-only"
                                                                        onclick="mostrarFormEditar(
                                                                            <?php echo $mn['id'];?>,
                                                                            '<?php echo addslashes($mn['nombre']);?>',
                                                                            '<?php echo $mn['precio'];?>',
                                                                            '<?php echo addslashes($mn['categoria']);?>'
                                                                        )">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <!-- OCULTAR -->
                                                                <button type="button"
                                                                        class="btn btn-sm btn-warning btn-icon-only"
                                                                        onclick="showHideMenuModal(<?php echo $mn['id']; ?>)">
                                                                    <i class="fas fa-eye-slash"></i>
                                                                </button>
                                                                <!-- ELIMINAR -->
                                                                <button type="button"
                                                                        class="btn btn-sm btn-danger btn-icon-only"
                                                                        onclick="showDeleteMenuModal(<?php echo $mn['id']; ?>)">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <!-- C) Formulario Oculto para EDITAR Platillo -->
                            <div id="formEditar" style="display:none;">
                                <div class="card mt-4">
                                    <div class="card-header">
                                        <h3>Editar Platillo</h3>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" id="editarMenuForm">
                                            <input type="hidden" name="accion" value="editar_menu">
                                            <input type="hidden" id="id_menu_edit"  name="id_menu"   value="">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label class="form-label">Nombre:</label>
                                                        <input type="text" id="nombre_edit" name="nombreEdit" required class="form-control">
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label class="form-label">Precio:</label>
                                                        <input type="number" step="0.01" id="precio_edit" name="precioEdit" required class="form-control">
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label class="form-label">Categoría:</label>
                                                        <input type="text" id="cat_edit" name="catEdit" class="form-control">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2 mt-3">
                                                <button type="button" class="btn btn-success btn-icon" onclick="showEditMenuConfirmModal()">
                                                    <i class="fas fa-save"></i> Guardar Cambios
                                                </button>
                                                <button type="button" class="btn btn-secondary btn-icon" onclick="document.getElementById('formEditar').style.display='none';">
                                                    <i class="fas fa-times"></i> Cancelar
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </main>

        <!-- ===============================================
             MODALES DE CONFIRMACIÓN PARA TODAS LAS ACCIONES
             =============================================== -->
        <!-- Modal Eliminar Mesa -->
        <div class="modal" id="deleteMesaModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Confirmar eliminación</h3>
                    <span class="close-modal" onclick="closeModal('deleteMesaModal')">&times;</span>
                </div>
                <div class="modal-body">
                    <p>¿Estás seguro de que deseas eliminar esta mesa?</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" id="deleteMesaForm">
                        <input type="hidden" name="accion" value="eliminar_mesa">
                        <input type="hidden" name="id_mesa" id="delete_mesa_id" value="">
                        <button type="submit" class="btn btn-danger">Eliminar</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('deleteMesaModal')">Cancelar</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Crear Pedido -->
        <div class="modal" id="createPedidoModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Confirmar pedido</h3>
                    <span class="close-modal" onclick="closeModal('createPedidoModal')">&times;</span>
                </div>
                <div class="modal-body">
                    <p>¿Deseas crear este pedido?</p>
                    <p>Total estimado: $<span id="modal-estimated-total">0.00</span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" onclick="submitForm('nuevoPedidoForm')">Crear Pedido</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createPedidoModal')">Cancelar</button>
                </div>
            </div>
        </div>

        <!-- Modal Completar Pedido -->
        <div class="modal" id="completePedidoModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Completar pedido</h3>
                    <span class="close-modal" onclick="closeModal('completePedidoModal')">&times;</span>
                </div>
                <div class="modal-body">
                    <p>¿Deseas marcar este pedido como completado?</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" id="completePedidoForm">
                        <input type="hidden" name="accion" value="completar_pedido">
                        <input type="hidden" name="pedido_id" id="complete_pedido_id" value="">
                        <button type="submit" class="btn btn-success">Completar</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('completePedidoModal')">Cancelar</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Eliminar Pedido -->
        <div class="modal" id="deletePedidoModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Eliminar pedido</h3>
                    <span class="close-modal" onclick="closeModal('deletePedidoModal')">&times;</span>
                </div>
                <div class="modal-body">
                    <p>¿Deseas eliminar este pedido?</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" id="deletePedidoForm">
                        <input type="hidden" name="accion" value="eliminar_pedido">
                        <input type="hidden" name="pedido_id" id="delete_pedido_id" value="">
                        <button type="submit" class="btn btn-danger">Eliminar</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('deletePedidoModal')">Cancelar</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Agregar Platillo -->
        <div class="modal" id="addMenuModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Agregar platillo</h3>
                    <span class="close-modal" onclick="closeModal('addMenuModal')">&times;</span>
                </div>
                <div class="modal-body">
                    <p>¿Confirmas agregar este platillo?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" onclick="submitForm('agregarMenuForm')">Agregar</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addMenuModal')">Cancelar</button>
                </div>
            </div>
        </div>

        <!-- Modal Ocultar Platillo -->
        <div class="modal" id="hideMenuModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Ocultar platillo</h3>
                    <span class="close-modal" onclick="closeModal('hideMenuModal')">&times;</span>
                </div>
                <div class="modal-body">
                    <p>¿Deseas ocultar este platillo del menú?</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" id="hideMenuForm">
                        <input type="hidden" name="accion" value="ocultar_menu">
                        <input type="hidden" name="id_menu" id="hide_menu_id" value="">
                        <button type="submit" class="btn btn-warning">Ocultar</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('hideMenuModal')">Cancelar</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Eliminar Platillo -->
        <div class="modal" id="deleteMenuModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Eliminar platillo</h3>
                    <span class="close-modal" onclick="closeModal('deleteMenuModal')">&times;</span>
                </div>
                <div class="modal-body">
                    <p class="text-danger">¿Confirmas eliminar este platillo permanentemente?</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" id="deleteMenuForm">
                        <input type="hidden" name="accion" value="eliminar_menu">
                        <input type="hidden" name="id_menu" id="delete_menu_id" value="">
                        <button type="submit" class="btn btn-danger">Eliminar</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('deleteMenuModal')">Cancelar</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Confirmar Edición -->
        <div class="modal" id="editMenuConfirmModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Guardar cambios</h3>
                    <span class="close-modal" onclick="closeModal('editMenuConfirmModal')">&times;</span>
                </div>
                <div class="modal-body">
                    <p>¿Confirma los cambios realizados al platillo?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" onclick="submitForm('editarMenuForm')">Guardar</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editMenuConfirmModal')">Cancelar</button>
                </div>
            </div>
        </div>
    </div> <!-- /app-container -->

    <!-- ===================================================================
         4) CÓDIGO JAVASCRIPT
         - Navegación por pestañas (mostrar/ocultar secciones)
         - Cálculo de total estimado
         - Alternar modo oscuro / claro
         - Control de apertura y cierre de modales
         =================================================================== -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // ---------- Navegación por pestañas ----------
        const tabs  = document.querySelectorAll('.nav-link');
        const views = document.querySelectorAll('.view');
        tabs.forEach(tab => {
            tab.addEventListener('click', function(e) {
                const viewId = this.getAttribute('data-view');
                // Si no tiene data-view, es enlace externo (corte.php), dejamos pasar
                if (!viewId) return;
                e.preventDefault(); // Evita recargar
                // Marcar pestaña activa y sección visible
                tabs.forEach(t => t.classList.remove('active'));
                views.forEach(v => v.classList.remove('active'));
                this.classList.add('active');
                document.getElementById(viewId).classList.add('active');
            });
        });

        // ---------- Calcular total estimado para nuevo pedido ----------
        function calcTotal() {
            let total = 0;
            document.querySelectorAll('.check-platillo').forEach(chk => {
                if (chk.checked) {
                    const price = parseFloat(chk.dataset.price) || 0;
                    const id    = chk.dataset.id;
                    const qty   = parseInt(document.getElementById('qty-' + id).value) || 0;
                    total += price * qty;
                }
            });
            document.getElementById('estimated-total').textContent = total.toFixed(2);
        }
        // Vincular cálculo a cambios en checkboxes y cantidades
        document.querySelectorAll('.check-platillo, .qty-input').forEach(el => {
            el.addEventListener('change', calcTotal);
            el.addEventListener('keyup',  calcTotal);
        });
        calcTotal(); // Calcular inicialmente

        // ---------- Modo oscuro / claro ----------
        const darkModeToggle = document.getElementById('darkModeToggle');
        const body = document.body;
        // Cargar preferencia guardada
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            body.classList.add('dark-mode');
            darkModeToggle.innerHTML = '<i class="fas fa-sun"></i>';
        }
        // Cambiar tema al hacer clic
        darkModeToggle.addEventListener('click', function() {
            body.classList.toggle('dark-mode');
            if (body.classList.contains('dark-mode')) {
                localStorage.setItem('theme','dark');
                this.innerHTML = '<i class="fas fa-sun"></i>';
            } else {
                localStorage.setItem('theme','light');
                this.innerHTML = '<i class="fas fa-moon"></i>';
            }
        });
    });

    // ---------- Funciones de modales ----------
    function showModal(modalId) {
        document.getElementById(modalId).style.display = 'flex';
    }
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
    function submitForm(formId) {
        document.getElementById(formId).submit();
    }

    // Funciones específicas para abrir cada modal con datos
    function showDeleteMesaModal(mesaId) {
        document.getElementById('delete_mesa_id').value = mesaId;
        showModal('deleteMesaModal');
    }
    function showCreatePedidoModal() {
        document.getElementById('modal-estimated-total').textContent =
            document.getElementById('estimated-total').textContent;
        showModal('createPedidoModal');
    }
    function showCompletePedidoModal(pedidoId) {
        document.getElementById('complete_pedido_id').value = pedidoId;
        showModal('completePedidoModal');
    }
    function showDeletePedidoModal(pedidoId) {
        document.getElementById('delete_pedido_id').value = pedidoId;
        showModal('deletePedidoModal');
    }
    function showAddMenuModal() {
        showModal('addMenuModal');
    }
    function showHideMenuModal(menuId) {
        document.getElementById('hide_menu_id').value = menuId;
        showModal('hideMenuModal');
    }
    function showDeleteMenuModal(menuId) {
        document.getElementById('delete_menu_id').value = menuId;
        showModal('deleteMenuModal');
    }
    function mostrarFormEditar(id,nombre,precio,cat) {
        // Mostrar formulario de edición y llenarlo con datos del platillo
        document.getElementById('formEditar').style.display = 'block';
        document.getElementById('id_menu_edit').value    = id;
        document.getElementById('nombre_edit').value     = nombre;
        document.getElementById('precio_edit').value     = precio;
        document.getElementById('cat_edit').value        = cat;
        // Hacer scroll suave al formulario
        document.getElementById('formEditar').scrollIntoView({behavior:'smooth'});
    }
    function showEditMenuConfirmModal() {
        showModal('editMenuConfirmModal');
    }

    // Cerrar todos los modales al hacer clic fuera de ellos
    window.onclick = function(event) {
        document.querySelectorAll('.modal').forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    }
    </script>
</body>
</html>


