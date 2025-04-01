<?php
// login.php
session_start();
require_once "config.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $codigo = $_POST["codigo"] ?? "";

    // Preparamos consulta para verificar mesero con ese código
    $sql = "SELECT * FROM meseros WHERE codigo = :codigo LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(":codigo", $codigo, PDO::PARAM_INT);
    $stmt->execute();
    $mesero = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($mesero) {
        // Se encontró el mesero; iniciamos sesión
        $_SESSION["mesero_id"] = $mesero["id"];
        $_SESSION["mesero_nombre"] = $mesero["nombre"];

        // Redirigimos a la página principal (home)
        header("Location: home.php");
        exit;
    } else {
        $error = "Código incorrecto o mesero no encontrado.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login - Restaurante</title>
    <!-- Aquí puedes incluir Bootstrap u otro framework CSS si lo deseas -->
</head>
<body>
    <div class="container">
        <h1>Iniciar Sesión (Meseros)</h1>
        <?php if (!empty($error)): ?>
            <div style="color: red;"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <label for="codigo">Código (4 dígitos):</label>
            <input type="number" name="codigo" id="codigo" required placeholder="0000" 
                   style="width:100px;" min="0" max="9999">
            <button type="submit">Ingresar</button>
        </form>
    </div>
</body>
</html>
