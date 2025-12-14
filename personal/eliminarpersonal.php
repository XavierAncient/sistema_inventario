<?php
// 1. Conexión a Base de Datos
require_once '../config/db.php';

// Iniciar sesión si no está iniciada (por si acaso, aunque el header lo hace)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$mensaje = "";
$tipo_mensaje = "";

// 2. Lógica de Eliminación
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['eliminar_btn'])) {
    
    // Verificamos si se marcaron casillas
    if (!empty($_POST['usuarios_ids'])) {
        $ids_a_borrar = $_POST['usuarios_ids'];
        $cantidad = count($ids_a_borrar);
        
        // Convertimos el array de IDs en una cadena separada por comas para la consulta SQL
        // Ejemplo: 1,3,5
        // Nota: Es seguro hacerlo así porque forzamos que sean enteros, o usamos marcadores '?' dinámicos.
        // Usaremos marcadores dinámicos (?) para máxima seguridad.
        
        $placeholders = implode(',', array_fill(0, $cantidad, '?'));
        
        try {
            $sql = "DELETE FROM usuarios WHERE id_usuario IN ($placeholders)";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute($ids_a_borrar)) {
                $tipo_mensaje = "exito";
                if ($cantidad == 1) {
                    $mensaje = "Empleado eliminado exitosamente.";
                } else {
                    $mensaje = "Empleados eliminados exitosamente.";
                }
            } else {
                $mensaje = "Error al intentar eliminar los registros.";
                $tipo_mensaje = "error";
            }
        } catch (PDOException $e) {
            $mensaje = "Error de sistema: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    } else {
        $mensaje = "No seleccionaste ningún empleado para eliminar.";
        $tipo_mensaje = "error";
    }
}

// 3. Obtener lista de usuarios para mostrar en la tabla
// Excluimos al usuario que está logueado actualmente para evitar que se borre a sí mismo.
$id_actual = $_SESSION['usuario_id'] ?? 0;
$sqlUsers = "SELECT * FROM usuarios WHERE id_usuario != ? ORDER BY nombre_usuario ASC";
$stmtUsers = $pdo->prepare($sqlUsers);
$stmtUsers->execute([$id_actual]);
$lista_usuarios = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

// 4. Incluir Header
include '../includes/header_personalmode.php';
?>

<style>
    .main-container {
        max-width: 900px; /* Más ancho para la tabla */
        margin: 40px auto;
        background-color: #f9f9f9;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        color: #333;
        position: relative;
        z-index: 1;
    }

    .title-area {
        text-align: center;
        border-bottom: 2px solid #ddd;
        padding-bottom: 15px;
        margin-bottom: 25px;
    }

    .title-area h2 {
        color: #d9534f; /* Rojo suave para indicar "Zona de Peligro/Borrar" o mantener verde si prefieres */
        /* Para mantener consistencia con tu verde/gris, usaremos el gris oscuro del header */
        color: #333; 
        margin: 0;
    }
    
    .subtitle {
        color: #666;
        font-size: 0.9rem;
        margin-top: 5px;
    }

    /* Tabla de selección */
    .table-container {
        overflow-x: auto;
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
        font-size: 0.95rem;
    }

    th, td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }

    th {
        background-color: #6a5a5a; /* Gris del Header */
        color: white;
        font-weight: 600;
    }

    tr:hover {
        background-color: #f1f1f1;
    }

    /* Checkbox personalizado grande */
    input[type="checkbox"] {
        transform: scale(1.3);
        cursor: pointer;
        accent-color: #d9534f; /* Rojo al marcar */
    }

    /* Botones */
    .btn-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #eee;
    }

    .btn {
        padding: 12px 30px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
        font-weight: bold;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        transition: 0.3s;
    }

    .btn-cancel {
        background-color: #6a5a5a; /* Gris */
        color: white;
    }
    .btn-cancel:hover { background-color: #4e4242; }

    .btn-confirm {
        background-color: #d9534f; /* Rojo para acción destructiva */
        color: white;
    }
    .btn-confirm:hover { background-color: #c9302c; }

    /* Alertas */
    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 4px;
        text-align: center;
    }
    .alert-exito { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

    .empty-msg {
        text-align: center;
        padding: 20px;
        color: #777;
        font-style: italic;
    }
</style>

<div class="main-container">
    <div class="title-area">
        <h2><i class="fas fa-user-minus"></i> Eliminar Personal</h2>
        <p class="subtitle">Seleccione los empleados que desea dar de baja del sistema.</p>
    </div>

    <?php if (!empty($mensaje)): ?>
        <div class="alert <?php echo ($tipo_mensaje == 'exito') ? 'alert-exito' : 'alert-error'; ?>">
            <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" onsubmit="return confirm('¿Está seguro de eliminar los usuarios seleccionados? Esta acción no se puede deshacer.');">
        
        <div class="table-container">
            <?php if (count($lista_usuarios) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">Seleccionar</th>
                        <th>Usuario</th>
                        <th>Rol Asignado</th>
                        <th>Clave (Encriptada)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista_usuarios as $user): ?>
                    <tr>
                        <td style="text-align: center;">
                            <input type="checkbox" name="usuarios_ids[]" value="<?php echo $user['id_usuario']; ?>">
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($user['nombre_usuario']); ?></strong>
                        </td>
                        <td>
                            <?php 
                                // Decodificar Rol visualmente
                                if ($user['id_rol'] == 1) echo '<span style="color:#27672c; font-weight:bold;">Administrador</span>';
                                else echo 'Empleado';
                            ?>
                        </td>
                        <td style="font-family: monospace; color: #888; font-size: 0.85rem;">
                            <?php 
                                // Mostramos solo una parte de la clave para referencia visual
                                echo substr($user['clave_usuario'], 0, 15) . "..."; 
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="empty-msg">
                    No hay otros empleados registrados para eliminar.
                </div>
            <?php endif; ?>
        </div>

        <div class="btn-actions">
            <a href="../includes/header_personalmode.php" class="btn btn-cancel">
                <i class="fas fa-arrow-left"></i> Cancelar
            </a>
            
            <?php if (count($lista_usuarios) > 0): ?>
            <button type="submit" name="eliminar_btn" class="btn btn-confirm">
                Confirmar Eliminación <i class="fas fa-trash-alt"></i>
            </button>
            <?php endif; ?>
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