<?php
// 1. Conexión a la Base de Datos (Ruta corregida: subimos un nivel)
require_once '../config/db.php';

$mensaje = "";
$tipo_mensaje = ""; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario_nuevo = trim($_POST['nombre_usuario']);
    $clave_plana = trim($_POST['clave_acceso']);
    $rol_seleccionado = $_POST['rol'];

    if (empty($usuario_nuevo) || empty($clave_plana)) {
        $mensaje = "Por favor complete todos los campos.";
        $tipo_mensaje = "error";
    } else {
        try {
            // Verificar si el usuario ya existe
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE nombre_usuario = ?");
            $stmtCheck->execute([$usuario_nuevo]);
            
            if ($stmtCheck->fetchColumn() > 0) {
                $mensaje = "El nombre de usuario ya está registrado.";
                $tipo_mensaje = "error";
            } else {
                // --- CORRECCIÓN DE ENCRIPTACIÓN ---
                // Tu sistema usa SHA1 (40 caracteres), no password_hash.
                $clave_hash = sha1($clave_plana);
                
                // Usamos 'clave_usuario' según tu base de datos
                $sql = "INSERT INTO usuarios (nombre_usuario, clave_usuario, id_rol) VALUES (?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                
                if ($stmt->execute([$usuario_nuevo, $clave_hash, $rol_seleccionado])) {
                    $mensaje = "Nuevo empleado registrado exitosamente.";
                    $tipo_mensaje = "exito";
                    // Limpiamos la variable POST para que no se repobla el campo
                    $_POST['nombre_usuario'] = '';
                } else {
                    $mensaje = "Error al registrar en la base de datos.";
                    $tipo_mensaje = "error";
                }
            }
        } catch (PDOException $e) {
            $mensaje = "Error de sistema: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// 2. Incluimos el Header (Ruta correcta a includes)
include '../includes/header_personalmode.php';
?>

<style>
    /* Estilos idénticos a los anteriores */
    .form-container {
        max-width: 500px;
        margin: 50px auto;
        background-color: #f9f9f9; 
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        color: #333;
        position: relative; 
        z-index: 1;
    }

    .form-title {
        text-align: center;
        color: #27672c; 
        margin-bottom: 25px;
        border-bottom: 2px solid #ddd;
        padding-bottom: 10px;
    }

    .form-group { margin-bottom: 20px; }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: bold;
        color: #555;
    }

    .form-control {
        width: 100%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box; 
        font-size: 16px;
    }

    .form-control:focus {
        border-color: #27672c;
        outline: none;
    }

    .btn-actions {
        display: flex;
        justify-content: space-between;
        margin-top: 30px;
    }

    .btn {
        padding: 10px 25px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
        font-weight: bold;
        text-decoration: none;
        text-align: center;
        transition: 0.3s;
        display: inline-block;
    }

    .btn-submit { background-color: #27672c; color: white; }
    .btn-submit:hover { background-color: #1b5517; }

    .btn-cancel { background-color: #6a5a5a; color: white; }
    .btn-cancel:hover { background-color: #4e4242; }

    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 4px;
        text-align: center;
    }
    .alert-exito { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
</style>

<div class="form-container">
    <h2 class="form-title"><i class="fas fa-user-plus"></i> Añadir Nuevo Empleado</h2>

    <?php if (!empty($mensaje)): ?>
        <div class="alert <?php echo ($tipo_mensaje == 'exito') ? 'alert-exito' : 'alert-error'; ?>">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        
        <div class="form-group">
            <label for="usuario">Nombre de Usuario:</label>
            <input type="text" id="usuario" name="nombre_usuario" class="form-control" 
                   value="<?php echo ($tipo_mensaje != 'exito' && isset($_POST['nombre_usuario'])) ? htmlspecialchars($_POST['nombre_usuario']) : ''; ?>" 
                   required autocomplete="off">
        </div>

        <div class="form-group">
            <label for="clave">Clave de Acceso:</label>
            <input type="password" id="clave" name="clave_acceso" class="form-control" required autocomplete="new-password">
        </div>

        <div class="form-group">
            <label for="rol">Asignar Rol:</label>
            <select id="rol" name="rol" class="form-control" required>
                <option value="2">Empleado</option>
                <option value="1">Administrador</option>
            </select>
        </div>

        <div class="btn-actions">
            <a href="../includes/header_personalmode.php" class="btn btn-cancel">Cancelar</a>
            
            <button type="submit" class="btn btn-submit">
                Registrar <i class="fas fa-save"></i>
            </button>
        </div>

    </form>
</div>

<script>
    if ( window.history.replaceState ) {
        window.history.replaceState( null, null, window.location.href );
    }
</script>

</body>
</html>