<?php
session_start();

// Redirigir si no hay sesión
if (!isset($_SESSION['mesero'])){
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurante - Panel de Mesero</title>
    <link rel="stylesheet" href="paqueseveabonito.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        /* Estilos adicionales para mantener el diseño original de home.php */
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo-text {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            color: var(--primary);
            letter-spacing: 2px;
        }
        
        .welcome-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .welcome-name {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .welcome-subtitle {
            color: var(--gray);
            font-size: 16px;
        }
        
        .menu-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .menu-card {
            background-color: var(--dark-gray);
            border-radius: var(--radius);
            padding: 25px;
            transition: var(--transition);
            cursor: pointer;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 200px;
            text-decoration: none;
            color: var(--light);
        }
        
        .menu-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow);
            background-color: var(--darker);
            border: 1px solid var(--primary);
        }
        
        .menu-card h3 {
            margin-bottom: 15px;
            font-size: 20px;
            color: var(--primary);
        }
        
        .menu-card p {
            color: var(--gray);
            margin-bottom: 20px;
        }
        
        .menu-card-icon {
            font-size: 40px;
            margin-bottom: 15px;
            color: var(--primary);
        }
        
        .logout-link {
            display: block;
            text-align: center;
            margin-top: 30px;
            color: var(--gray);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .logout-link:hover {
            color: var(--primary);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <div class="logo-text">RESTAURANTE</div>
        </div>
        
        <div class="welcome-header">
            <div class="welcome-name"><?php echo $_SESSION['mesero']; ?></div>
            <div class="welcome-subtitle">Panel de Mesero</div>
        </div>
        
        <div class="menu-cards">
            <a href="tables.php" class="menu-card">
                <div class="menu-card-icon">🍽️</div>
                <h3>Mesas y Órdenes</h3>
                <p>Gestiona las mesas del restaurante y visualiza las órdenes activas.</p>
            </a>
            
            <a href="menu.php" class="menu-card">
                <div class="menu-card-icon">📋</div>
                <h3>Menú y Pedidos</h3>
                <p>Crea nuevos pedidos y gestiona los platillos disponibles.</p>
            </a>
        </div>
        
        <a href="logout.php" class="logout-link">Cerrar Sesión</a>
    </div>
</body>
</html>