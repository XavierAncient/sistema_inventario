<?php
    // --- LÓGICA DE SESIÓN (OBLIGATORIO AL INICIO) ---
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // SEGURIDAD: Si no hay usuario logueado, redirigir al Login
    if (!isset($_SESSION['usuario_id'])) {
        header("Location: config/login.php"); 
        exit;
    }

    // OBTENER DATOS DE LA SESIÓN REAL
    $nombre_usuario = $_SESSION['usuario_nombre'];
    $rol_id = $_SESSION['usuario_rol'];

    // Convertir el ID del rol (de la BD) al texto que usa tu diseño
    // En tu SQL: 1 = Administrador, 2 = Empleado
    if ($rol_id == 1) {
        $tipo_usuario = 'administrador';
    } else {
        $tipo_usuario = 'empleado';
    }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        /* --- ESTILOS GENERALES (Coincidentes con Modo Principal) --- */
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #887f7fff; /* Fondo Gris Rojizo */
            overflow-x: hidden;
        }

        /* --- HEADER --- */
        header {
            background-color: #6a5a5aff; /* Header Gris Oscuro */
            padding: 15px 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            border-bottom: 2px solid #3e3c3cff;
            position: relative;
            z-index: 10;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .brand-title {
            color: #ffffff;
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }

        /* --- BOTÓN DE PERFIL (CÍRCULO) --- */
        .profile-trigger {
            width: 40px;
            height: 40px;
            background-color: #27672cff; /* Verde Principal */
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            color: white;
            transition: transform 0.2s, background-color 0.3s;
            border: 2px solid #fff;
        }

        .profile-trigger:hover {
            background-color: #27672cff;
            transform: scale(1.1);
        }

        /* --- BARRA DE NAVEGACIÓN --- */
        .nav-bar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .nav-btn {
            background-color: #27672cff; /* Verde Principal */
            color: white;
            border: none;
            padding: 8px 16px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            border-radius: 4px;
            transition: background-color 0.3s ease;
            cursor: pointer;
        }

        .nav-btn:hover {
            background-color: #1b5517ff;
        }

        /* --- SIDEBAR (BARRA LATERAL) --- */
        .sidebar {
            height: 100%;
            width: 300px;
            position: fixed;
            z-index: 1000;
            top: 0;
            right: -320px;
            background-color: #2b2b2b;
            color: white;
            box-shadow: -2px 0 5px rgba(0,0,0,0.5);
            transition: right 0.3s ease;
            display: flex;
            flex-direction: column;
            padding: 20px;
            box-sizing: border-box;
        }

        .sidebar.active {
            right: 0;
        }

        /* Contenido del Sidebar */
        .sidebar-header {
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid #555;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }

        .sidebar-avatar-large {
            width: 60px;
            height: 60px;
            background-color: #27672cff; /* Verde Principal */
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 24px;
            color: white;
        }

        .user-info h3 {
            margin: 0;
            font-size: 18px;
            color: #fff;
        }

        .user-info p {
            margin: 5px 0 0 0;
            font-size: 14px;
            color: #ccc;
            font-style: italic;
        }

        /* Botón cerrar (X) */
        .close-btn {
            position: absolute;
            top: 15px;
            right: 20px;
            background: none;
            border: none;
            color: #aaa;
            font-size: 24px;
            cursor: pointer;
        }
        .close-btn:hover { color: white; }

        /* Botones inferiores del sidebar */
        .sidebar-footer {
            margin-top: auto;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .action-btn {
            padding: 12px;
            border: 1px solid #555;
            background-color: transparent;
            color: white;
            cursor: pointer;
            border-radius: 5px;
            text-align: center;
            transition: 0.3s;
            display: block;
            text-decoration: none;
        }

        .action-btn:hover {
            background-color: #444;
        }

        .btn-logout {
            background-color: #27672cff; /* Verde Principal */
            border: none;
        }
        .btn-logout:hover {
            background-color: #1b5517ff;
        }

        /* Overlay oscuro */
        .overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 900;
            display: none;
        }
        .overlay.active { display: block; }

    </style>
</head>
<body>

<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

<div class="sidebar" id="sidebar">
    <button class="close-btn" onclick="toggleSidebar()">&times;</button>
    
    <div class="sidebar-header">
        <div class="sidebar-avatar-large">
            <?php echo substr($nombre_usuario, 0, 1); ?>
        </div>
        <div class="user-info">
            <h3><?php echo $nombre_usuario; ?></h3>
            <p>Rol: <?php echo ucfirst($tipo_usuario); ?></p>
        </div>
    </div>

    <div style="color: #ccc; font-size: 14px;">
        <p>Estás en: <strong>Gestión de Personal</strong></p>
    </div>

    <div class="sidebar-footer">
        <a href="header.php" class="action-btn">
            <i class="fas fa-sync-alt"></i> Ir a Modo Productos
        </a>
        
        <a href="/sistema_inventario/config/logout.php" class="action-btn btn-logout">
            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
        </a>
    </div>
</div>

<header>
    <div class="header-container">
        
        <div class="header-top">
            <h1 class="brand-title">GJ Inventory Personal</h1>
            
            <div class="profile-trigger" onclick="toggleSidebar()" title="Ver Perfil">
                <i class="fas fa-user"></i> </div>
        </div>

        <nav class="nav-bar">
            <?php if ($tipo_usuario == 'administrador'): ?>
                <a href="/sistema_inventario/personal/anadirpersonal.php" class="nav-btn">Añadir</a>
                <a href="/sistema_inventario/personal/eliminarpersonal.php" class="nav-btn">Eliminar</a>
                <a href="/sistema_inventario/personal/editarpersonal.php" class="nav-btn">Editar</a>
                <a href="/sistema_inventario/personal/mostrarpersonal.php" class="nav-btn">Mostrar</a>
            <?php endif; ?>
        </nav>

    </div>
</header>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    }
</script>

</body>
</html>