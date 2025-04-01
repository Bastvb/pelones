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
   <title>Login - Sistema de Restaurante</title>
   <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   <link rel="stylesheet" href="modern-styles.css">
   <style>
      body {
         background-color: var(--body-bg);
         min-height: 100vh;
         display: flex;
         align-items: center;
         justify-content: center;
      }
      
      .login-wrapper {
         width: 100%;
         max-width: 420px;
         padding: 20px;
      }
      
      .login-container {
         background-color: var(--card-bg);
         border-radius: var(--card-radius);
         box-shadow: var(--card-shadow);
         overflow: hidden;
         animation: fadeIn 0.5s ease-in-out;
      }
      
      .login-header {
         background-color: var(--primary);
         padding: 2rem;
         text-align: center;
         color: white;
      }
      
      .login-header i {
         font-size: 3rem;
         margin-bottom: 1rem;
      }
      
      .login-header h1 {
         margin: 0;
         color: white;
         font-size: 1.75rem;
      }
      
      .login-body {
         padding: 2rem;
      }
      
      .login-form .form-group {
         margin-bottom: 1.5rem;
      }
      
      .login-form .form-label {
         display: block;
         margin-bottom: 0.5rem;
         font-weight: 500;
      }
      
      .login-form .form-control {
         display: block;
         width: 100%;
         padding: 0.75rem 1rem;
         border: 1px solid var(--gray-light);
         border-radius: var(--input-radius);
         font-size: 1.25rem;
         text-align: center;
         letter-spacing: 2px;
      }
      
      .login-form button {
         width: 100%;
         padding: 1rem;
         font-size: 1.1rem;
         margin-top: 1rem;
      }
      
      .alert {
         margin-bottom: 1.5rem;
      }
      
      .form-footer {
         text-align: center;
         margin-top: 1.5rem;
         color: var(--gray);
      }
      
      .toggle-theme {
         position: absolute;
         top: 1rem;
         right: 1rem;
         background: none;
         border: none;
         color: var(--gray);
         cursor: pointer;
         font-size: 1.25rem;
      }
      
      @media (max-width: 480px) {
         .login-header {
            padding: 1.5rem;
         }
         
         .login-body {
            padding: 1.5rem;
         }
      }
   </style>
</head>
<body>
   <button class="toggle-theme" id="darkModeToggle">
      <i class="fas fa-moon"></i>
   </button>
   
   <div class="login-wrapper">
      <div class="login-container">
         <div class="login-header">
            <i class="fas fa-utensils"></i>
            <h1>Sistema de Restaurante</h1>
         </div>
         
         <div class="login-body">
            <?php if (!empty($error)): ?>
               <div class="alert alert-danger">
                  <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
               </div>
            <?php endif; ?>
            
            <form method="POST" action="login.php" class="login-form">
               <div class="form-group">
                  <label for="codigo" class="form-label">Código de Mesero</label>
                  <input 
                     type="number" 
                     name="codigo" 
                     id="codigo" 
                     class="form-control" 
                     required 
                     placeholder="Ingresa el código de 4 dígitos" 
                     min="0" 
                     max="9999"
                     autocomplete="off"
                  >
               </div>
               <button type="submit" class="btn btn-primary btn-icon">
                  <i class="fas fa-sign-in-alt"></i> Ingresar
               </button>
            </form>
            
            <div class="form-footer">
               <p>Ingresa tu código de mesero para acceder al sistema</p>
            </div>
         </div>
      </div>
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

