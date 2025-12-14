<?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['usuario_id'])) {
        header("Location: /sistema_inventario/config/login.php"); 
        exit;
    }

    $nombre_usuario = $_SESSION['usuario_nombre'];
    $rol_id = $_SESSION['usuario_rol'];

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
        /* --- ESTILOS GENERALES --- */
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #887f7fff;
            overflow-x: hidden;
        }

        /* --- HEADER --- */
        header {
            background-color: #6a5a5aff;
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
            background-color: #27672cff;
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
            align-items: center; /* Alineación vertical */
        }

        .nav-btn {
            background-color: #27672cff;
            color: white;
            border: none;
            padding: 8px 16px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            border-radius: 4px;
            transition: background-color 0.3s ease;
            cursor: pointer;
            display: inline-block;
        }

        .nav-btn:hover {
            background-color: #1b5517ff;
        }

        /* --- ESTILOS PARA DROPDOWN (MENÚ DESPLEGABLE) --- */
        .dropdown {
            position: relative;
            display: inline-block;
        }

        /* Contenido del menú oculto por defecto */
        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #2b2b2b; /* Color oscuro sidebar */
            min-width: 140px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.4);
            z-index: 100;
            border-radius: 4px;
            border: 1px solid #555;
            top: 100%; /* Justo debajo del botón */
            left: 0;
        }

        /* Enlaces dentro del dropdown */
        .dropdown-content a {
            color: white;
            padding: 10px 16px;
            text-decoration: none;
            display: block;
            font-size: 13px;
            text-align: left;
            transition: background 0.2s;
        }

        /* Hover en las opciones del dropdown */
        .dropdown-content a:hover {
            background-color: #27672cff; /* Verde al pasar mouse */
        }

        /* Mostrar el menú al pasar el mouse sobre el contenedor .dropdown */
        .dropdown:hover .dropdown-content {
            display: block;
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
            background-color: #27672cff;
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
            background-color: #27672cff;
            border: none;
        }
        .btn-logout:hover {
            background-color: #27672cff;
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
        <p>Bienvenido al sistema GJ Inventory.</p>
    </div>

    <div class="sidebar-footer">
        <?php if ($tipo_usuario == 'administrador'): ?>
            <a href="header_personalmode.php" class="action-btn">
                <i class="fas fa-sync-alt"></i> Ir a Modo Personal
            </a>
        <?php endif; ?>
        
        <a href="/sistema_inventario/config/logout.php" class="action-btn btn-logout">
            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
        </a>
    </div>
</div>

<header>
    <div class="header-container">
        
        <div class="header-top">
            <h1 class="brand-title">GJ Inventory</h1>
            
            <div class="profile-trigger" onclick="toggleSidebar()" title="Ver Perfil">
                <i class="fas fa-user"></i> </div>
        </div>

        <nav class="nav-bar">
            <?php if ($tipo_usuario == 'administrador'): ?>
                
                <div class="dropdown">
                    <button class="nav-btn">Añadir <i class="fas fa-caret-down"></i></button>
                    <div class="dropdown-content">
                        <a href="/sistema_inventario/productos/anadir/nuevo.php">Nuevo</a>
                        <a href="/sistema_inventario/productos/anadir/reabastecer.php">Reabastecer</a>
                        <a href="/sistema_inventario/productos/anadir/marca.php">Marca</a>
                        <a href="/sistema_inventario/productos/anadir/modelo.php">Modelo</a>
                    </div>
                </div>

                <a href="/sistema_inventario/productos/buscar/localizar.php" class="nav-btn">Buscar</a>

                <div class="dropdown">
                    <button class="nav-btn">Eliminar <i class="fas fa-caret-down"></i></button>
                    <div class="dropdown-content">
                        <a href="/sistema_inventario/productos/eliminar/borrar.php">Borrar</a>
                        <a href="/sistema_inventario/productos/eliminar/reducir.php">Reducir</a>
                        <a href="/sistema_inventario/productos/eliminar/marca.php">Marca</a>
                        <a href="/sistema_inventario/productos/eliminar/modelo.php">Modelo</a>
                    </div>
                </div>

                   <div class="dropdown">
                    <button class="nav-btn">Editar <i class="fas fa-caret-down"></i></button>
                    <div class="dropdown-content">
                        <a href="/sistema_inventario/productos/editar/ubicacion.php">Ubicación</a>
                        <a href="/sistema_inventario/productos/editar/precio.php">Precio</a>
                    </div>
                </div>
               
                <a href="/sistema_inventario/productos/mostrar/inventario.php" class="nav-btn">Mostrar</a>
            
            <?php else: ?>
                <a href="/sistema_inventario/productos/buscar/localizar.php" class="nav-btn">Buscar</a>
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