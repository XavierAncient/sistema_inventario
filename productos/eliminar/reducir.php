<?php
session_start();
require_once '../../config/db.php'; 

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] != 1) {
    echo "<div class='alert alert-danger text-center mt-5'>Acceso restringido.</div>";
    exit;
}

$show_zero_modal = false; 
$error = '';
$mensaje = '';

// --- AJAX ---
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

// --- PROCESAR REDUCCIÓN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reduce_batch') {
    try {
        $id_modelo = $_POST['id_modelo'];
        $items = $_POST['items'] ?? [];
        
        $pdo->beginTransaction();

        foreach ($items as $id_vidrio => $datos) {
            $sel_calidad = $datos['id_calidad'];
            $sel_ubicacion = $datos['id_ubicacion'];
            $reductions = [];

            // Desglose de matriz/split/simple
            if ($sel_calidad === 'both' && $sel_ubicacion === 'both') {
                if (isset($datos['matrix'])) {
                    foreach ($datos['matrix'] as $id_cal => $ubicaciones) {
                        foreach ($ubicaciones as $id_ub => $cant) {
                            if ($cant > 0) $reductions[] = ['cal' => $id_cal, 'ub' => $id_ub, 'cant' => $cant];
                        }
                    }
                }
            } elseif ($sel_calidad === 'both') {
                if (isset($datos['split_calidad'])) {
                    foreach ($datos['split_calidad'] as $id_cal => $cant) {
                        if ($cant > 0) $reductions[] = ['cal' => $id_cal, 'ub' => $sel_ubicacion, 'cant' => $cant];
                    }
                }
            } elseif ($sel_ubicacion === 'both') {
                if (isset($datos['split_ubicacion'])) {
                    foreach ($datos['split_ubicacion'] as $id_ub => $cant) {
                        if ($cant > 0) $reductions[] = ['cal' => $sel_calidad, 'ub' => $id_ub, 'cant' => $cant];
                    }
                }
            } else {
                $cant = (int)$datos['cantidad_final'];
                if ($cant > 0) $reductions[] = ['cal' => $sel_calidad, 'ub' => $sel_ubicacion, 'cant' => $cant];
            }

            // Procesar cada reducción
            foreach ($reductions as $red) {
                // 1. Buscar stock actual
                $stmt = $pdo->prepare("SELECT id_inventario, stock FROM inventario 
                                       WHERE id_modelo = ? AND id_vidrio = ? AND id_calidad = ? AND id_ubicacion = ?");
                $stmt->execute([$id_modelo, $id_vidrio, $red['cal'], $red['ub']]);
                $existente = $stmt->fetch();

                if ($existente) {
                    $stock_actual = (int)$existente['stock'];
                    $cantidad_reducir = (int)$red['cant'];
                    
                    // --- NUEVA VALIDACIÓN ESTRICTA ---
                    
                    // CASO 1: Intentar reducir más de lo que hay
                    if ($cantidad_reducir > $stock_actual) {
                        throw new Exception("Error: La cantidad a reducir ($cantidad_reducir) excede el stock disponible ($stock_actual).");
                    }

                    // CASO 2: La cantidad es IGUAL al stock (quedaría en 0)
                    if ($cantidad_reducir == $stock_actual) {
                        throw new Exception("ZERO_STOCK_ERROR");
                    }

                    // CASO 3: Reducción válida (Menor al stock)
                    $nuevo_stock = $stock_actual - $cantidad_reducir;
                    $upd = $pdo->prepare("UPDATE inventario SET stock = ? WHERE id_inventario = ?");
                    $upd->execute([$nuevo_stock, $existente['id_inventario']]);

                } else {
                    throw new Exception("Error: Intenta reducir un producto que no existe en el inventario.");
                }
            }
        }
        
        $pdo->commit();
        header('Location: ../../index.php?ok=reduction_success');
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        if ($e->getMessage() === "ZERO_STOCK_ERROR") {
            $show_zero_modal = true; // Activar modal
        } else {
            $error = $e->getMessage(); // Error normal (ej: cantidad excede stock)
        }
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
    <title>Reducir Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        header { background-color: #333; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; color: white; }
        .table-custom th { background-color: #f8f9fa; text-align: center; vertical-align: middle; font-size: 0.9rem; }
        .table-custom td { vertical-align: top; padding: 8px; }
        .col-detail { display: none; background-color: #ffebee; } 
        .show-col { display: table-cell !important; }
        .sub-label { font-size: 0.75rem; color: #666; margin-bottom: 2px; display: block; }
        .mini-input { margin-bottom: 5px; }
        .modal-content { background-color: #3e3c3cff; color: white; border: 1px solid #555; }
        .modal-header { border-bottom: 1px solid #555; }
        .modal-footer { border-top: 1px solid #555; }
        .btn-yes { background-color: #dc3545; color: white; }
        .btn-no { background-color: #6c757d; color: white; }
    </style>
</head>
<body class="bg-light">

    <header>
        <div class="fw-bold">Reducir Inventario</div>
        <div>
            <a href="../../index.php" class="btn btn-outline-light btn-sm">Cancelar</a>
            <button type="button" onclick="submitForm()" class="btn btn-danger btn-sm ms-2">Confirmar Reducción</button>
        </div>
    </header>

    <div class="container-fluid mt-4 px-4">
        
        <?php if ($error): ?>
            <div class="alert alert-danger text-center fw-bold shadow-sm">
                <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <form id="formReducir" method="POST">
            <input type="hidden" name="action" value="reduce_batch">

            <div class="card p-3 mb-3 shadow-sm">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Marca</label>
                        <select id="selectMarca" class="form-select" required>
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
                            <th style="width: 10%">Cant. a Reducir</th>
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
                                        <input type="number" name="items[<?= $idVidrio ?>][split_calidad][<?= $c['id_calidad'] ?>]" class="form-control form-control-sm mini-input calc-trigger" min="0" placeholder="0">
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
                                            <input type="number" name="items[<?= $idVidrio ?>][split_ubicacion][<?= $u['id_ubicacion'] ?>]" class="form-control form-control-sm mini-input calc-trigger" min="0" placeholder="0">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="box-matrix d-none">
                                    <small class="text-danger d-block mb-1">Reducir por:</small>
                                    <?php foreach ($calidades as $c): ?>
                                        <?php foreach ($ubicaciones as $u): ?>
                                            <div class="mb-1 border-bottom pb-1">
                                                <span class="sub-label" style="font-size:0.7rem">
                                                    <?= $c['nombre_calidad'] ?> &#8594; <?= $u['nombre_ubicacion'] ?>:
                                                </span>
                                                <input type="number" 
                                                       name="items[<?= $idVidrio ?>][matrix][<?= $c['id_calidad'] ?>][<?= $u['id_ubicacion'] ?>]" 
                                                       class="form-control form-control-sm mini-input calc-trigger matrix-input" 
                                                       min="0" placeholder="0">
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </div>
                            </td>

                            <td><input type="text" class="form-control form-control-sm text-center input-stock" readonly value="-"></td>
                            
                            <td><input type="number" name="items[<?= $idVidrio ?>][cantidad_final]" class="form-control form-control-sm fw-bold input-total" readonly value="0"></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>

    <div class="modal fade" id="modalFalloCero" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">Reducción Fallida</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    No se puede reducir un producto existente a cero.<br><br>
                    ¿Desea Eliminar el Producto?
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
        // --- JS LOGIC ---
        function toggleRows(selectElement) {
            const row = selectElement.closest('tr');
            const valCalidad = row.querySelector('.sel-calidad').value;
            const valUbicacion = row.querySelector('.sel-ubicacion').value;
            
            const cellDetCal = row.querySelector('.detail-calidad');
            const cellDetUbi = row.querySelector('.detail-ubicacion');
            const boxSplitUbi = row.querySelector('.box-split-ubicacion');
            const boxMatrix = row.querySelector('.box-matrix');
            const inputTotal = row.querySelector('.input-total');

            row.querySelectorAll('.calc-trigger').forEach(i => i.value = ''); 
            inputTotal.removeAttribute('readonly'); 
            
            // Remover el límite máximo visual al cambiar de modo (se recalcula luego)
            inputTotal.removeAttribute('max');

            if (valCalidad === 'both' && valUbicacion !== 'both') {
                cellDetCal.classList.add('show-col');
                inputTotal.setAttribute('readonly', true);
            } else {
                cellDetCal.classList.remove('show-col');
            }

            if (valUbicacion === 'both') {
                cellDetUbi.classList.add('show-col');
                inputTotal.setAttribute('readonly', true);
                if (valCalidad === 'both') {
                    boxSplitUbi.classList.add('d-none');
                    boxMatrix.classList.remove('d-none');
                    cellDetCal.querySelectorAll('input').forEach(el => el.disabled = true);
                } else {
                    boxSplitUbi.classList.remove('d-none');
                    boxMatrix.classList.add('d-none');
                    cellDetCal.querySelectorAll('input').forEach(el => el.disabled = false);
                }
            } else {
                cellDetUbi.classList.remove('show-col');
                boxMatrix.classList.add('d-none');
                cellDetCal.querySelectorAll('input').forEach(el => el.disabled = false);
            }
            checkHeaders();
            calculateTotal(row);
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

        function calculateTotal(row) {
            const valCal = row.querySelector('.sel-calidad').value;
            const valUb = row.querySelector('.sel-ubicacion').value;
            const inputTotal = row.querySelector('.input-total');
            let sum = 0;
            if (valCal === 'both' && valUb === 'both') {
                row.querySelectorAll('.box-matrix input').forEach(inp => sum += Number(inp.value));
            } else if (valCal === 'both') {
                row.querySelectorAll('.detail-calidad input:not(:disabled)').forEach(inp => sum += Number(inp.value));
            } else if (valUb === 'both') {
                row.querySelectorAll('.box-split-ubicacion input').forEach(inp => sum += Number(inp.value));
            } 
            if(valCal === 'both' || valUb === 'both') inputTotal.value = sum;
        }

        document.addEventListener('input', function(e) {
            if(e.target.classList.contains('calc-trigger')) calculateTotal(e.target.closest('tr'));
        });

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
                const inputTotal = row.querySelector('.input-total');
                
                // Limpiar límite previo
                inputTotal.removeAttribute('max');

                if (idCal !== 'both' && idUb !== 'both') {
                    const match = data.find(d => d.id_vidrio == idVidrio && d.id_calidad == idCal && d.id_ubicacion == idUb);
                    if (match) {
                        inputStock.value = match.stock;
                        // --- AQUÍ ESTÁ EL CAMBIO VISUAL ---
                        // Limitamos el input para que no puedan escribir un número mayor al stock
                        // (Ayuda visual, la validación real sigue estando en PHP)
                        inputTotal.max = match.stock;
                    } else {
                        inputStock.value = 0;
                        inputTotal.max = 0;
                    }
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

        function submitForm() {
            if(!selectModelo.value) { alert("Seleccione modelo"); return; }
            document.getElementById('formReducir').submit();
        }

        <?php if ($show_zero_modal): ?>
            document.addEventListener("DOMContentLoaded", function(){
                var errorModal = new bootstrap.Modal(document.getElementById('modalFalloCero'));
                errorModal.show();
            });
        <?php endif; ?>
    </script>
</body>
</html>