<?php
session_start();

// Verificar si el usuario ya está logueado
if (isset($_SESSION['usuario_id'])) {
    // Si ya ingresó, lo enviamos a la página principal del sistema
    // (Asegúrate de tener este archivo creado, o cambia 'inicio.php' por tu archivo principal)
    header('Location: includes/header.php'); 
    exit;
} else {
    // Si NO ha ingresado, lo mandamos directo al login en la carpeta config
    header('Location: config/login.php');
    exit;
}
?>