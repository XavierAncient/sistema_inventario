<?php
session_start();
require_once '../../config/db.php'; 

$mensaje = '';
$error = '';
$mostrar_modal_redireccion = false; // Bandera para activar el segundo modal

// --- CARGAR MARCAS ---
try {
    $stmt_marcas = $pdo->query("SELECT id_marca, nombre_marca FROM Marcas ORDER BY nombre_marca ASC");
    $marcas = $stmt_marcas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al cargar marcas.";
}

// --- LÓGICA DE ELIMINACIÓN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_marca = $_POST['id_marca'] ?? '';

    if (!empty($id_marca)) {
        try {
            // 1. Verificar si existen modelos afiliados a esta marca
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM Modelos WHERE id_marca = ?");
            $stmt_check->execute([$id_marca]);
            $cantidad_modelos = $stmt_check->fetchColumn();

            if ($cantidad_modelos > 0) {
                // CASO FALLIDO: Hay modelos, no se puede borrar.
                // Activamos la bandera para mostrar el modal de redirección
                $mostrar_modal_redireccion = true;
            } else {
                // CASO EXITOSO: No hay modelos, procedemos a borrar.
                $pdo->beginTransaction();
                
                // Borramos la marca
                $del_marca = $pdo->prepare("DELETE FROM Marcas WHERE id_marca = ?");
                $del_marca->execute([$id_marca]);

                $pdo->commit();
                $mensaje = "Eliminación de Marca Exitosa.";
                
                // Recargamos la lista de marcas para que desaparezca la borrada
                $stmt_marcas = $pdo->query("SELECT id_marca, nombre_marca FROM Marcas ORDER BY nombre_marca ASC");
                $marcas = $stmt_marcas->fetchAll(PDO::FETCH_ASSOC);
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Error técnico: " . $e->getMessage();
        }
    } else {
        $error = "Seleccione una marca válida.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Eliminación de Marca</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* --- ESTILOS DARK THEME (Reutilizados) --- */
        body {
            background-color: #887f7fff; 
            color: white;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            display: flex;
            align-items: center; 
            justify-content: center;
            margin: 0;
        }

        .form-card {
            background-color: #6a5a5aff; 
            border-radius: 10px;
            padding: 30px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            border: 2px solid #3e3c3cff; 
        }

        .form-title {
            text-align: center;
            font-weight: bold;
            font-size: 1.5rem;
            margin-bottom: 25px;
            position: relative;
            padding-bottom: 10px;
            color: #ffffff;
        }
        
        .form-title::after {
            content: '';
            display: block;
            width: 50px;
            height: 4px;
            background-color: #dc3545; /* Rojo para indicar eliminación */
            margin: 10px auto 0;
            border-radius: 2px;
        }

        .form-label { font-weight: 500; font-size: 0.9rem; margin-bottom: 5px; color: #f0f0f0; }
        .form-select {
            background-color: #f0f0f0; 
            border: 1px solid #3e3c3cff;
            color: #333;
            border-radius: 5px;
            padding: 10px;
        }

        /* Botones */
        .btn-custom-confirm {
            background-color: #27672cff; /* Verde */
            color: white;
            font-weight: bold;
            border: none;
            padding: 10px;
            width: 100%;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .btn-custom-confirm:hover { background-color: #1b5517ff; }

        .btn-custom-cancel {
            background-color: #dc3545; /* Rojo */
            color: white;
            font-weight: bold;
            border: none;
            padding: 10px;
            width: 100%;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .btn-custom-cancel:hover { background-color: #bb2d3b; color: white; }

        /* Alertas */
        .alert-custom { padding: 10px; border-radius: 5px; margin-bottom: 20px; text-align: center; font-size: 0.9rem; }
        .alert-success-custom { background-color: #d1e7dd; color: #0f5132; }
        .alert-error-custom { background-color: #f8d7da; color: #842029; }

        /* Modales */
        .modal-content { background-color: #3e3c3cff; color: white; border: 1px solid #555; }
        .modal-header { border-bottom: 1px solid #555; }
        .modal-footer { border-top: 1px solid #555; }
        .btn-yes { background-color: #dc3545; color: white; }
        .btn-no { background-color: #6c757d; color: white; }
    </style>
</head>
<body>

    <div class="form-card">
        <h2 class="form-title">Eliminación de Marca</h2>

        <?php if ($mensaje): ?>
            <div class="alert-custom alert-success-custom"><?= $mensaje ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-custom alert-error-custom"><?= $error ?></div>
        <?php endif; ?>

        <form id="formEliminarMarca" method="POST" action="">
            <div class="mb-4">
                <label for="id_marca" class="form-label">Marca</label>
                <select name="id_marca" id="id_marca" class="form-select" required>
                    <option value="">Seleccione una marca...</option>
                    <?php foreach ($marcas as $marca): ?>
                        <option value="<?= $marca['id_marca'] ?>">
                            <?= htmlspecialchars($marca['nombre_marca']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row g-2">
                <div class="col-6">
                    <a href="../../index.php" class="btn-custom-cancel">Cancelar</a>
                </div>
                <div class="col-6">
                    <button type="button" class="btn-custom-confirm" onclick="mostrarConfirmacion()">Confirmar</button>
                </div>
            </div>
        </form>
    </div>

    <div class="modal fade" id="modalConfirmacion" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Recordatorio</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Para eliminar una marca debe primero deshacerse de los modelos afiliados a ella.<br><br>
                    ¿Desea continuar?
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-no" data-bs-dismiss="modal">No</button>
                    <button type="button" class="btn btn-yes" onclick="enviarFormulario()">Si</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalFallo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">Eliminación Fallida</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Modelos Afiliados Existentes.<br>
                    ¿Desea ir a modelos para proseguir?
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-no" data-bs-dismiss="modal">No</button>
                    <a href="modelo.php" class="btn btn-yes">Si</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Función para abrir el primer modal
        function mostrarConfirmacion() {
            const idMarca = document.getElementById('id_marca').value;
            if (idMarca === "") {
                alert("Por favor seleccione una marca.");
                return;
            }
            var myModal = new bootstrap.Modal(document.getElementById('modalConfirmacion'));
            myModal.show();
        }

        // Función para enviar el form
        function enviarFormulario() {
            document.getElementById('formEliminarMarca').submit();
        }

        // --- LÓGICA PARA ABRIR EL SEGUNDO MODAL AUTOMÁTICAMENTE ---
        // Si PHP detecta error de dependencia, activa esta variable
        <?php if ($mostrar_modal_redireccion): ?>
            document.addEventListener("DOMContentLoaded", function(){
                var errorModal = new bootstrap.Modal(document.getElementById('modalFallo'));
                errorModal.show();
            });
        <?php endif; ?>
    </script>
</body>
</html>