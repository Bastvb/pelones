<?php
session_start();
include('config.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recibimos los datos del formulario
    $codigo = $_POST['codigo'];
    $nombre = $_POST['nombre'];

    // Validar que el código tenga exactamente 4 dígitos
    if (!preg_match('/^[0-9]{4}$/', $codigo)) {
        echo "<div class='notification error'>El código debe ser numérico y contener exactamente 4 dígitos.</div>";
        exit;
    }

    // Convertir el código a entero para la columna 'codigo'
    $codigoInt = intval($codigo);
    // Para la columna 'id' (CHAR(4)), nos aseguramos de que tenga 4 caracteres, completando con ceros a la izquierda
    $id = str_pad($codigo, 4, "0", STR_PAD_LEFT);

    // Verificar que el código no exista ya en la columna 'codigo'
    $stmt = $conn->prepare("SELECT nombre FROM meseros WHERE codigo = ?");
    if (!$stmt) {
        die("Error en la preparación de la consulta: " . $conn->error);
    }
    $stmt->bind_param("i", $codigoInt);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->close();
        echo "<div class='notification error'>El código ya está en uso. Por favor, elige otro código.</div>";
        exit;
    }
    $stmt->close();

    // Insertar el nuevo mesero en la base de datos
    $stmt = $conn->prepare("INSERT INTO meseros (id, codigo, nombre) VALUES (?, ?, ?)");
    if (!$stmt) {
        die("Error en la preparación de la consulta: " . $conn->error);
    }
    // 's' para id (string), 'i' para codigo (entero) y 's' para nombre
    $stmt->bind_param("sis", $id, $codigoInt, $nombre);
    if ($stmt->execute()) {
        $_SESSION['mesero'] = $nombre;
        $stmt->close();
        $conn->close();
        header("Location: home.php");
        exit;
    } else {
        $stmt->close();
        echo "<div class='notification error'>Error al registrarse: " . $conn->error . "</div>";
    }
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
        <p><a href="register.php">Volver al registro</a></p>
    </div>
</body>
</html>


<!-- $stemt esta mierdita sirve como variable para mostrar -->
