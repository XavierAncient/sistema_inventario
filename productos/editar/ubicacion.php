<?php
session_start();
require_once '../../config/db.php'; 

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 1. Verificación de seguridad
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] != 1) {
    die("<h3 style='color:red; text-align:center;'>Acceso restringido.</h3>");
}

// 2. Obtener listas
$marcas = $pdo->query("SELECT id_marca, nombre_marca FROM marcas ORDER BY nombre_marca")->fetchAll();
$ubicaciones = $pdo->query("SELECT id_ubicacion, nombre_ubicacion FROM ubicaciones ORDER BY nombre_ubicacion")->fetchAll(PDO::FETCH_ASSOC);

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
        $sql = "SELECT i.id_inventario, i.stock, i.id_ubicacion, i.id_calidad, i.id_vidrio,
                       v.tipo_vidrio, c.nombre_calidad, u.nombre_ubicacion as ubicacion_actual
                FROM inventario i
                JOIN vidrios v ON i.id_vidrio = v.id_vidrio
                JOIN calidades c ON i.id_calidad = c.id_calidad
                JOIN ubicaciones u ON i.id_ubicacion = u.id_ubicacion
                WHERE i.id_modelo = ?
                ORDER BY v.id_vidrio, c.id_calidad";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_GET['id_modelo']]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }
    exit;
}

// --- GUARDAR (PROCESAR) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reubicar') {
    try {
        $items = $_POST['cambios'] ?? [];
        $pdo->beginTransaction();
        $cambios_realizados = 0;

        foreach ($items as $id_inventario => $datos) {
            $nueva_ubicacion = $datos['nueva_ubicacion'];
            $ubicacion_actual = $datos['ubicacion_actual']; 
            
            if ($nueva_ubicacion != $ubicacion_actual) {
                // Obtener datos fila origen
                $stmt = $pdo->prepare("SELECT * FROM inventario WHERE id_inventario = ?");
                $stmt->execute([$id_inventario]);
                $source = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$source) continue;

                // Buscar destino existente (Merge)
                $check = $pdo->prepare("SELECT id_inventario, stock FROM inventario 
                                        WHERE id_modelo = ? AND id_vidrio = ? AND id_calidad = ? AND id_ubicacion = ?");
                $check->execute([$source['id_modelo'], $source['id_vidrio'], $source['id_calidad'], $nueva_ubicacion]);
                $target = $check->fetch(PDO::FETCH_ASSOC);

                if ($target) {
                    // FUSIÓN
                    $nuevo_stock = $target['stock'] + $source['stock'];
                    $pdo->prepare("UPDATE inventario SET stock = ? WHERE id_inventario = ?")
                        ->execute([$nuevo_stock, $target['id_inventario']]);
                    $pdo->prepare("DELETE FROM inventario WHERE id_inventario = ?")
                        ->execute([$id_inventario]);
                } else {
                    // CAMBIO SIMPLE
                    $pdo->prepare("UPDATE inventario SET id_ubicacion = ? WHERE id_inventario = ?")
                        ->execute([$nueva_ubicacion, $id_inventario]);
                }
                $cambios_realizados++;
            }
        }

        $pdo->commit();
        header('Location: ' . $_SERVER['PHP_SELF'] . '?success=1&n=' . $cambios_realizados);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Ubicación</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        /* Estilos Generales sin Bootstrap */
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
        
        /* Header */
        header { background-color: #333; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        header h2 { margin: 0; font-size: 1.2rem; }
        
        /* Botones */
        .btn { padding: 8px 15px; border: none; cursor: pointer; border-radius: 4px; text-decoration: none; font-size: 0.9rem; display: inline-block; }
        .btn-cancel { background-color: transparent; border: 1px solid white; color: white; }
        .btn-confirm { background-color: #27672cff; color: white; margin-left: 10px; }
        .btn-modal-close { background-color: #6c757d; color: white; }
        .btn-modal-new { background-color: #0d6efd; color: white; margin-left: 10px; }
        
        /* Contenedor Principal */
        .container { max-width: 1000px; margin: 20px auto; padding: 0 15px; }
        
        /* Cajas / Cards */
        .card { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        
        /* Grid del Formulario */
        .form-row { display: flex; gap: 20px; flex-wrap: wrap; }
        .form-group { flex: 1; min-width: 200px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; color: #333; }
        .form-control { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        
        /* Tabla */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px; text-align: center; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; color: #333; }
        .badge { background-color: #e2e3e5; color: #383d41; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem; }
        
        /* Alert Error */
        .alert-error { background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; border: 1px solid #f5c6cb; margin-bottom: 15px; }

        /* MODAL PERSONALIZADO (CSS) */
        .modal-overlay {
            display: none; /* Oculto por defecto */
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal-box {
            background: white;
            padding: 25px;
            border-radius: 8px;
            text-align: center;
            width: 400px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.2);
        }
        .modal-header { font-size: 1.25rem; font-weight: bold; color: #27672cff; margin-bottom: 15px; }
        .modal-buttons { margin-top: 20px; }
    </style>
</head>
<body>

    <header>
        <div style="font-weight:bold;"><i class="fas fa-map-marker-alt"></i> Editar Ubicación</div>
        <div>
            <a href="../../index.php" class="btn btn-cancel">Cancelar</a>
            <button type="button" onclick="confirmarCambios()" class="btn btn-confirm">Confirmar</button>
        </div>
    </header>

    <div class="container">
        
        <?php if (isset($error)): ?>
            <div class="alert-error"><?= $error ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="form-row">
                <div class="form-group">
                    <label>Marca</label>
                    <select id="selectMarca" class="form-control">
                        <option value="">Seleccione...</option>
                        <?php foreach ($marcas as $m): ?>
                            <option value="<?= $m['id_marca'] ?>"><?= $m['nombre_marca'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Modelo</label>
                    <select id="selectModelo" class="form-control" disabled>
                        <option value="">Esperando Marca...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Año</label>
                    <input type="text" id="inputAnio" class="form-control" readonly>
                </div>
            </div>
        </div>

        <form id="formUbicacion" method="POST">
            <input type="hidden" name="action" value="reubicar">

            <div class="card">
                <div style="font-weight:bold; color:gray; border-bottom:1px solid #eee; padding-bottom:10px; margin-bottom:10px;">
                    Productos Registrados
                </div>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Calidad</th>
                                <th>Stock</th>
                                <th>Ubicación Actual</th>
                                <th style="background-color: #fff3cd;">Nueva Ubicación</th>
                            </tr>
                        </thead>
                        <tbody id="tablaBody">
                            <tr>
                                <td colspan="5" style="color:gray;">Seleccione un modelo para cargar los productos...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
    </div>

    <div id="modalExito" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header"><i class="fas fa-check-circle"></i> Operación Exitosa</div>
            <p id="msgExito">Producto Reubicado con éxito.</p>
            <div class="modal-buttons">
                <a href="../../index.php" class="btn btn-modal-close">Cerrar</a>
                <a href="ubicacion.php" class="btn btn-modal-new">Realizar nueva reubicación</a>
            </div>
        </div>
    </div>

    <script>
        // Datos PHP a JS
        const listaUbicaciones = <?= json_encode($ubicaciones) ?>;

        // Referencias DOM
        const selectMarca = document.getElementById('selectMarca');
        const selectModelo = document.getElementById('selectModelo');
        const inputAnio = document.getElementById('inputAnio');
        const tablaBody = document.getElementById('tablaBody');
        const modalExito = document.getElementById('modalExito');

        // --- Selects ---
        selectMarca.addEventListener('change', function() {
            const idMarca = this.value;
            selectModelo.innerHTML = '<option value="">Cargando...</option>';
            selectModelo.disabled = true;
            tablaBody.innerHTML = '<tr><td colspan="5" style="color:gray;">Seleccione un modelo...</td></tr>';
            
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
            if(this.value) cargarInventario(this.value);
        });

        // --- Cargar Tabla ---
        function cargarInventario(idModelo) {
            tablaBody.innerHTML = '<tr><td colspan="5">Cargando datos...</td></tr>';

            fetch(`?ajax_action=get_inventario&id_modelo=${idModelo}`)
                .then(r => r.json())
                .then(data => {
                    tablaBody.innerHTML = '';
                    if (data.length === 0) {
                        tablaBody.innerHTML = '<tr><td colspan="5" style="color:red;">No hay productos para este modelo.</td></tr>';
                        return;
                    }

                    data.forEach(item => {
                        let optionsHtml = '';
                        listaUbicaciones.forEach(u => {
                            const selected = (u.id_ubicacion == item.id_ubicacion) ? 'selected' : '';
                            optionsHtml += `<option value="${u.id_ubicacion}" ${selected}>${u.nombre_ubicacion}</option>`;
                        });

                        // Note: Usamos clases CSS nativas ahora (form-control, badge)
                        const row = `
                            <tr>
                                <td style="font-weight:bold;">${item.tipo_vidrio}</td>
                                <td>${item.nombre_calidad}</td>
                                <td>${item.stock}</td>
                                <td>
                                    <span class="badge">${item.ubicacion_actual}</span>
                                    <input type="hidden" name="cambios[${item.id_inventario}][ubicacion_actual]" value="${item.id_ubicacion}">
                                </td>
                                <td>
                                    <select name="cambios[${item.id_inventario}][nueva_ubicacion]" class="form-control" style="border: 1px solid #ffc107;">
                                        ${optionsHtml}
                                    </select>
                                </td>
                            </tr>
                        `;
                        tablaBody.innerHTML += row;
                    });
                });
        }

        function confirmarCambios() {
            if (selectModelo.value === "") {
                alert("Primero seleccione un modelo y cargue los productos.");
                return;
            }
            document.getElementById('formUbicacion').submit();
        }

        // --- Mostrar Modal si hay éxito ---
        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('success')) {
                const n = parseInt(urlParams.get('n')) || 0;
                // Si n > 0 hubo cambios, si n=0 (reubicó al mismo sitio) técnicamente es éxito del proceso pero sin cambios reales.
                // Ajustamos mensaje si quieres:
                if(n > 0) {
                   document.getElementById('msgExito').innerText = "Producto(s) reubicado(s) con éxito.";
                } else {
                   document.getElementById('msgExito').innerText = "Proceso finalizado (sin cambios detectados).";
                }
                
                // Mostrar overlay
                modalExito.style.display = "flex";
            }
        });
    </script>
</body>
</html>