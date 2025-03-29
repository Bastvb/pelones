<?php
session_start();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurante - Registro</title>
    <link rel="stylesheet" href="paqueseveabonito.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="logo">
            <div class="logo-text">RESTAURANTE</div>
        </div>
        
        <h2>Registro de Mesero</h2>
        
        <form action="register_process.php" method="POST">
            <div class="form-group">
                <label for="codigo">Código de Mesero</label>
                <input type="text" name="codigo" id="codigo" maxlength="4" placeholder="Ingresa un código de 4 dígitos" required>
            </div>
            
            <div class="form-group">
                <label for="nombre">Nombre Completo</label>
                <input type="text" name="nombre" id="nombre" placeholder="Ingresa tu nombre completo" required>
            </div>
            
            <input type="submit" value="Registrarse">
        </form>
        
        <div class="form-footer">
            <p>¿Ya tienes cuenta? <a href="index.php">Inicia sesión aquí</a></p>
        </div>
    </div>
</body>
</html>