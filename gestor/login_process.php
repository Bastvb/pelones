<?php
session_start();
include('config.php');

if ($_SERVER["REQUEST_METHOD"] == "POST"){
    $codigo = $_POST['codigo'];

    // Validar que el código tenga 4 dígitos
    if (!preg_match('/^[0-9]{4}$/',$codigo)) {
        echo "<div class='notification error'>El código debe ser numérico y contener exactamente 4 dígitos.</div>";
        exit;
    }

    // Evitar inyecciones SQL
    $stmt = $conn->prepare("SELECT nombre FROM meseros WHERE codigo = ?");
    if (!$stmt){
        die("Error en la preparación de la consulta: ". $conn->error);
    }

    $stmt->bind_param("i", $codigo);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0){
        // Si se encontró el mesero, iniciar sesión
        $stmt->bind_result($nombre);
        $stmt->fetch();
        $_SESSION['mesero'] = $nombre;
        $stmt->close();
        
        // Redirigir al panel de mesero
        header("Location: home.php");
        exit;
    } else {
        echo "<div class='notification error'>Código incorrecto o mesero no registrado.</div>";
    }

    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurante - Error</title>
    <link rel="stylesheet" href="paqueseveabonito.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="logo">
            <div class="logo-text">RESTAURANTE</div>
        </div>
        
        <p>Redirigiendo...</p>
        <p><a href="index.php">Volver al inicio</a></p>
    </div>
</body>
</html>


<!--
UPDATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
); -->