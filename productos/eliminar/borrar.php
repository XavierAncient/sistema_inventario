<?php
session_start();
require_once '../../config/db.php'; 

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] != 1) {
    echo "<div class='alert alert-danger text-center mt-5'>Acceso restringido.</div>";
    exit;
}

$mensaje_exito = '';
$error = '';

// --- AJAX (Carga de datos) ---
if (isset($_GET['ajax_action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax_action'] === 'get_modelos' && isset($_GET['id_marca'])) {
        $stmt = $pdo->prepare("SELECT id_modelo, nombre_modelo, anio_modelo FROM modelos WHERE id_marca = ?");
        $stmt->execute([$_GET['id_marca']]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($_GET['ajax_action'] === 'get_inventario' && isset($_GET['id_modelo'])) {
        $stmt = $pdo->prepare("SELECT id_vidrio, id_calidad, id_ubicacion, stock FROM inventario WHERE id_modelo = ?");
        $stmt->execute([$_GET['id_modelo']]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }
    exit;
}

// --- PROCESAR ELIMINACIÓN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_batch') {
    try {
        $id_modelo = $_POST['id_modelo'];
        $items = $_POST['items'] ?? [];
        
        $pdo->beginTransaction();

        foreach ($items as $id_vidrio => $datos) {
            
            // 1. CASO: BORRAR TODO (Columna Final)
            if (isset($datos['borrar_simple']) && $datos['borrar_simple'] === 'yes') {
                $del = $pdo->prepare("DELETE FROM inventario WHERE id_modelo = ? AND id_vidrio = ?");
                $del->execute([$id_modelo, $id_vidrio]);
                continue; // Pasamos al siguiente vidrio, ya borramos todo de este
            }

            // 2. CASO: BORRADO ESPECÍFICO (Si no se eligió borrar todo)
            
            // A. Matriz (Ambos + Ambos)
            if (isset($datos['matrix'])) {
                foreach ($datos['matrix'] as $id_cal => $ubicaciones) {
                    foreach ($ubicaciones as $id_ub => $val) {
                        if ($val === 'yes') {
                            $del = $pdo->prepare("DELETE FROM inventario WHERE id_modelo = ? AND id_vidrio = ? AND id_calidad = ? AND id_ubicacion = ?");
                            $del->execute([$id_modelo, $id_vidrio, $id_cal, $id_ub]);
                        }
                    }
                }
            }
            
            // B. Split Calidad (Solo Calidad desplegada)
            if (isset($datos['split_calidad'])) {
                foreach ($datos['split_calidad'] as $id_cal => $val) {
                    if ($val === 'yes') {
                        // Borra todas las ubicaciones para esa calidad
                        $del = $pdo->prepare("DELETE FROM inventario WHERE id_modelo = ? AND id_vidrio = ? AND id_calidad = ?");
                        $del->execute([$id_modelo, $id_vidrio, $id_cal]);
                    }
                }
            }

            // C. Split Ubicación (Solo Ubicación desplegada)
            if (isset($datos['split_ubicacion'])) {
                foreach ($datos['split_ubicacion'] as $id_ub => $val) {
                    if ($val === 'yes') {
                        // Borra todas las calidades para esa ubicación
                        $del = $pdo->prepare("DELETE FROM inventario WHERE id_modelo = ? AND id_vidrio = ? AND id_ubicacion = ?");
                        $del->execute([$id_modelo, $id_vidrio, $id_ub]);
                    }
                }
            }
        }
        
        $pdo->commit();
        $mensaje_exito = "Eliminación exitosa.";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Datos iniciales
$marcas = $pdo->query("SELECT id_marca, nombre_marca FROM marcas ORDER BY nombre_marca")->fetchAll();
$calidades = $pdo->query("SELECT id_calidad, nombre_calidad FROM calidades")->fetchAll(PDO::FETCH_ASSOC);
$ubicaciones = $pdo->query("SELECT id_ubicacion, nombre_ubicacion FROM ubicaciones")->fetchAll(PDO::FETCH_ASSOC);
$lista_vidrios = $pdo->query("SELECT id_vidrio, tipo_vidrio FROM Vidrios ORDER BY id_vidrio")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Borrar Producto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        header { background-color: #333; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; color: white; }
        .table-custom th { background-color: #f8f9fa; text-align: center; vertical-align: middle; font-size: 0.9rem; }
        .table-custom td { vertical-align: top; padding: 8px; }
        .col-detail { display: none; background-color: #ffebee; } 
        .show-col { display: table-cell !important; }
        .sub-label { font-size: 0.75rem; color: #666; margin-bottom: 2px; display: block; }
        
        .modal-content { background-color: #3e3c3cff; color: white; border: 1px solid #555; }
        .modal-header { border-bottom: 1px solid #555; }
        .modal-footer { border-top: 1px solid #555; }
        .btn-yes { background-color: #dc3545; color: white; }
        .btn-no { background-color: #6c757d; color: white; }
        
        .select-delete option[value="yes"] { background-color: #dc3545; color: white; }
    </style>
</head>
<body class="bg-light">

    <header>
        <div class="fw-bold">Borrar Producto</div>
        <div>
            <a href="../../index.php" class="btn btn-outline-light btn-sm">Cancelar</a>
            <button type="button" onclick="mostrarConfirmacion()" class="btn btn-danger btn-sm ms-2">Confirmar</button>
        </div>
    </header>

    <div class="container-fluid mt-4 px-4">
        
        <?php if ($mensaje_exito): ?>
            <div class="alert alert-success text-center fw-bold shadow-sm">
                <i class="fas fa-check-circle"></i> <?= $mensaje_exito ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger text-center fw-bold shadow-sm">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <form id="formBorrar" method="POST">
            <input type="hidden" name="action" value="delete_batch">

            <div class="card p-3 mb-3 shadow-sm">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Marca</label>
                        <select name="id_marca" id="selectMarca" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($marcas as $m): echo "<option value='{$m['id_marca']}'>{$m['nombre_marca']}</option>"; endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Modelo</label>
                        <select name="id_modelo" id="selectModelo" class="form-select" disabled required><option value="">Esperando Marca...</option></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Año</label>
                        <input type="text" id="inputAnio" class="form-control" readonly>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm" style="overflow-x: auto;">
                <table class="table table-bordered table-custom mb-0 align-middle">
                    <thead>
                        <tr>
                            <th style="width: 15%">Vidrio</th>
                            <th style="width: 12%">Calidad</th>
                            <th class="col-detail detail-calidad" style="width: 15%">Detalles Calidad</th>
                            <th style="width: 12%">Ubicación</th>
                            <th class="col-detail detail-ubicacion" style="width: 18%">Detalles Ubicación</th>
                            <th style="width: 8%">Stock Actual</th>
                            <th style="width: 10%">Borrar Todo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lista_vidrios as $v): 
                            $idVidrio = $v['id_vidrio'];
                            $nombreVidrio = $v['tipo_vidrio'];
                        ?>
                        <tr class="row-glass" data-id-vidrio="<?= $idVidrio ?>">
                            <td class="fw-bold"><?= htmlspecialchars($nombreVidrio) ?></td>
                            
                            <td>
                                <select name="items[<?= $idVidrio ?>][id_calidad]" class="form-select form-select-sm sel-calidad" onchange="toggleRows(this)">
                                    <?php foreach ($calidades as $c): ?>
                                        <option value="<?= $c['id_calidad'] ?>"><?= $c['nombre_calidad'] ?></option>
                                    <?php endforeach; ?>
                                    <option value="both" style="font-weight:bold; color:blue;">Ambos</option>
                                </select>
                            </td>

                            <td class="col-detail detail-calidad">
                                <?php foreach ($calidades as $c): ?>
                                    <div class="mb-1">
                                        <span class="sub-label"><?= $c['nombre_calidad'] ?>:</span>
                                        <select name="items[<?= $idVidrio ?>][split_calidad][<?= $c['id_calidad'] ?>]" class="form-select form-select-sm select-delete">
                                            <option value="no">No</option>
                                            <option value="yes">Si</option>
                                        </select>
                                    </div>
                                <?php endforeach; ?>
                            </td>

                            <td>
                                <select name="items[<?= $idVidrio ?>][id_ubicacion]" class="form-select form-select-sm sel-ubicacion" onchange="toggleRows(this)">
                                    <?php foreach ($ubicaciones as $u): ?>
                                        <option value="<?= $u['id_ubicacion'] ?>"><?= $u['nombre_ubicacion'] ?></option>
                                    <?php endforeach; ?>
                                    <option value="both" style="font-weight:bold; color:blue;">Ambos</option>
                                </select>
                            </td>

                            <td class="col-detail detail-ubicacion">
                                <div class="box-split-ubicacion">
                                    <?php foreach ($ubicaciones as $u): ?>
                                        <div class="mb-1">
                                            <span class="sub-label"><?= $u['nombre_ubicacion'] ?>:</span>
                                            <select name="items[<?= $idVidrio ?>][split_ubicacion][<?= $u['id_ubicacion'] ?>]" class="form-select form-select-sm select-delete">
                                                <option value="no">No</option>
                                                <option value="yes">Si</option>
                                            </select>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="box-matrix d-none">
                                    <small class="text-danger d-block mb-1">Borrar:</small>
                                    <?php foreach ($calidades as $c): ?>
                                        <?php foreach ($ubicaciones as $u): ?>
                                            <div class="mb-1 border-bottom pb-1">
                                                <span class="sub-label" style="font-size:0.7rem">
                                                    <?= $c['nombre_calidad'] ?> &#8594; <?= $u['nombre_ubicacion'] ?>:
                                                </span>
                                                <select name="items[<?= $idVidrio ?>][matrix][<?= $c['id_calidad'] ?>][<?= $u['id_ubicacion'] ?>]" class="form-select form-select-sm select-delete">
                                                    <option value="no">No</option>
                                                    <option value="yes">Si</option>
                                                </select>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </div>
                            </td>

                            <td><input type="text" class="form-control form-control-sm text-center input-stock" readonly value="-"></td>
                            
                            <td>
                                <select name="items[<?= $idVidrio ?>][borrar_simple]" class="form-select form-select-sm fw-bold select-delete input-total">
                                    <option value="no">No</option>
                                    <option value="yes">Si</option>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>

    <div class="modal fade" id="modalConfirmacion" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">Confirmación de Eliminación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    ¿Está seguro que desea eliminar los productos seleccionados? <br>
                    Esta acción no se puede deshacer.
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-no" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-yes" onclick="enviarFormulario()">Eliminar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // --- JS LOGIC VISUAL (Simplificado) ---
        function toggleRows(selectElement) {
            const row = selectElement.closest('tr');
            const valCalidad = row.querySelector('.sel-calidad').value;
            const valUbicacion = row.querySelector('.sel-ubicacion').value;
            
            const cellDetCal = row.querySelector('.detail-calidad');
            const cellDetUbi = row.querySelector('.detail-ubicacion');
            const boxSplitUbi = row.querySelector('.box-split-ubicacion');
            const boxMatrix = row.querySelector('.box-matrix');
            const inputTotal = row.querySelector('.input-total');

            row.querySelectorAll('.select-delete').forEach(s => s.value = 'no');
            inputTotal.removeAttribute('disabled'); 

            if (valCalidad === 'both' && valUbicacion !== 'both') {
                cellDetCal.classList.add('show-col');
                inputTotal.setAttribute('disabled', true); 
            } else {
                cellDetCal.classList.remove('show-col');
            }

            if (valUbicacion === 'both') {
                cellDetUbi.classList.add('show-col');
                inputTotal.setAttribute('disabled', true);
                if (valCalidad === 'both') {
                    boxSplitUbi.classList.add('d-none');
                    boxMatrix.classList.remove('d-none');
                } else {
                    boxSplitUbi.classList.remove('d-none');
                    boxMatrix.classList.add('d-none');
                }
            } else {
                cellDetUbi.classList.remove('show-col');
                boxMatrix.classList.add('d-none');
            }
            checkHeaders();
        }

        function checkHeaders() {
            let showCalHeader = false;
            let showUbiHeader = false;
            document.querySelectorAll('.row-glass').forEach(row => {
                const c = row.querySelector('.sel-calidad').value;
                const u = row.querySelector('.sel-ubicacion').value;
                if (c === 'both' && u !== 'both') showCalHeader = true;
                if (u === 'both') showUbiHeader = true;
            });
            const thCal = document.querySelector('th.detail-calidad');
            const thUbi = document.querySelector('th.detail-ubicacion');
            if(showCalHeader) thCal.classList.add('show-col'); else thCal.classList.remove('show-col');
            if(showUbiHeader) thUbi.classList.add('show-col'); else thUbi.classList.remove('show-col');
        }

        // --- CARGAR DATOS ---
        const selectMarca = document.getElementById('selectMarca');
        const selectModelo = document.getElementById('selectModelo');
        const inputAnio = document.getElementById('inputAnio');

        selectMarca.addEventListener('change', function() {
            const idMarca = this.value;
            selectModelo.innerHTML = '<option value="">Cargando...</option>';
            selectModelo.disabled = true;
            if (idMarca) {
                fetch(`?ajax_action=get_modelos&id_marca=${idMarca}`)
                    .then(r => r.json())
                    .then(d => {
                        selectModelo.innerHTML = '<option value="">Seleccione...</option>';
                        d.forEach(m => {
                            let opt = document.createElement('option');
                            opt.value = m.id_modelo;
                            opt.text = m.nombre_modelo;
                            opt.dataset.anio = m.anio_modelo;
                            selectModelo.appendChild(opt);
                        });
                        selectModelo.disabled = false;
                    });
            }
        });

        selectModelo.addEventListener('change', function() {
            const sel = this.options[this.selectedIndex];
            inputAnio.value = sel.dataset.anio || '';
            document.querySelectorAll('.input-stock').forEach(el => el.value = '-');
            if(this.value) {
                fetch(`?ajax_action=get_inventario&id_modelo=${this.value}`)
                    .then(r => r.json())
                    .then(data => updateStocks(data));
            }
        });

        function updateStocks(data) {
            document.querySelectorAll('.row-glass').forEach(row => {
                const idVidrio = parseInt(row.dataset.idVidrio);
                const idCal = row.querySelector('.sel-calidad').value;
                const idUb = row.querySelector('.sel-ubicacion').value;
                const inputStock = row.querySelector('.input-stock');
                
                if (idCal !== 'both' && idUb !== 'both') {
                    const match = data.find(d => d.id_vidrio == idVidrio && d.id_calidad == idCal && d.id_ubicacion == idUb);
                    inputStock.value = match ? match.stock : 0;
                } else {
                    inputStock.value = 'Var';
                }
            });
        }
        
        document.querySelectorAll('.sel-calidad, .sel-ubicacion').forEach(s => {
            s.addEventListener('change', () => {
                const row = s.closest('tr');
                const idCal = row.querySelector('.sel-calidad').value;
                const idUb = row.querySelector('.sel-ubicacion').value;
                const inputStock = row.querySelector('.input-stock');
                if (idCal === 'both' || idUb === 'both') inputStock.value = 'Mix';
                else selectModelo.dispatchEvent(new Event('change')); 
            });
        });

        function mostrarConfirmacion() {
            if(!selectModelo.value) { alert("Seleccione modelo"); return; }
            new bootstrap.Modal(document.getElementById('modalConfirmacion')).show();
        }

        function enviarFormulario() {
            document.getElementById('formBorrar').submit();
        }
    </script>
</body>
</html>