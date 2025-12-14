<?php
// 1. Conexión y Sesión
require_once '../config/db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$mensaje = "";
$tipo_mensaje = "";

// 2. Procesar el Formulario al Confirmar
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $id_usuario_editar = $_POST['id_usuario'] ?? '';
    $check_nombre = isset($_POST['check_nombre']); // ¿Se marcó editar nombre?
    $check_clave = isset($_POST['check_clave']);   // ¿Se marcó editar clave?
    
    $nuevo_nombre = trim($_POST['nuevo_nombre']);
    $nueva_clave = trim($_POST['nueva_clave']);

    if (empty($id_usuario_editar)) {
        $mensaje = "Por favor seleccione un usuario.";
        $tipo_mensaje = "error";
    } elseif (!$check_nombre && !$check_clave) {
        $mensaje = "Debe seleccionar al menos una opción para editar.";
        $tipo_mensaje = "error";
    } else {
        try {
            // CASO 1: AMBOS (Nombre y Clave)
            if ($check_nombre && $check_clave) {
                if (empty($nuevo_nombre) || empty($nueva_clave)) {
                    $mensaje = "Para editar ambos, complete los dos campos.";
                    $tipo_mensaje = "error";
                } else {
                    $clave_hash = sha1($nueva_clave); // SHA1
                    $sql = "UPDATE usuarios SET nombre_usuario = ?, clave_usuario = ? WHERE id_usuario = ?";
                    $stmt = $pdo->prepare($sql);
                    if ($stmt->execute([$nuevo_nombre, $clave_hash, $id_usuario_editar])) {
                        $mensaje = "Usuarios actualizado exitosamente.";
                        $tipo_mensaje = "exito";
                    }
                }
            } 
            // CASO 2: SOLO NOMBRE
            elseif ($check_nombre) {
                if (empty($nuevo_nombre)) {
                    $mensaje = "El nombre de usuario no puede estar vacío.";
                    $tipo_mensaje = "error";
                } else {
                    $sql = "UPDATE usuarios SET nombre_usuario = ? WHERE id_usuario = ?";
                    $stmt = $pdo->prepare($sql);
                    if ($stmt->execute([$nuevo_nombre, $id_usuario_editar])) {
                        $mensaje = "Nombre de usuario cambiado con éxito.";
                        $tipo_mensaje = "exito";
                    }
                }
            } 
            // CASO 3: SOLO CLAVE
            elseif ($check_clave) {
                if (empty($nueva_clave)) {
                    $mensaje = "La nueva clave no puede estar vacía.";
                    $tipo_mensaje = "error";
                } else {
                    $clave_hash = sha1($nueva_clave); // SHA1
                    $sql = "UPDATE usuarios SET clave_usuario = ? WHERE id_usuario = ?";
                    $stmt = $pdo->prepare($sql);
                    if ($stmt->execute([$clave_hash, $id_usuario_editar])) {
                        $mensaje = "Clave de usuario cambiada con éxito.";
                        $tipo_mensaje = "exito";
                    }
                }
            }
        } catch (PDOException $e) {
            $mensaje = "Error: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// 3. Obtener Usuarios para el Dropdown (Separados por Rol)
// 1 = Admin, 2 = Empleado
$sqlAdmins = "SELECT * FROM usuarios WHERE id_rol = 1 ORDER BY nombre_usuario ASC";
$admins = $pdo->query($sqlAdmins)->fetchAll(PDO::FETCH_ASSOC);

$sqlEmps = "SELECT * FROM usuarios WHERE id_rol = 2 ORDER BY nombre_usuario ASC";
$empleados = $pdo->query($sqlEmps)->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header_personalmode.php';
?>

<style>
    .form-container {
        max-width: 600px;
        margin: 40px auto;
        background-color: #f9f9f9;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        color: #333;
        position: relative; z-index: 1;
    }
    .form-title {
        text-align: center; color: #27672c; 
        margin-bottom: 20px; border-bottom: 2px solid #ddd; padding-bottom: 10px;
    }
    
    /* Checkboxes de Selección */
    .selection-area {
        display: flex; justify-content: space-around;
        background-color: #e9ecef; padding: 15px;
        border-radius: 5px; margin-bottom: 25px; border: 1px solid #ccc;
    }
    .checkbox-wrapper { display: flex; align-items: center; gap: 8px; font-weight: bold; cursor: pointer; }
    input[type="checkbox"] { transform: scale(1.2); cursor: pointer; accent-color: #27672c; }

    /* Campos del formulario */
    .form-group { margin-bottom: 20px; }
    label { display: block; margin-bottom: 8px; font-weight: bold; color: #555; }
    .form-control {
        width: 100%; padding: 10px; border: 1px solid #ccc;
        border-radius: 4px; box-sizing: border-box; font-size: 16px;
    }
    .form-control:focus { border-color: #27672c; outline: none; }
    
    /* Inputs de Solo Lectura (Gris) */
    .input-readonly { background-color: #e2e2e2; color: #666; font-family: monospace; cursor: not-allowed; }

    /* Secciones ocultas por defecto */
    #section-nombre, #section-clave { display: none; }
    
    /* Animación suave al aparecer */
    .fade-in { animation: fadeIn 0.5s; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    /* Botones */
    .btn-actions { display: flex; justify-content: space-between; margin-top: 30px; }
    .btn { padding: 10px 25px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold; text-decoration: none; color: white; display: inline-flex; align-items: center; gap: 8px; }
    .btn-confirm { background-color: #27672c; }
    .btn-confirm:hover { background-color: #1b5517; }
    .btn-cancel { background-color: #6a5a5a; }
    .btn-cancel:hover { background-color: #4e4242; }

    /* Alertas */
    .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; text-align: center; }
    .alert-exito { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
</style>

<div class="form-container">
    <h2 class="form-title"><i class="fas fa-user-edit"></i> Editar Usuario</h2>

    <?php if (!empty($mensaje)): ?>
        <div class="alert <?php echo ($tipo_mensaje == 'exito') ? 'alert-exito' : 'alert-error'; ?>">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        
        <div class="form-group">
            <label for="id_usuario">Seleccione Usuario a Editar:</label>
            <select name="id_usuario" id="id_usuario" class="form-control" required onchange="cargarDatosUsuario()">
                <option value="">-- Seleccionar --</option>
                
                <optgroup label="Administradores">
                    <?php foreach($admins as $a): ?>
                        <option value="<?= $a['id_usuario'] ?>" 
                                data-nombre="<?= htmlspecialchars($a['nombre_usuario']) ?>" 
                                data-clave="<?= $a['clave_usuario'] ?>">
                            <?= htmlspecialchars($a['nombre_usuario']) ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>

                <optgroup label="Empleados">
                    <?php foreach($empleados as $e): ?>
                        <option value="<?= $e['id_usuario'] ?>" 
                                data-nombre="<?= htmlspecialchars($e['nombre_usuario']) ?>" 
                                data-clave="<?= $e['clave_usuario'] ?>">
                            <?= htmlspecialchars($e['nombre_usuario']) ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            </select>
        </div>

        <div class="selection-area">
            <label class="checkbox-wrapper">
                <input type="checkbox" name="check_nombre" id="check_nombre" onchange="toggleSections()">
                Editar Nombre de Usuario
            </label>
            <label class="checkbox-wrapper">
                <input type="checkbox" name="check_clave" id="check_clave" onchange="toggleSections()">
                Editar Clave de Usuario
            </label>
        </div>

        <div id="section-nombre" class="fade-in">
            <div class="form-group">
                <label>Nombre Actual:</label>
                <input type="text" id="nombre_actual_display" class="form-control input-readonly" readonly>
            </div>
            <div class="form-group">
                <label for="nuevo_nombre" style="color:#27672c;">Nuevo Nombre de Usuario:</label>
                <input type="text" name="nuevo_nombre" id="nuevo_nombre" class="form-control" autocomplete="off" placeholder="Escriba el nuevo nombre">
            </div>
            <hr>
        </div>

        <div id="section-clave" class="fade-in">
            <div class="form-group">
                <label>Clave Actual:</label>
                <input type="text" id="clave_actual_display" class="form-control input-readonly" readonly>
                
            </div>
            <div class="form-group">
                <label for="nueva_clave" style="color:#27672c;">Nueva Clave de Usuario:</label>
                <input type="password" name="nueva_clave" id="nueva_clave" class="form-control" placeholder="Escriba la nueva clave">
            </div>
        </div>

        <div class="btn-actions">
            <a href="../includes/header_personalmode.php" class="btn btn-cancel">Cancelar</a>
            <button type="submit" class="btn btn-confirm">Confirmar Cambios <i class="fas fa-check"></i></button>
        </div>

    </form>
</div>

<script>
    // Función para mostrar/ocultar campos según checkboxes
    function toggleSections() {
        const chkNombre = document.getElementById('check_nombre');
        const chkClave = document.getElementById('check_clave');
        const secNombre = document.getElementById('section-nombre');
        const secClave = document.getElementById('section-clave');

        if (chkNombre.checked) {
            secNombre.style.display = 'block';
        } else {
            secNombre.style.display = 'none';
        }

        if (chkClave.checked) {
            secClave.style.display = 'block';
        } else {
            secClave.style.display = 'none';
        }
    }

    // Función para "Autocompletar" los datos actuales cuando eliges del dropdown
    function cargarDatosUsuario() {
        const select = document.getElementById('id_usuario');
        // Obtener la opción seleccionada
        const opcion = select.options[select.selectedIndex];
        
        // Obtener datos guardados en los atributos data-nombre y data-clave
        const nombre = opcion.getAttribute('data-nombre');
        const clave = opcion.getAttribute('data-clave');

        // Llenar los campos de "Actual"
        if(nombre) {
            document.getElementById('nombre_actual_display').value = nombre;
            // Opcional: poner el nombre actual en el input de nuevo nombre para editar fácil
            // document.getElementById('nuevo_nombre').value = nombre; 
        } else {
            document.getElementById('nombre_actual_display').value = '';
        }

        if(clave) {
            document.getElementById('clave_actual_display').value = clave;
        } else {
            document.getElementById('clave_actual_display').value = '';
        }
    }
</script>

<script>
    // Evitar reenvío de formulario
    if ( window.history.replaceState ) {
        window.history.replaceState( null, null, window.location.href );
    }
</script>

</body>
</html>