<?php
// Archivo: config.php
// Este archivo prepara la conexión a la base de datos.
// Piensa en la base de datos como un almacén de información
// y en este archivo como la llave para entrar a ese almacén.

// 1) Definimos los datos necesarios para conectar:
// -----------------------------------------------
// Donde está el almacén (servidor):
$host = "localhost"; 
// Quién está entrando (usuario):
$usuario = "emydevco"; 
// Contraseña secreta para que te dejen pasar:
$password = "Cherry_may123-"; 
// Qué almacén específico quieres usar (base de datos):
$basededatos = "emydevco_restaurante"; 

// 2) Intentamos abrir la puerta y entrar al almacén:
// ------------------------------------------------
try {
    // Creamos un "PDO", que es el encargado de gestionar la conexión.
    // La cadena "mysql:host=...;dbname=...;charset=utf8" indica:
    // - mysql: tipo de base de datos
    // - host=...: dirección del servidor
    // - dbname=...: nombre de la base de datos
    // - charset=utf8: formato de caracteres (para letras con tildes, ñ, etc.)
    $pdo = new PDO(
        "mysql:host=$host;dbname=$basededatos;charset=utf8", 
        $usuario, 
        $password
    );

    // Le decimos a PDO que, si hay un error, nos avise con un mensaje:
    // Esto es útil mientras estamos desarrollando, para saber qué falla.
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    // Si algo falla al conectar, mostramos un aviso sencillo:
    echo "Error de conexión: " . $e->getMessage();
    // Y detenemos todo, porque sin conexión no podemos seguir:
    exit;
}

// 3) ¿Y ahora qué?
// ----------------
// En otros archivos PHP puedes usar esta variable `$pdo`
// para hacer consultas (leer, escribir, borrar datos).
// Basta con poner al inicio:
//     include 'config.php';
// y luego usar `$pdo` para hablar con la base de datos.

?>
