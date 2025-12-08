<?php
session_start();
// Ajusta la ruta si es necesario
require_once '../../config/db.php'; 

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] != 1) {
    echo "<div class='alert alert-danger text-center mt-5'>Acceso restringido.</div>";
    exit;
}

// --- AJAX ---
if (isset($_GET['ajax_action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax_action'] === 'get_modelos' && isset($_GET['id_marca'])) {
        $stmt = $pdo->prepare("SELECT id_modelo, nombre_modelo, anio_modelo FROM Modelos WHERE id_marca = ?");
        $stmt->execute([$_GET['id_marca']]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($_GET['ajax_action'] === 'get_inventario' && isset($_GET['id_modelo'])) {
        // Consulta corregida para tabla 'vidrios'
        $stmt = $pdo->prepare("SELECT tipo_vidrio, id_calidad, id_ubicacion, stock FROM vidrios WHERE id_modelo = ?");
        $stmt->execute([$_GET['id_modelo']]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }
    exit;
}

// --- GUARDAR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_batch') {
    try {
        $id_modelo = $_POST['id_modelo'];
        $items = $_POST['items'] ?? [];
        $pdo->beginTransaction();

        foreach ($items as $vidrio => $datos) {
            $sel_calidad = $datos['id_calidad'];
            $sel_ubicacion = $datos['id_ubicacion'];
            $inserts = [];

            // Lógica de inserción
            if ($sel_calidad === 'both' && $sel_ubicacion === 'both') {
                if (isset($datos['matrix'])) {
                    foreach ($datos['matrix'] as $id_cal => $ubicaciones) {
                        foreach ($ubicaciones as $id_ub => $cant) {
                            if ($cant > 0) $inserts[] = ['cal' => $id_cal, 'ub' => $id_ub, 'cant' => $cant];
                        }
                    }
                }
            } elseif ($sel_calidad === 'both') {
                if (isset($datos['split_calidad'])) {
                    foreach ($datos['split_calidad'] as $id_cal => $cant) {
                        if ($cant > 0) $inserts[] = ['cal' => $id_cal, 'ub' => $sel_ubicacion, 'cant' => $cant];
                    }
                }
            } elseif ($sel_ubicacion === 'both') {
                if (isset($datos['split_ubicacion'])) {
                    foreach ($datos['split_ubicacion'] as $id_ub => $cant) {
                        if ($cant > 0) $inserts[] = ['cal' => $sel_calidad, 'ub' => $id_ub, 'cant' => $cant];
                    }
                }
            } else {
                $cant = (int)$datos['cantidad_final'];
                if ($cant > 0) $inserts[] = ['cal' => $sel_calidad, 'ub' => $sel_ubicacion, 'cant' => $cant];
            }

            // Ejecutar queries en tabla 'vidrios'
            foreach ($inserts as $ins) {
                $stmt = $pdo->prepare("SELECT id_inventario, stock FROM vidrios 
                                       WHERE id_modelo = ? AND tipo_vidrio = ? AND id_calidad = ? AND id_ubicacion = ?");
                $stmt->execute([$id_modelo, $vidrio, $ins['cal'], $ins['ub']]);
                $existente = $stmt->fetch();

                if ($existente) {
                    $upd = $pdo->prepare("UPDATE vidrios SET stock = stock + ? WHERE id_inventario = ?");
                    $upd->execute([$ins['cant'], $existente['id_inventario']]);
                } else {
                    $inst = $pdo->prepare("INSERT INTO vidrios (id_modelo, tipo_vidrio, id_calidad, id_ubicacion, stock, precio_unitario) VALUES (?, ?, ?, ?, ?, 0)");
                    $inst->execute([$id_modelo, $vidrio, $ins['cal'], $ins['ub'], $ins['cant']]);
                }
            }
        }
        
        $pdo->commit();
        header('Location: admin_inventario.php?ok=batch_success'); 
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Datos iniciales
$marcas = $pdo->query("SELECT id_marca, nombre_marca FROM Marcas ORDER BY nombre_marca")->fetchAll();
$calidades = $pdo->query("SELECT id_calidad, nombre_calidad FROM Calidades")->fetchAll(PDO::FETCH_ASSOC);
$ubicaciones = $pdo->query("SELECT id_ubicacion, nombre_ubicacion FROM Ubicaciones")->fetchAll(PDO::FETCH_ASSOC);

$lista_vidrios = ['Parabrisas Frontal', 'Parabrisas Trasero', 'Ventana Piloto', 'Ventana Copiloto', 'Ventana Acompañante Piloto', 'Ventana Acompañante Copiloto', 'Quarter Piloto', 'Quarter Copiloto'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reabastecer Avanzado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        header { background-color: #333; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; color: white; }
        .table-custom th { background-color: #f8f9fa; text-align: center; vertical-align: middle; font-size: 0.9rem; }
        .table-custom td { vertical-align: top; padding: 8px; }
        .col-detail { display: none; background-color: #fff8e1; }
        .show-col { display: table-cell !important; }
        .sub-label { font-size: 0.75rem; color: #666; margin-bottom: 2px; display: block; }
        .mini-input { margin-bottom: 5px; }
    </style>
</head>
<body class="bg-light">

    <header>
    <div class="fw-bold">Añadir / Reabastecer</div>
    <div>
        <a href="../../index.php" class="btn btn-outline-light btn-sm">Cancelar</a>
        
        <button type="button" onclick="submitForm()" class="btn btn-success btn-sm ms-2">Guardar</button>
    </div>
</header>

    <div class="container-fluid mt-4 px-4">
        <?php if (isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

        <form id="formReabastecer" method="POST">
            <input type="hidden" name="action" value="save_batch">

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
                            <th style="width: 10%">Total a Añadir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lista_vidrios as $vidrio): ?>
                        <tr class="row-glass" data-vidrio="<?= $vidrio ?>">
                            <td class="fw-bold"><?= $vidrio ?></td>
                            
                            <td>
                                <select name="items[<?= $vidrio ?>][id_calidad]" class="form-select form-select-sm sel-calidad" onchange="toggleRows(this)">
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
                                        <input type="number" name="items[<?= $vidrio ?>][split_calidad][<?= $c['id_calidad'] ?>]" class="form-control form-control-sm mini-input calc-trigger" min="0" placeholder="0">
                                    </div>
                                <?php endforeach; ?>
                            </td>

                            <td>
                                <select name="items[<?= $vidrio ?>][id_ubicacion]" class="form-select form-select-sm sel-ubicacion" onchange="toggleRows(this)">
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
                                            <input type="number" name="items[<?= $vidrio ?>][split_ubicacion][<?= $u['id_ubicacion'] ?>]" class="form-control form-control-sm mini-input calc-trigger" min="0" placeholder="0">
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="box-matrix d-none">
                                    <small class="text-primary d-block mb-1">Desglose Total:</small>
                                    <?php foreach ($calidades as $c): ?>
                                        <?php foreach ($ubicaciones as $u): ?>
                                            <div class="mb-1 border-bottom pb-1">
                                                <span class="sub-label" style="font-size:0.7rem">
                                                    <?= $c['nombre_calidad'] ?> &#8594; <?= $u['nombre_ubicacion'] ?>:
                                                </span>
                                                <input type="number" 
                                                       name="items[<?= $vidrio ?>][matrix][<?= $c['id_calidad'] ?>][<?= $u['id_ubicacion'] ?>]" 
                                                       class="form-control form-control-sm mini-input calc-trigger matrix-input" 
                                                       min="0" placeholder="0">
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </div>
                            </td>

                            <td><input type="text" class="form-control form-control-sm text-center input-stock" readonly value="-"></td>
                            
                            <td><input type="number" name="items[<?= $vidrio ?>][cantidad_final]" class="form-control form-control-sm fw-bold input-total" readonly value="0"></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>

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

            // Resetear triggers para evitar cálculos residuales
            row.querySelectorAll('.calc-trigger').forEach(i => i.value = ''); 
            inputTotal.removeAttribute('readonly'); 

            // --- LÓGICA DE VISIBILIDAD DE COLUMNAS ---
            
            // 1. ¿Mostrar Detalles Calidad?
            // SOLO si Calidad = Ambos Y Ubicacion != Ambos
            if (valCalidad === 'both' && valUbicacion !== 'both') {
                cellDetCal.classList.add('show-col');
                inputTotal.setAttribute('readonly', true);
            } else {
                // Si ambos son 'both', ocultamos esta columna para que solo se vea la matriz en Ubicación
                cellDetCal.classList.remove('show-col');
            }

            // 2. ¿Mostrar Detalles Ubicación?
            if (valUbicacion === 'both') {
                cellDetUbi.classList.add('show-col');
                inputTotal.setAttribute('readonly', true);
                
                if (valCalidad === 'both') {
                    // MODO MATRIZ (Ambos + Ambos) -> Solo esta columna visible
                    boxSplitUbi.classList.add('d-none');
                    boxMatrix.classList.remove('d-none');
                    
                    // Desactivamos inputs de calidad para que no interfieran en el POST
                    cellDetCal.querySelectorAll('input').forEach(el => el.disabled = true);
                } else {
                    // MODO SOLO UBICACIÓN
                    boxSplitUbi.classList.remove('d-none');
                    boxMatrix.classList.add('d-none');
                    
                    // Reactivamos inputs de calidad si estuvieran desactivados
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

        // Lógica de Cabeceras: Verificar todos los estados de fila
        function checkHeaders() {
            let showCalHeader = false;
            let showUbiHeader = false;

            document.querySelectorAll('.row-glass').forEach(row => {
                const c = row.querySelector('.sel-calidad').value;
                const u = row.querySelector('.sel-ubicacion').value;

                // Cabecera Calidad: Mostrar si hay alguna fila que sea Ambos PERO NO Ubicación Ambos
                if (c === 'both' && u !== 'both') {
                    showCalHeader = true;
                }
                // Cabecera Ubicación: Mostrar si hay alguna fila con Ubicación Ambos
                if (u === 'both') {
                    showUbiHeader = true;
                }
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
                // Sumar Matriz
                row.querySelectorAll('.box-matrix input').forEach(inp => sum += Number(inp.value));
                inputTotal.value = sum;
            } 
            else if (valCal === 'both') {
                // Sumar Detalles Calidad
                row.querySelectorAll('.detail-calidad input:not(:disabled)').forEach(inp => sum += Number(inp.value));
                inputTotal.value = sum;
            } 
            else if (valUb === 'both') {
                // Sumar Detalles Ubicación
                row.querySelectorAll('.box-split-ubicacion input').forEach(inp => sum += Number(inp.value));
                inputTotal.value = sum;
            } 
        }

        document.addEventListener('input', function(e) {
            if(e.target.classList.contains('calc-trigger')) {
                calculateTotal(e.target.closest('tr'));
            }
        });

        // --- CARGA DE DATOS ---
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
                const tipo = row.dataset.vidrio;
                const idCal = row.querySelector('.sel-calidad').value;
                const idUb = row.querySelector('.sel-ubicacion').value;
                const inputStock = row.querySelector('.input-stock');
                
                if (idCal !== 'both' && idUb !== 'both') {
                    const match = data.find(d => d.tipo_vidrio === tipo && d.id_calidad == idCal && d.id_ubicacion == idUb);
                    inputStock.value = match ? match.stock : 0;
                } else {
                    inputStock.value = 'Var';
                }
            });
        }
        
        document.querySelectorAll('.sel-calidad, .sel-ubicacion').forEach(s => {
            s.addEventListener('change', () => {
                const row = s.closest('tr');
                // Al cambiar select, forzamos re-chequeo de headers y filas
                // La función toggleRows se llama sola por el onchange del HTML, 
                // pero updateStocks necesita saber que algo cambió visualmente
                const idCal = row.querySelector('.sel-calidad').value;
                const idUb = row.querySelector('.sel-ubicacion').value;
                const inputStock = row.querySelector('.input-stock');
                if (idCal === 'both' || idUb === 'both') {
                    inputStock.value = 'Mix';
                } else {
                    selectModelo.dispatchEvent(new Event('change')); 
                }
            });
        });

        function submitForm() {
            if(!selectModelo.value) { alert("Seleccione modelo"); return; }
            document.getElementById('formReabastecer').submit();
        }
    </script>
</body>
</html>