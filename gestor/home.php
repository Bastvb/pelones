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
//   Así no muestras los "ocultos".
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Restaurante</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <header class="app-header">
            <div class="container">
                <div class="header-content">
                    <h1 class="app-title">
                        <i class="fas fa-utensils"></i> Sistema de Restaurante
                    </h1>
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION["mesero_nombre"] ?? '', ENT_QUOTES); ?></span>
                        <button class="dark-mode-toggle" id="darkModeToggle">
                            <i class="fas fa-moon"></i>
                        </button>
                        <a href="logout.php" class="btn btn-sm btn-danger btn-icon">
                            <i class="fas fa-sign-out-alt"></i> Salir
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Navigation -->
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
                        <a href="corte.php" class="nav-link">
                            <i class="fas fa-cash-register"></i> Corte
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Content -->
        <main class="app-content">
            <div class="container">
                <!-- Mesas View -->
                <section id="mesas" class="view active fade-in">
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-chair"></i> Gestión de Mesas</h2>
                            <form method="POST">
                                <input type="hidden" name="accion" value="agregar_mesa">
                                <button type="submit" class="btn btn-primary btn-icon">
                                    <i class="fas fa-plus"></i> Agregar Mesa
                                </button>
                            </form>
                        </div>
                        <div class="card-body">
                            <div class="mesa-grid">
                                <?php foreach ($mesas as $m): ?>
                                    <?php 
                                    $mesaClass = 'mesa-card';
                                    if ($m['estado'] === 'libre') {
                                        $mesaClass .= ' libre';
                                        $estadoBadge = '<span class="badge badge-success">Libre</span>';
                                    } elseif (strpos($m['estado'], 'Pedido pendiente') !== false) {
                                        $mesaClass .= ' pendiente';
                                        $estadoBadge = '<span class="badge badge-warning">Pendiente</span>';
                                    } else {
                                        $mesaClass .= ' ocupada';
                                        $estadoBadge = '<span class="badge badge-danger">Ocupada</span>';
                                    }
                                    ?>
                                    <div class="<?php echo $mesaClass; ?>">
                                        <div class="mesa-header">
                                            <h3 class="mesa-title">Mesa #<?php echo $m['id']; ?></h3>
                                            <?php echo $estadoBadge; ?>
                                        </div>
                                        <p class="mesa-status"><?php echo $m['estado']; ?></p>
                                        <div class="mesa-actions">
                                            <form method="POST" onsubmit="return confirm('¿Seguro de eliminar esta mesa?');">
                                                <input type="hidden" name="accion" value="eliminar_mesa">
                                                <input type="hidden" name="id_mesa" value="<?php echo $m['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm btn-icon">
                                                    <i class="fas fa-trash"></i> Eliminar
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Nuevo Pedido View -->
                <section id="nuevo-pedido" class="view fade-in">
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-plus-circle"></i> Crear Nuevo Pedido</h2>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="accion" value="crear_pedido_multiple">

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

                                <h3 class="mb-3">Seleccionar Platillos:</h3>
                                <div class="menu-items">
                                    <?php foreach ($menus as $mn): ?>
                                        <?php 
                                        $idP = $mn['id'];
                                        $prc = $mn['precio'];
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

                                <div class="total-box">
                                    <span class="total-label">Total Estimado:</span>
                                    <span class="total-value">$<span id="estimated-total">0.00</span></span>
                                </div>
                                <button type="submit" class="btn btn-success btn-lg btn-icon">
                                    <i class="fas fa-save"></i> Crear Pedido
                                </button>
                            </form>
                        </div>
                    </div>
                </section>

                <!-- Pedidos Pendientes View -->
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

                                            <div class="pedido-detalle">
                                                <h4>Detalle del Pedido:</h4>
                                                <?php if (empty($det)): ?>
                                                    <p><em>No hay platillos en este pedido.</em></p>
                                                <?php else: ?>
                                                    <?php foreach ($det as $cat => $arr): ?>
                                                        <h5 class="pedido-categoria"><?php echo $cat; ?></h5>
                                                        <ul class="pedido-items">
                                                        <?php foreach ($arr as $itm): ?>
                                                            <?php 
                                                            $sub = $itm['precio'] * $itm['cantidad'];
                                                            ?>
                                                            <li class="pedido-item">
                                                                <span><?php echo $itm['nombre']." ($".$itm['precio']." x ".$itm['cantidad'].")"; ?></span>
                                                                <span>$<?php echo $sub; ?></span>
                                                            </li>
                                                        <?php endforeach; ?>
                                                        </ul>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>

                                            <div class="pedido-actions">
                                                <form method="POST" onsubmit="return confirm('¿Completar este pedido?');">
                                                    <input type="hidden" name="accion" value="completar_pedido">
                                                    <input type="hidden" name="pedido_id" value="<?php echo $p['id']; ?>">
                                                    <button type="submit" class="btn btn-success btn-icon">
                                                        <i class="fas fa-check"></i> Completar
                                                    </button>
                                                </form>
                                                <form method="POST" onsubmit="return confirm('¿Eliminar este pedido?');">
                                                    <input type="hidden" name="accion" value="eliminar_pedido">
                                                    <input type="hidden" name="pedido_id" value="<?php echo $p['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-icon">
                                                        <i class="fas fa-trash"></i> Eliminar
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <!-- Pedidos Completados View -->
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
                                                    <span class="pedido-info-value text-success fw-bold">$<?php echo $c['total']; ?></span>
                                                </div>
                                            </div>

                                            <div class="pedido-detalle">
                                                <h4>Detalle del Pedido:</h4>
                                                <?php if (empty($detC)): ?>
                                                    <p><em>No hay platillos en este pedido.</em></p>
                                                <?php else: ?>
                                                    <?php foreach ($detC as $cat => $arr): ?>
                                                        <h5 class="pedido-categoria"><?php echo $cat; ?></h5>
                                                        <ul class="pedido-items">
                                                        <?php foreach ($arr as $itm): ?>
                                                            <?php 
                                                            $sub = $itm['precio'] * $itm['cantidad'];
                                                            ?>
                                                            <li class="pedido-item">
                                                                <span><?php echo $itm['nombre']." ($".$itm['precio']." x ".$itm['cantidad'].")"; ?></span>
                                                                <span>$<?php echo $sub; ?></span>
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

                <!-- Menu View -->
                <section id="menu" class="view fade-in">
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="fas fa-book-open"></i> Gestión del Menú</h2>
                        </div>
                        <div class="card-body">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h3>Agregar Nuevo Platillo</h3>
                                </div>
                                <div class="card-body">
                                    <form method="POST" class="row">
                                        <input type="hidden" name="accion" value="agregar_menu">
                                        
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
                                            <button type="submit" class="btn btn-primary btn-icon">
                                                <i class="fas fa-plus"></i> Agregar Platillo
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

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
                                                        <td>$<?php echo $mn['precio']; ?></td>
                                                        <td><?php echo $mn['categoria']; ?></td>
                                                        <td>
                                                            <div class="d-flex gap-2">
                                                                <!-- Botón EDITAR -->
                                                                <button 
                                                                class="btn btn-sm btn-primary btn-icon-only"
                                                                onclick="mostrarFormEditar(
                                                                    <?php echo $mn['id'];?>,
                                                                    '<?php echo addslashes($mn['nombre']);?>',
                                                                    '<?php echo $mn['precio'];?>',
                                                                    '<?php echo addslashes($mn['categoria']);?>'
                                                                )">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>

                                                                <!-- Botón OCULTAR -->
                                                                <form method="POST"
                                                                    onsubmit="return confirm('¿Ocultar este platillo?');">
                                                                    <input type="hidden" name="accion" value="ocultar_menu">
                                                                    <input type="hidden" name="id_menu" value="<?php echo $mn['id']; ?>">
                                                                    <button type="submit" class="btn btn-sm btn-warning btn-icon-only">
                                                                        <i class="fas fa-eye-slash"></i>
                                                                    </button>
                                                                </form>

                                                                <!-- Botón ELIMINAR -->
                                                                <form method="POST"
                                                                    onsubmit="return confirm('¿Eliminar físicamente este platillo?');">
                                                                    <input type="hidden" name="accion" value="eliminar_menu">
                                                                    <input type="hidden" name="id_menu" value="<?php echo $mn['id']; ?>">
                                                                    <button type="submit" class="btn btn-sm btn-danger btn-icon-only">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- FORMULARIO OCULTO EDITAR -->
                            <div id="formEditar" style="display:none;">
                                <div class="card mt-4">
                                    <div class="card-header">
                                        <h3>Editar Platillo</h3>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <input type="hidden" name="accion" value="editar_menu">
                                            <input type="hidden" id="id_menu_edit" name="id_menu" value="">
                                            
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
                                                <button type="submit" class="btn btn-success btn-icon">
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
    </div>

    <script>
    // Tab navigation
    document.addEventListener('DOMContentLoaded', function() {
        const tabs = document.querySelectorAll('.nav-link');
        const views = document.querySelectorAll('.view');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', function(e) {
                const viewId = this.getAttribute('data-view');
                // Si NO existe 'data-view', significa que es un enlace externo (ejemplo 'corte.php'),
                // así que permitimos que funcione como link normal:
                if (!viewId) {
                    return; // no hacemos preventDefault
                }

                // Caso contrario, es un tab con data-view => evitas la navegación y cambias de vista:
                e.preventDefault();

                // Lógica de cambiar clases .active en tabs y views
                tabs.forEach(t => t.classList.remove('active'));
                views.forEach(v => v.classList.remove('active'));

                this.classList.add('active');
                document.getElementById(viewId).classList.add('active');
            });
        });

        
        // Calculate total for new order
        calcTotal();
        let inputs = document.querySelectorAll('.check-platillo, .qty-input');
        inputs.forEach(i => {
            i.addEventListener('change', calcTotal);
            i.addEventListener('keyup', calcTotal);
        });
        
        // Dark mode toggle
        const darkModeToggle = document.getElementById('darkModeToggle');
        const body = document.body;
        
        // Check for saved theme preference
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            body.classList.add('dark-mode');
            darkModeToggle.innerHTML = '<i class="fas fa-sun"></i>';
        }
        
        darkModeToggle.addEventListener('click', function() {
            body.classList.toggle('dark-mode');
            
            if (body.classList.contains('dark-mode')) {
                localStorage.setItem('theme', 'dark');
                this.innerHTML = '<i class="fas fa-sun"></i>';
            } else {
                localStorage.setItem('theme', 'light');
                this.innerHTML = '<i class="fas fa-moon"></i>';
            }
        });
    });

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
        
        // Scroll to edit form
        document.getElementById('formEditar').scrollIntoView({
            behavior: 'smooth'
        });
    }
    </script>
</body>
</html>

