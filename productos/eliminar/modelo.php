<?php
session_start();
require_once '../../config/db.php'; 

$mensaje = '';
$error = '';
$mostrar_modal_fallo = false; // Bandera para el modal de error/redirección

// --- AJAX: Obtener modelos ---
if (isset($_GET['action']) && $_GET['action'] === 'get_modelos' && isset($_GET['id_marca'])) {
    header('Content-Type: application/json');
    try {
        $stmt = $pdo->prepare("SELECT id_modelo, nombre_modelo, anio_modelo FROM Modelos WHERE id_marca = ? ORDER BY nombre_modelo ASC");
        $stmt->execute([$_GET['id_marca']]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo json_encode([]);
    }
    exit;
}

// --- CARGAR MARCAS ---
try {
    $stmt_marcas = $pdo->query("SELECT id_marca, nombre_marca FROM Marcas ORDER BY nombre_marca ASC");
    $marcas = $stmt_marcas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al cargar marcas.";
}

// --- LÓGICA DE ELIMINACIÓN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_modelo = $_POST['id_modelo'] ?? '';

    if (!empty($id_modelo)) {
        try {
            // 1. Verificar si hay productos en el inventario afiliados a este modelo
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM inventario WHERE id_modelo = ?");
            $stmt_check->execute([$id_modelo]);
            $productos_afiliados = $stmt_check->fetchColumn();

            if ($productos_afiliados > 0) {
                // CASO FALLIDO: Hay productos, no se puede borrar el modelo.
                $mostrar_modal_fallo = true;
            } else {
                // CASO EXITOSO: No hay productos, procedemos a borrar el modelo.
                $pdo->beginTransaction();

                $del_mod = $pdo->prepare("DELETE FROM Modelos WHERE id_modelo = ?");
                $del_mod->execute([$id_modelo]);

                $pdo->commit();
                $mensaje = "Eliminación de Modelo exitosa.";
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Error al eliminar: " . $e->getMessage();
        }
    } else {
        $error = "Debe seleccionar un modelo válido.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Eliminación de Modelo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* --- ESTÉTICA REUTILIZADA --- */
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
            background-color: #dc3545; 
            margin: 10px auto 0;
            border-radius: 2px;
        }

        .form-label { font-weight: 500; font-size: 0.9rem; margin-bottom: 5px; color: #f0f0f0; }
        .form-control, .form-select {
            background-color: #f0f0f0; 
            border: 1px solid #3e3c3cff;
            color: #333;
            border-radius: 5px;
            padding: 10px;
        }
        .form-control[readonly] { background-color: #dcdcdc; color: #555; cursor: not-allowed; }

        /* Botones */
        .btn-custom-confirm {
            background-color: #27672cff; 
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
            background-color: #dc3545; 
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

        /* Modal */
        .modal-content { background-color: #3e3c3cff; color: white; border: 1px solid #555; }
        .modal-header { border-bottom: 1px solid #555; }
        .modal-footer { border-top: 1px solid #555; }
        .btn-yes { background-color: #dc3545; color: white; }
        .btn-no { background-color: #6c757d; color: white; }
    </style>
</head>
<body>

    <div class="form-card">
        <h2 class="form-title">Eliminación de modelo</h2>

        <?php if ($mensaje): ?>
            <div class="alert-custom alert-success-custom"><?= $mensaje ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-custom alert-error-custom"><?= $error ?></div>
        <?php endif; ?>

        <form id="formEliminar" method="POST" action="">
            
            <div class="mb-3">
                <label for="selectMarca" class="form-label">Marca</label>
                <select id="selectMarca" class="form-select" required>
                    <option value="">Seleccione una marca...</option>
                    <?php foreach ($marcas as $marca): ?>
                        <option value="<?= $marca['id_marca'] ?>">
                            <?= htmlspecialchars($marca['nombre_marca']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="selectModelo" class="form-label">Nombre del Modelo</label>
                <select name="id_modelo" id="selectModelo" class="form-select" disabled required>
                    <option value="">Primero seleccione marca...</option>
                </select>
            </div>

            <div class="mb-4">
                <label for="anio_modelo" class="form-label">Año</label>
                <input type="text" class="form-control" id="anio_modelo" name="anio_modelo_visual" placeholder="Automático" readonly>
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
                    <h5 class="modal-title">Confirmación de Eliminación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    ¿Está seguro que desea eliminar este modelo permanentemente?
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-no" data-bs-dismiss="modal">No</button>
                    <button type="button" class="btn btn-yes" onclick="ejecutarEliminacion()">Si</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalFallo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">Borrado Fallido</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Hay productos afiliados a este modelo.<br>
                    ¿Desea eliminar primero los productos para proseguir?
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-no" data-bs-dismiss="modal">No</button>
                    <a href="borrar.php" class="btn btn-yes">Si</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const selectMarca = document.getElementById('selectMarca');
        const selectModelo = document.getElementById('selectModelo');
        const inputAnio = document.getElementById('anio_modelo');

        selectMarca.addEventListener('change', function() {
            const idMarca = this.value;
            selectModelo.innerHTML = '<option value="">Cargando...</option>';
            selectModelo.disabled = true;
            inputAnio.value = '';

            if (idMarca) {
                fetch(`?action=get_modelos&id_marca=${idMarca}`)
                    .then(r => r.json())
                    .then(data => {
                        selectModelo.innerHTML = '<option value="">Seleccione modelo...</option>';
                        if (data.length > 0) {
                            data.forEach(m => {
                                const opt = document.createElement('option');
                                opt.value = m.id_modelo; 
                                opt.textContent = m.nombre_modelo; 
                                opt.dataset.anio = m.anio_modelo;
                                selectModelo.appendChild(opt);
                            });
                            selectModelo.disabled = false;
                        } else {
                            selectModelo.innerHTML = '<option value="">Sin modelos</option>';
                        }
                    });
            } else {
                selectModelo.innerHTML = '<option value="">Primero seleccione marca...</option>';
            }
        });

        selectModelo.addEventListener('change', function() {
            const sel = this.options[this.selectedIndex];
            inputAnio.value = sel.dataset.anio || '';
        });

        function mostrarConfirmacion() {
            if(!selectModelo.value) { alert("Seleccione un modelo."); return; }
            new bootstrap.Modal(document.getElementById('modalConfirmacion')).show();
        }

        function ejecutarEliminacion() {
            document.getElementById('formEliminar').submit();
        }

        // --- ACTIVAR MODAL DE ERROR AUTOMÁTICAMENTE ---
        <?php if ($mostrar_modal_fallo): ?>
            document.addEventListener("DOMContentLoaded", function(){
                new bootstrap.Modal(document.getElementById('modalFallo')).show();
            });
        <?php endif; ?>
    </script>
</body>
</html>