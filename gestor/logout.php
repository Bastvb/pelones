<?php
// =======================================================
// logout.php
// Este pequeño archivo cierra la sesión del usuario
// y lo envía de vuelta al formulario de login.
// =======================================================

session_start();
// 1) session_start() debe llamarse siempre que vayamos a
//    trabajar con la “sesión” de PHP. Si ya había datos
//    guardados (como mesero_id y mesero_nombre), ahora los
//    tenemos disponibles para eliminarlos.

session_destroy();
// 2) session_destroy() borra todos los datos de la sesión.
//    Con esto “olvidamos” que el mesero estaba logueado.

header("Location: login.php");
// 3) header("Location: ...") envía una cabecera HTTP al navegador
//    indicándole que cargue otra página—inmediatamente va a
//    login.php, donde el mesero podrá volver a ingresar su código.

exit;
// 4) exit detiene la ejecución del script. Así nos aseguramos
//    de que no salga nada de más después del redirect.
