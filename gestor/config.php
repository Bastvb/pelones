<?php
$host="192.185.131.135";
$user="emydevco";
$password="Cherry_may123-";
$database="emydevco_restaurante";

// crear la conexion
$conn = new mysqli($host,$user,$password,$database);

// verificamos la conexion brrr

if ($conn->connect_error){
    die("Conexion fallida: ".$conn->connect_error);
}

?>