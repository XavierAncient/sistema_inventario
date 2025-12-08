<?php
session_start();
session_unset();
session_destroy();

// Redirige al login que está en la misma carpeta 'config'
header('Location: login.php');
exit;
?>