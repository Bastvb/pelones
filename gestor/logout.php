<?php
session_start();
session_destroy();
header("Locate: index.php");
exit;
?>