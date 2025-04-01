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
                        <button class="dark-mode-toggle" id="darkModeToggle">
                            <i class="fas fa-moon"></i>
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <main class="app-content">
            <div class="container">
                <div class="row">
                    <div class="col-12" style="max-width: 500px; margin: 0 auto;">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="text-center"><i class="fas fa-user-lock"></i> Iniciar Sesión</h2>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($error)): ?>
                                    <div class="alert alert-danger">
                                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" action="login.php">
                                    <div class="form-group">
                                        <label for="codigo" class="form-label">Código de Mesero (4 dígitos)</label>
                                        <input 
                                            type="number" 
                                            name="codigo" 
                                            id="codigo" 
                                            class="form-control" 
                                            required 
                                            placeholder="Ingresa tu código" 
                                            min="0" 
                                            max="9999"
                                            autocomplete="off"
                                        >
                                    </div>
                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary w-100 btn-icon">
                                            <i class="fas fa-sign-in-alt"></i> Ingresar al Sistema
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <div class="card-footer text-center">
                                <p class="mb-0 text-muted">Ingresa tu código de acceso para continuar</p>
                            </div>
                        </div>
                    </div>
                </div>
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

        // Focus the input field
        document.getElementById('codigo').focus();
    });
    </script>
</body>
</html>


