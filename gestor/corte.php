<?php
session_start();
require_once "config.php";

if (!isset($_SESSION["mesero_id"])) {
   header("Location: login.php");
   exit;
}

// Obtenemos la SUMA total de todos los pedidos completados, agrupados por fecha
$sql = "SELECT fecha, SUM(total) AS total_dia
       FROM pedido
       WHERE estado = 'completado'
       GROUP BY fecha
       ORDER BY fecha DESC";
$result = $pdo->query($sql);
$registros = $result->fetchAll(PDO::FETCH_ASSOC);

// Calcular el gran total
$granTotal = 0;
foreach ($registros as $row) {
    $granTotal += $row['total_dia'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Corte de Caja - Sistema de Restaurante</title>
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
                       <i class="fas fa-cash-register"></i> Sistema de Restaurante
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

       <!-- Content -->
       <main class="app-content">
           <div class="container">
               <div class="card">
                   <div class="card-header">
                       <h2><i class="fas fa-cash-register"></i> Corte de Caja</h2>
                   </div>
                   <div class="card-body">
                       <?php if (count($registros) === 0): ?>
                           <div class="text-center p-4">
                               <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                               <p class="text-muted">No hay pedidos completados aún.</p>
                           </div>
                       <?php else: ?>
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
                                           <td class="text-right">$<?php echo number_format($row['total_dia'], 2); ?></td>
                                       </tr>
                                       <?php endforeach; ?>
                                   </tbody>
                                   <tfoot>
                                       <tr class="fw-bold" style="background-color: var(--primary); color: white;">
                                           <td>TOTAL GENERAL</td>
                                           <td class="text-right">$<?php echo number_format($granTotal, 2); ?></td>
                                       </tr>
                                   </tfoot>
                               </table>
                           </div>
                       <?php endif; ?>
                   </div>
                   <div class="card-footer">
                       <div class="d-flex justify-content-between">
                           <a href="home.php" class="btn btn-secondary btn-icon">
                               <i class="fas fa-arrow-left"></i> Volver al Inicio
                           </a>
                           <?php if (count($registros) > 0): ?>
                           <button class="btn btn-primary btn-icon" onclick="window.print()">
                               <i class="fas fa-print"></i> Imprimir Reporte
                           </button>
                           <?php endif; ?>
                       </div>
                   </div>
               </div>

               <!-- Resumen Card -->
               <?php if (count($registros) > 0): ?>
               <div class="card mt-4">
                   <div class="card-header">
                       <h3><i class="fas fa-chart-pie"></i> Resumen</h3>
                   </div>
                   <div class="card-body">
                       <div class="row">
                           <div class="col-md-6">
                               <div class="card bg-primary" style="color: white;">
                                   <div class="card-body">
                                       <h4 class="mb-0">Total General</h4>
                                       <div class="d-flex align-items-center justify-content-between">
                                           <i class="fas fa-dollar-sign fa-3x"></i>
                                           <span style="font-size: 2rem; font-weight: 700;">$<?php echo number_format($granTotal, 2); ?></span>
                                       </div>
                                   </div>
                               </div>
                           </div>
                           <div class="col-md-6">
                               <div class="card bg-success" style="color: white;">
                                   <div class="card-body">
                                       <h4 class="mb-0">Días con Ventas</h4>
                                       <div class="d-flex align-items-center justify-content-between">
                                           <i class="fas fa-calendar-check fa-3x"></i>
                                           <span style="font-size: 2rem; font-weight: 700;"><?php echo count($registros); ?></span>
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

   <script>
   document.addEventListener('DOMContentLoaded', function() {
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
   </script>

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

