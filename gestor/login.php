<?php
// =======================================================
// 1) INICIAR SESIÓN Y CONEXIÓN A LA BASE DE DATOS
// =======================================================

// session_start() arranca la “sesión” en PHP, 
// que nos permite recordar al usuario mientras navega.
session_start();

// require_once incluye config.php UNA VEZ,
// donde está la conexión a la base de datos ($pdo).
require_once "config.php";

// =======================================================
// 2) PROCESAR ENVÍO DEL FORMULARIO (POST)
// =======================================================
// Aquí detectamos si el formulario envi ó datos (método POST),
// y buscamos en la tabla 'meseros' un código que coincida.

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Tomamos el código ingresado por el usuario (o cadena vacía)
    $codigo = $_POST["codigo"] ?? "";

    // Preparamos consulta SQL para buscar mesero por código
    $sql  = "SELECT * FROM meseros WHERE codigo = :codigo LIMIT 1";
    $stmt = $pdo->prepare($sql);
    // Vinculamos el parámetro :codigo como entero
    $stmt->bindParam(":codigo", $codigo, PDO::PARAM_INT);
    $stmt->execute();
    // Ejecutamos y traemos el primer resultado
    $mesero = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($mesero) {
        // Si encontramos al mesero, guardamos datos en la sesión
        $_SESSION["mesero_id"]     = $mesero["id"];
        $_SESSION["mesero_nombre"] = $mesero["nombre"];
        // Redirigimos a home.php (página principal)
        header("Location: home.php");
        exit;
    } else {
        // Si no coincide, preparamos un mensaje de error
        $error = "Código incorrecto o mesero no encontrado.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <!-- ================================================
         3) CABECERA HTML (HEAD)
         ================================================
         - charset UTF-8 para acentos y ñ
         - viewport para que se adapte a móviles
         - título de pestaña
         - enlaces a Google Fonts, Font Awesome y tu CSS
    -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Restaurante - Login</title>
    <!-- Fuente Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Tu CSS -->
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="app-container">
        <!-- =================================================
             4) HEADER: TÍTULO DE LA APLICACIÓN Y MODO OSCURO
             ================================================= -->
        <header class="app-header">
            <div class="container">
                <div class="header-content">
                    <h1 class="app-title">
                        <i class="fas fa-utensils"></i> Sistema de Restaurante
                    </h1>
                    <div class="user-info">
                        <!-- Botón para cambiar tema claro/oscuro -->
                        <button class="dark-mode-toggle" id="darkModeToggle">
                            <i class="fas fa-moon"></i>
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- =================================================
             5) CONTENIDO PRINCIPAL: FORMULARIO DE LOGIN
             ================================================= -->
        <main class="app-content">
            <div class="container">
                <div class="row">
                    <!-- Centrar la tarjeta de login -->
                    <div class="col-12" style="max-width:500px; margin:0 auto;">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="text-center">
                                    <i class="fas fa-user-lock"></i> Iniciar Sesión
                                </h2>
                            </div>
                            <div class="card-body">
                                <!-- Si hay error, mostrar alerta -->
                                <?php if (!empty($error)): ?>
                                    <div class="alert alert-danger">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <?php echo htmlspecialchars($error, ENT_QUOTES); ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Formulario de login -->
                                <form method="POST" action="login.php">
                                    <div class="form-group">
                                        <label for="codigo" class="form-label">
                                            Código de Mesero (4 dígitos)
                                        </label>
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
                                <p class="mb-0 text-muted">
                                    Ingresa tu código de acceso para continuar
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- =================================================
         6) MODAL DE ERROR (JS) — Opcional extra
         ================================================= -->
    <div class="modal" id="loginErrorModal" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Error de inicio de sesión</h3>
                <span class="close-modal" onclick="closeModal('loginErrorModal')">&times;</span>
            </div>
            <div class="modal-body">
                <p id="loginErrorMessage">Código incorrecto o mesero no encontrado.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="closeModal('loginErrorModal')">
                    Entendido
                </button>
            </div>
        </div>
    </div>

    <!-- =================================================
         7) JavaScript: Dark Mode, focus y modal de error
         ================================================= -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Dark mode toggle ---
        const darkModeToggle = document.getElementById('darkModeToggle');
        const body = document.body;
        // Cargar preferencia
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            body.classList.add('dark-mode');
            darkModeToggle.innerHTML = '<i class="fas fa-sun"></i>';
        }
        // Cambiar tema al hacer clic
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

        // --- Poner foco en el campo código ---
        document.getElementById('codigo').focus();

        // --- Si hubo error en PHP, mostrar modal con mensaje ---
        <?php if (!empty($error)): ?>
        showLoginErrorModal("<?php echo addslashes($error); ?>");
        <?php endif; ?>
    });

    // Función para mostrar el modal de error
    function showLoginErrorModal(message) {
        document.getElementById('loginErrorMessage').textContent = message;
        document.getElementById('loginErrorModal').style.display = 'flex';
    }

    // Función para cerrar cualquier modal
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // Cerrar modal al hacer clic fuera de él
    window.onclick = function(event) {
        document.querySelectorAll('.modal').forEach(modal => {
            if (event.target === modal) {
                modal.style.display = "none";
            }
        });
    }
    </script>
</body>
</html>



