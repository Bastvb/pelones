<?php
// =======================
// 1) Código PHP inicial
// =======================

// Arrancamos la “sesión” para saber quién está usando la aplicación
session_start();

// Cargamos los datos de conexión a la base de datos
require_once "config.php";

// Verificamos que el mesero haya iniciado sesión
// Si no está logueado, lo mandamos al login.php y detenemos este script
if (!isset($_SESSION["mesero_id"])) {
    header("Location: login.php");
    exit;
}

// =======================
// 2) Leer datos de la BD
// =======================

// Preparamos la consulta SQL para obtener, por cada fecha,
// la suma de los totales de pedidos completados
$sql = "
    SELECT
        fecha,
        SUM(total) AS total_dia
    FROM pedido
    WHERE estado = 'completado'
    GROUP BY fecha
    ORDER BY fecha DESC
";
// Ejecutamos la consulta
$result = $pdo->query($sql);
// Traemos todos los resultados en un arreglo asociativo
$registros = $result->fetchAll(PDO::FETCH_ASSOC);

// Calculamos el gran total sumando cada total_dia
$granTotal = 0;
foreach ($registros as $row) {
    $granTotal += $row['total_dia'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <!--
        =======================
        3) CABECERA HTML (HEAD)
        =======================
        Aquí definimos:
         - El juego de caracteres (UTF-8)
         - Para que sea responsive en móviles
         - El título de la pestaña
         - Enlaces a fuentes, iconos y estilos CSS
    -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Corte de Caja - Sistema de Restaurante</title>

    <!-- Fuente Inter de Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Iconos Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Tu hoja de estilos personalizada -->
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <!--
        =======================
        4) ESTRUCTURA DEL BODY
        =======================
        .app-container engloba toda la app
        HEADER: título e info del usuario
        NAV: menú de navegación
        MAIN: contenido (aquí, el corte de caja)
    -->
    <div class="app-container">
        <!-- ===== HEADER ===== -->
        <header class="app-header">
            <div class="container">
                <div class="header-content">
                    <!-- Icono y título de la app -->
                    <h1 class="app-title">
                        <i class="fas fa-cash-register"></i>
                        Sistema de Restaurante
                    </h1>
                    <!-- Sección de usuario: nombre, modo oscuro y salir -->
                    <div class="user-info">
                        <!-- Mostramos el nombre de forma segura -->
                        <span class="user-name">
                            <?php echo htmlspecialchars($_SESSION["mesero_nombre"] ?? '', ENT_QUOTES); ?>
                        </span>
                        <!-- Botón para alternar modo claro/oscuro -->
                        <button class="dark-mode-toggle" id="darkModeToggle">
                            <i class="fas fa-moon"></i>
                        </button>
                        <!-- Enlace para cerrar sesión -->
                        <a href="logout.php" class="btn btn-sm btn-danger btn-icon">
                            <i class="fas fa-sign-out-alt"></i> Salir
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- ===== NAVEGACIÓN ===== -->
        <nav class="app-nav">
            <div class="container">
                <ul class="nav-tabs">
                    <li class="nav-item">
                        <a href="home.php" class="nav-link">
                            <i class="fas fa-home"></i> Inicio
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="corte.php" class="nav-link active">
                            <i class="fas fa-cash-register"></i> Corte de Caja
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- ===== CONTENIDO PRINCIPAL ===== -->
        <main class="app-content">
            <div class="container">
                <div class="card">
                    <!-- Título de la tarjeta -->
                    <div class="card-header">
                        <h2><i class="fas fa-cash-register"></i> Corte de Caja</h2>
                    </div>
                    <div class="card-body">
                        <?php if (count($registros) === 0): ?>
                            <!-- Mensaje cuando no hay datos -->
                            <div class="text-center p-4">
                                <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No hay pedidos completados aún.</p>
                            </div>
                        <?php else: ?>
                            <!-- Tabla con resultados -->
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th class="text-right">Total del día</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($registros as $row): ?>
                                        <tr>
                                            <td><?php echo $row['fecha']; ?></td>
                                            <td class="text-right">
                                                $<?php echo number_format($row['total_dia'], 2); ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="fw-bold" style="background-color: var(--primary); color: white;">
                                            <td>TOTAL GENERAL</td>
                                            <td class="text-right">
                                                $<?php echo number_format($granTotal, 2); ?>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <!-- Pie de la tarjeta con botones -->
                    <div class="card-footer">
                        <div class="d-flex justify-content-between">
                            <a href="home.php" class="btn btn-secondary btn-icon">
                                <i class="fas fa-arrow-left"></i> Volver al Inicio
                            </a>
                            <?php if (count($registros) > 0): ?>
                            <!-- Botón que abre el modal de confirmación -->
                            <button class="btn btn-primary btn-icon" id="printReportBtn">
                                <i class="fas fa-print"></i> Imprimir Reporte
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ===== TARJETA DE RESUMEN ===== -->
                <?php if (count($registros) > 0): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-pie"></i> Resumen</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Total General -->
                            <div class="col-md-6">
                                <div class="card bg-primary" style="color: white;">
                                    <div class="card-body">
                                        <h4 class="mb-0">Total General</h4>
                                        <div class="d-flex align-items-center justify-content-between">
                                            <i class="fas fa-dollar-sign fa-3x"></i>
                                            <span style="font-size: 2rem; font-weight: 700;">
                                                $<?php echo number_format($granTotal, 2); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Días con Ventas -->
                            <div class="col-md-6">
                                <div class="card bg-success" style="color: white;">
                                    <div class="card-body">
                                        <h4 class="mb-0">Días con Ventas</h4>
                                        <div class="d-flex align-items-center justify-content-between">
                                            <i class="fas fa-calendar-check fa-3x"></i>
                                            <span style="font-size: 2rem; font-weight: 700;">
                                                <?php echo count($registros); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <!--
        =======================
        5) MODAL DE IMPRESIÓN
        =======================
        Este bloque está oculto por defecto y aparece al hacer clic en Imprimir.
    -->
    <div class="modal" id="printConfirmModal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Imprimir Reporte</h3>
                <!-- Botón para cerrar el modal -->
                <span class="close-modal" onclick="closeModal('printConfirmModal')">&times;</span>
            </div>
            <div class="modal-body">
                <p>¿Deseas imprimir el reporte de corte de caja?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="printReport()">Imprimir</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('printConfirmModal')">Cancelar</button>
            </div>
        </div>
    </div>

    <!--
        =======================
        6) CÓDIGO JavaScript
        =======================
        - Alternar modo oscuro/claro
        - Controlar apertura/cierre del modal
        - Lanzar la impresión real
    -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 6.1) Modo oscuro
        const darkModeToggle = document.getElementById('darkModeToggle');
        const body = document.body;
        // Revisamos si ya había preferencia guardada
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            body.classList.add('dark-mode');
            darkModeToggle.innerHTML = '<i class="fas fa-sun"></i>';
        }
        // Al hacer clic cambiamos el tema y guardamos la preferencia
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

        // 6.2) Botón Imprimir
        const printBtn = document.getElementById('printReportBtn');
        if (printBtn) {
            printBtn.addEventListener('click', function() {
                showModal('printConfirmModal');
            });
        }
    });

    // Mostrar modal por su ID
    function showModal(modalId) {
        document.getElementById(modalId).style.display = 'flex';
    }
    // Cerrar modal
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
    // Al confirmar, cerramos modal e imprimimos
    function printReport() {
        closeModal('printConfirmModal');
        window.print();
    }
    // Cerrar modal al hacer clic fuera de él
    window.onclick = function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = "none";
            }
        });
    }
    </script>

    <!--
        =======================
        7) ESTILOS PARA IMPRESIÓN
        =======================
        Al imprimir, ocultamos elementos innecesarios y ajustamos colores.
    -->
    <style>
    @media print {
        .app-header, .app-nav, .card-footer, .dark-mode-toggle {
            display: none !important;
        }
        body {
            background-color: white !important;
            color: black !important;
        }
        .card {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
        }
        .table th {
            background-color: #f8f9fa !important;
            color: black !important;
        }
        .table tfoot tr {
            background-color: #f8f9fa !important;
            color: black !important;
            font-weight: bold !important;
        }
    }
    </style>
</body>
</html>



