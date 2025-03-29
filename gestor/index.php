<?php
session_start();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurante - Iniciar Sesión</title>
    <link rel="stylesheet" href="paqueseveabonito.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <div class="logo-text">RESTAURANTE</div>
        </div>
        
        <h2>Iniciar Sesión</h2>
        
        <form action="login_process.php" method="POST">
            <div class="form-group">
                <label for="codigo">Código de Mesero</label>
                <input type="text" name="codigo" id="codigo" maxlength="4" placeholder="Ingresa tu código de 4 dígitos" required>
            </div>
            
            <input type="submit" value="Iniciar Sesión">
        </form>
        
        <div class="form-footer">
            <p>¿No tienes cuenta? <a href="register.php">Regístrate aquí</a></p>
        </div>
    </div>
</body>
</html>