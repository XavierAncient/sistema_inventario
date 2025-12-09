<?php
session_start();
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
        $stmt = $pdo->prepare("SELECT id_modelo, nombre_modelo, anio_modelo FROM modelos WHERE id_marca = ?");
        $stmt->execute([$_GET['id_marca']]);
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
        $contador_inserts = 0; // Contador para mensaje plural/singular

        foreach ($items as $id_vidrio => $datos) {
            $sel_calidad = $datos['id_calidad'];
            $sel_ubicacion = $datos['id_ubicacion'];
            $precio_unitario = !empty($datos['precio']) ? $datos['precio'] : 0;

            $inserts = [];

            // Lógica de desglose
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
                // Caso directo (Default): Toma la cantidad final
                $cant = (int)$datos['cantidad_final'];
                if ($cant > 0) $inserts[] = ['cal' => $sel_calidad, 'ub' => $sel_ubicacion, 'cant' => $cant];
            }

            foreach ($inserts as $ins) {
                $stmt = $pdo->prepare("SELECT id_inventario FROM inventario 
                                       WHERE id_modelo = ? AND id_vidrio = ? AND id_calidad = ? AND id_ubicacion = ?");
                $stmt->execute([$id_modelo, $id_vidrio, $ins['cal'], $ins['ub']]);
                
                if ($stmt->fetch()) {
                    throw new Exception("Error: Un producto ya existe en esa ubicación/calidad.");
                } else {
                    $inst = $pdo->prepare("INSERT INTO inventario (id_modelo, id_vidrio, id_calidad, id_ubicacion, stock, precio_unitario) 
                                           VALUES (?, ?, ?, ?, ?, ?)");
                    $inst->execute([$id_modelo, $id_vidrio, $ins['cal'], $ins['ub'], $ins['cant'], $precio_unitario]);
                    $contador_inserts++;
                }
            }
        }
        
        $pdo->commit();
        // Redirige al index enviando el número de productos guardados
        header('Location: ../../index.php?ok=success&n=' . $contador_inserts); 
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// --- CARGA DE DATOS INICIALES ---
$marcas = $pdo->query("SELECT id_marca, nombre_marca FROM marcas ORDER BY nombre_marca")->fetchAll();
$calidades = $pdo->query("SELECT id_calidad, nombre_calidad FROM calidades")->fetchAll(PDO::FETCH_ASSOC);
$ubicaciones = $pdo->query("SELECT id_ubicacion, nombre_ubicacion FROM ubicaciones")->fetchAll(PDO::FETCH_ASSOC);
$lista_vidrios = $pdo->query("SELECT id_vidrio, tipo_vidrio FROM Vidrios ORDER BY id_vidrio")->fetchAll(PDO::FETCH_ASSOC);

// --- LÓGICA DE DEFAULT (NUEVO) ---
// Buscamos automáticamente el ID de "Genérica" y "Almacén 1"
// Ajusta los textos dentro de las comillas si en tu BD se llaman ligeramente diferente (ej: "Generica" sin tilde)
$id_default_calidad = "";
foreach($calidades as $c) {
    if (stripos($c['nombre_calidad'], 'Genérica') !== false || stripos($c['nombre_calidad'], 'Generica') !== false) {
        $id_default_calidad = $c['id_calidad'];
        break;
    }
}

$id_default_ubicacion = "";
foreach($ubicaciones as $u) {
    if (stripos($u['nombre_ubicacion'], 'Almacén 1') !== false || stripos($u['nombre_ubicacion'], 'Almacen 1') !== false) {
        $id_default_ubicacion = $u['id_ubicacion'];
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Añadir Nuevo Producto</title>
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
        <div class="fw-bold">Añadir Nuevo Producto</div>
        <div>
            <a href="../../index.php" class="btn btn-outline-light btn-sm">Cancelar</a>
            <button type="button" onclick="submitForm()" class="btn btn-success btn-sm ms-2">Guardar</button>
        </div>
    </header>

    <div class="container-fluid mt-4 px-4">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger text-center fw-bold shadow-sm">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form id="formNuevo" method="POST">
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
                            <th style="width: 20%">Vidrio</th> 
                            <th style="width: 15%">Calidad</th>
                            <th class="col-detail detail-calidad" style="width: 15%">Detalles Calidad</th>
                            <th style="width: 15%">Ubicación</th>
                            <th class="col-detail detail-ubicacion" style="width: 20%">Detalles Ubicación</th>
                            <th style="width: 15%">Cantidad Inicial</th>
                            <th style="width: 15%">Precio Unit. ($)</th> 
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lista_vidrios as $v): 
                            $idVidrio = $v['id_vidrio'];
                        ?>
                        <tr class="row-glass">
                            <td class="fw-bold"><?= htmlspecialchars($v['tipo_vidrio']) ?></td>
                            
                            <td>
                                <select name="items[<?= $idVidrio ?>][id_calidad]" class="form-select form-select-sm sel-calidad" onchange="toggleRows(this)">
                                    <?php foreach ($calidades as $c): 
                                        // Verificamos si es la opción por defecto
                                        $selected = ($c['id_calidad'] == $id_default_calidad) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $c['id_calidad'] ?>" <?= $selected ?>><?= $c['nombre_calidad'] ?></option>
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
                                    <?php foreach ($ubicaciones as $u): 
                                        // Verificamos si es la opción por defecto
                                        $selected = ($u['id_ubicacion'] == $id_default_ubicacion) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $u['id_ubicacion'] ?>" <?= $selected ?>><?= $u['nombre_ubicacion'] ?></option>
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
                                    <small class="text-primary d-block mb-1">Desglose:</small>
                                    <?php foreach ($calidades as $c): ?>
                                        <?php foreach ($ubicaciones as $u): ?>
                                            <div class="mb-1 border-bottom pb-1">
                                                <span class="sub-label"><?= $c['nombre_calidad'] ?> > <?= $u['nombre_ubicacion'] ?>:</span>
                                                <input type="number" 
                                                       name="items[<?= $idVidrio ?>][matrix][<?= $c['id_calidad'] ?>][<?= $u['id_ubicacion'] ?>]" 
                                                       class="form-control form-control-sm mini-input calc-trigger matrix-input" 
                                                       min="0" placeholder="0">
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </div>
                            </td>

                            <td>
                                <input type="number" name="items[<?= $idVidrio ?>][cantidad_final]" class="form-control form-control-sm fw-bold input-total" value="">
                            </td>

                            <td>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="items[<?= $idVidrio ?>][precio]" class="form-control form-control-sm" step="0.01" min="0" placeholder="0.00">
                                </div>
                            </td>

                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>

    <script>
        // Mismo JS de antes, pero al cargar la página debemos inicializar el estado
        // por si las opciones por defecto no son "both"
        
        function toggleRows(selectElement) {
            const row = selectElement.closest('tr');
            const valCalidad = row.querySelector('.sel-calidad').value;
            const valUbicacion = row.querySelector('.sel-ubicacion').value;
            
            const cellDetCal = row.querySelector('.detail-calidad');
            const cellDetUbi = row.querySelector('.detail-ubicacion');
            const boxSplitUbi = row.querySelector('.box-split-ubicacion');
            const boxMatrix = row.querySelector('.box-matrix');
            const inputTotal = row.querySelector('.input-total');

            // Limpiamos sub-inputs al cambiar modo
            row.querySelectorAll('.calc-trigger').forEach(i => i.value = ''); 
            
            // Regla: Si ambos selects tienen un valor específico (No "both"),
            // habilitamos el total general y ocultamos detalles.
            if (valCalidad !== 'both' && valUbicacion !== 'both') {
                inputTotal.removeAttribute('readonly');
                cellDetCal.classList.remove('show-col');
                cellDetUbi.classList.remove('show-col');
                boxMatrix.classList.add('d-none');
            } 
            else {
                // Si alguno es "both", el total es readonly (se calcula solo)
                inputTotal.setAttribute('readonly', true);
                
                // Lógica visual Calidad
                if (valCalidad === 'both' && valUbicacion !== 'both') {
                    cellDetCal.classList.add('show-col');
                } else {
                    cellDetCal.classList.remove('show-col');
                }

                // Lógica visual Ubicación
                if (valUbicacion === 'both') {
                    cellDetUbi.classList.add('show-col');
                    if (valCalidad === 'both') {
                        // Matriz completa
                        boxSplitUbi.classList.add('d-none');
                        boxMatrix.classList.remove('d-none');
                    } else {
                        // Solo split ubicacion
                        boxSplitUbi.classList.remove('d-none');
                        boxMatrix.classList.add('d-none');
                    }
                } else {
                    cellDetUbi.classList.remove('show-col');
                    boxMatrix.classList.add('d-none');
                }
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
            
            // Si NO es modo desglose, no calculamos nada (el usuario escribe manual)
            if (valCal !== 'both' && valUb !== 'both') return;

            let sum = 0;
            if (valCal === 'both' && valUb === 'both') {
                row.querySelectorAll('.box-matrix input').forEach(inp => sum += Number(inp.value));
            } else if (valCal === 'both') {
                row.querySelectorAll('.detail-calidad input:not(:disabled)').forEach(inp => sum += Number(inp.value));
            } else if (valUb === 'both') {
                row.querySelectorAll('.box-split-ubicacion input').forEach(inp => sum += Number(inp.value));
            } 
            inputTotal.value = sum;
        }

        document.addEventListener('input', function(e) {
            if(e.target.classList.contains('calc-trigger')) calculateTotal(e.target.closest('tr'));
        });

        // Carga de modelos
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
        });

        function submitForm() {
            if(!selectModelo.value) { alert("Seleccione modelo"); return; }
            document.getElementById('formNuevo').submit();
        }
    </script>
</body>
</html>