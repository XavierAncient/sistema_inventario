<?php
session_start();
// Ajustamos ruta (2 niveles arriba para config)
require_once '../../config/db.php'; 

// --- PERMISOS: ACCESO PARA ADMIN (1) Y EMPLEADO (2) ---
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../../config/login.php");
    exit;
}
// (No verificamos rol específico porque ambos pueden entrar)

$resultados_html = ''; // Aquí guardaremos el mensaje final
$busqueda_fallida = false;

// --- AJAX (Carga de datos) ---
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

// --- PROCESAR BÚSQUEDA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'buscar') {
    $id_modelo = $_POST['id_modelo'];
    $vidrios_seleccionados = $_POST['vidrios'] ?? [];
    
    // Filtrar solo los que tienen "yes"
    $ids_a_buscar = [];
    foreach ($vidrios_seleccionados as $id_vidrio => $val) {
        if ($val === 'yes') $ids_a_buscar[] = $id_vidrio;
    }

    if (!empty($ids_a_buscar)) {
        // Obtenemos nombres de marca/modelo para el mensaje
        $stmt_info = $pdo->prepare("
            SELECT m.nombre_modelo, m.anio_modelo, mar.nombre_marca 
            FROM modelos m JOIN marcas mar ON m.id_marca = mar.id_marca 
            WHERE m.id_modelo = ?");
        $stmt_info->execute([$id_modelo]);
        $info_vehiculo = $stmt_info->fetch(PDO::FETCH_ASSOC);
        
        $marca_txt = $info_vehiculo['nombre_marca'];
        $modelo_txt = $info_vehiculo['nombre_modelo'];
        $anio_txt = $info_vehiculo['anio_modelo'];

        // Consultamos el inventario
        // Traemos todo lo relacionado a este modelo y los vidrios seleccionados
        // Hacemos JOIN con tablas para obtener nombres de texto (Original, Almacen 1, etc)
        $placeholders = implode(',', array_fill(0, count($ids_a_buscar), '?'));
        $sql = "SELECT i.id_vidrio, v.tipo_vidrio, 
                       c.nombre_calidad, u.nombre_ubicacion, i.stock
                FROM inventario i
                JOIN Vidrios v ON i.id_vidrio = v.id_vidrio
                JOIN calidades c ON i.id_calidad = c.id_calidad
                JOIN ubicaciones u ON i.id_ubicacion = u.id_ubicacion
                WHERE i.id_modelo = ? AND i.id_vidrio IN ($placeholders) AND i.stock > 0";
        
        $params = array_merge([$id_modelo], $ids_a_buscar);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($registros)) {
            $busqueda_fallida = true;
        } else {
            // --- LÓGICA DE AGRUPACIÓN ---
            // Estructura: $data[Vidrio][Ubicacion][Calidad] = stock
            $data = [];
            foreach ($registros as $row) {
                $vid = $row['tipo_vidrio'];
                $ubi = $row['nombre_ubicacion'];
                $cal = strtolower($row['nombre_calidad']); // 'original' o 'genérico'
                $qty = $row['stock'];

                if (!isset($data[$vid][$ubi]['Original'])) $data[$vid][$ubi]['Original'] = 0;
                if (!isset($data[$vid][$ubi]['Genérico'])) $data[$vid][$ubi]['Genérico'] = 0;

                // Sumamos por si hubiera duplicados (aunque no debería por la UK)
                // Ajustamos la key según venga de la DB (normalmente es 'Genérico' con tilde o sin ella, normalizamos)
                if (stripos($cal, 'origin') !== false) $data[$vid][$ubi]['Original'] += $qty;
                else $data[$vid][$ubi]['Genérico'] += $qty;
            }

            // --- GENERAR MENSAJE ---
            $resultados_html .= "<strong>Búsqueda Exitosa.</strong><br><br>";
            
            foreach ($data as $nombre_vidrio => $ubicaciones) {
                // Determinar encabezado de ubicación
                $total_ubicaciones = count($ubicaciones);
                $loc_text = ($total_ubicaciones > 1) ? "en ambas ubicaciones" : "en " . array_key_first($ubicaciones);

                $resultados_html .= "Se ha localizado <strong>$nombre_vidrio</strong> de $marca_txt $modelo_txt ($anio_txt) $loc_text:<br>";

                // Detalles por almacén
                foreach ($ubicaciones as $nombre_ubi => $calidades) {
                    $cant_gen = $calidades['Genérico'];
                    $cant_ori = $calidades['Original'];
                    $resultados_html .= "<span class='ms-3'>• Cantidad en $nombre_ubi: $cant_gen genéricos y $cant_ori originales</span><br>";
                }
                $resultados_html .= "<hr>";
            }
        }
    } else {
        $busqueda_fallida = true; // No seleccionó nada
    }
}

// Carga inicial
$marcas = $pdo->query("SELECT id_marca, nombre_marca FROM marcas ORDER BY nombre_marca")->fetchAll();
$lista_vidrios = $pdo->query("SELECT id_vidrio, tipo_vidrio FROM Vidrios ORDER BY id_vidrio")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Localizar Producto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Estilos Header Dark (Misma estética) */
        header { background-color: #333; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; color: white; }
        
        .table-custom th { background-color: #f8f9fa; text-align: center; vertical-align: middle; font-size: 0.9rem; }
        .table-custom td { vertical-align: middle; padding: 8px; }
        
        .select-search option[value="yes"] { background-color: #27672cff; color: white; font-weight: bold; }
        .select-search option[value="no"] { color: #555; }

        /* Estilos del Modal Personalizado */
        .modal-content { background-color: #3e3c3cff; color: white; border: 1px solid #555; }
        .modal-header { border-bottom: 1px solid #555; }
        .modal-footer { border-top: 1px solid #555; }
        .btn-close-white { filter: invert(1); }
        .btn-custom { background-color: #27672cff; color: white; border:none; }
        .btn-custom:hover { background-color: #1b5517ff; }
    </style>
</head>
<body class="bg-light">

    <header>
        <div class="fw-bold">Localizar Producto</div>
        <div>
            <a href="../../index.php" class="btn btn-outline-light btn-sm">Cerrar</a>
        </div>
    </header>

    <div class="container-fluid mt-4 px-4">
        
        <form id="formBuscar" method="POST">
            <input type="hidden" name="action" value="buscar">

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
                        <select name="id_modelo" id="selectModelo" class="form-select" disabled required>
                            <option value="">Esperando Marca...</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Año</label>
                        <input type="text" id="inputAnio" class="form-control" readonly>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-secondary text-white">Seleccione productos a localizar</div>
                <div class="table-responsive">
                    <table class="table table-bordered table-custom mb-0">
                        <thead>
                            <tr>
                                <th style="width: 70%">Producto (Vidrio)</th>
                                <th style="width: 30%">¿Buscar?</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lista_vidrios as $v): ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($v['tipo_vidrio']) ?></td>
                                <td>
                                    <select name="vidrios[<?= $v['id_vidrio'] ?>]" class="form-select form-select-sm select-search text-center fw-bold">
                                        <option value="no">No</option>
                                        <option value="yes">Si</option>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="button" onclick="validarYBuscar()" class="btn btn-success btn-lg">
                    <i class="fas fa-search"></i> Búsqueda
                </button>
            </div>
        </form>
    </div>

    <div class="modal fade" id="modalResultados" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Resultados de Localización</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if ($busqueda_fallida): ?>
                        <div class="text-center text-danger">
                            <h4>Búsqueda fallida</h4>
                            <p>Producto no localizado</p>
                        </div>
                    <?php else: ?>
                        <div><?= $resultados_html ?></div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <a href="../../index.php" class="btn btn-secondary">Cerrar y Salir</a>
                    <button type="button" class="btn btn-custom" data-bs-dismiss="modal">Nueva Búsqueda</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    
    <script>
        // --- AJAX SELECTORES (Igual que siempre) ---
        const selectMarca = document.getElementById('selectMarca');
        const selectModelo = document.getElementById('selectModelo');
        const inputAnio = document.getElementById('inputAnio');

        selectMarca.addEventListener('change', function() {
            const idMarca = this.value;
            selectModelo.innerHTML = '<option value="">Cargando...</option>';
            selectModelo.disabled = true;
            inputAnio.value = '';
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

        function validarYBuscar() {
            if (!selectModelo.value) {
                alert("Seleccione Marca y Modelo primero.");
                return;
            }
            // Verificar que al menos un vidrio esté en SI
            let algunSi = false;
            document.querySelectorAll('.select-search').forEach(s => {
                if(s.value === 'yes') algunSi = true;
            });

            if(!algunSi) {
                alert("Debe seleccionar 'Si' en al menos un producto de la lista.");
                return;
            }

            document.getElementById('formBuscar').submit();
        }

        // --- ACTIVAR MODAL SI HAY RESULTADOS ---
        <?php if ($resultados_html !== '' || $busqueda_fallida): ?>
            document.addEventListener("DOMContentLoaded", function(){
                new bootstrap.Modal(document.getElementById('modalResultados')).show();
            });
        <?php endif; ?>
    </script>
</body>
</html>