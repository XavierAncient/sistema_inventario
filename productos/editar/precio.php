<?php
session_start();
require_once '../../config/db.php'; 

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 1. Verificación de seguridad
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] != 1) {
    die("<h3 style='color:red; text-align:center;'>Acceso restringido.</h3>");
}

// 2. Obtener marcas
$marcas = $pdo->query("SELECT id_marca, nombre_marca FROM marcas ORDER BY nombre_marca")->fetchAll();

// --- AJAX ---
if (isset($_GET['ajax_action'])) {
    header('Content-Type: application/json');
    
    // A) Cargar Modelos
    if ($_GET['ajax_action'] === 'get_modelos' && isset($_GET['id_marca'])) {
        $stmt = $pdo->prepare("SELECT id_modelo, nombre_modelo, anio_modelo FROM modelos WHERE id_marca = ?");
        $stmt->execute([$_GET['id_marca']]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // B) Cargar Inventario
    if ($_GET['ajax_action'] === 'get_inventario' && isset($_GET['id_modelo'])) {
        $sql = "SELECT i.id_inventario, i.stock, i.id_calidad, i.id_vidrio, i.precio_unitario,
                       v.tipo_vidrio, c.nombre_calidad
                FROM inventario i
                JOIN vidrios v ON i.id_vidrio = v.id_vidrio
                JOIN calidades c ON i.id_calidad = c.id_calidad
                WHERE i.id_modelo = ?
                ORDER BY v.id_vidrio, c.id_calidad";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_GET['id_modelo']]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }
    exit;
}

// --- GUARDAR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'actualizar_precio') {
    try {
        $items = $_POST['cambios'] ?? [];
        $pdo->beginTransaction();
        $cambios_realizados = 0;

        foreach ($items as $id_inventario => $datos) {
            $nuevo_precio = floatval($datos['nuevo_precio']);
            $precio_actual = floatval($datos['precio_actual']); 
            
            if ($nuevo_precio != $precio_actual && $nuevo_precio >= 0) {
                $sql = "UPDATE inventario SET precio_unitario = ? WHERE id_inventario = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nuevo_precio, $id_inventario]);
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
    <title>Editar Precio</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        /* --- VARIABLES --- */
        :root {
            --theme-color: #333; 
            --theme-hover: #555;
            --bg-body: #f4f4f4;
            --btn-green: #27672cff; /* Color Verde Restaurado */
            --btn-green-hover: #18461cff;
        }

        body { font-family: Arial, sans-serif; background-color: var(--bg-body); margin: 0; padding: 0; }
        
        /* HEADER */
        header { 
            background-color: var(--theme-color); 
            color: white; 
            padding: 15px 20px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        header h2 { margin: 0; font-size: 1.2rem; }
        
        /* BOTONES */
        .btn { padding: 8px 15px; border: none; cursor: pointer; border-radius: 4px; text-decoration: none; font-size: 0.9rem; display: inline-block; transition: background 0.3s; }
        
        .btn-cancel { 
            background-color: transparent; 
            border: 1px solid white; 
            color: white; 
        }
        .btn-cancel:hover { background-color: rgba(255,255,255,0.1); }

        /* CAMBIO: Botón Confirmar en VERDE */
        .btn-confirm { 
            background-color: var(--btn-green); 
            color: white; 
            margin-left: 10px; 
        }
        .btn-confirm:hover { background-color: var(--btn-green-hover); }
        
        /* Botones Modal */
        .btn-modal-close { background-color: #6c757d; color: white; }
        .btn-modal-new { background-color: #0d6efd; color: white; margin-left: 10px; }
        .btn-modal-new:hover { background-color: #0b5ed7; }

        .container { max-width: 1000px; margin: 20px auto; padding: 0 15px; }
        .card { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        
        /* Grid */
        .form-row { display: flex; gap: 20px; flex-wrap: wrap; }
        .form-group { flex: 1; min-width: 200px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; color: var(--theme-color); }
        .form-control { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        
        /* Header Tabla */
        .card-header-custom {
            font-weight: bold; 
            color: var(--theme-color);
            border-bottom: 2px solid var(--theme-color);
            padding-bottom: 10px; 
            margin-bottom: 15px;
        }

        /* Tabla */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px; text-align: center; border-bottom: 1px solid #ddd; vertical-align: middle; }
        th { background-color: #f8f9fa; color: var(--theme-color); }
        .badge-price { font-weight: bold; color: #2c3e50; }
        
        .alert-error { background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; border: 1px solid #f5c6cb; margin-bottom: 15px; }

        /* Modal */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000;
        }
        .modal-box {
            background: white; padding: 25px; border-radius: 8px; text-align: center; width: 400px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.2);
        }
        .modal-header { font-size: 1.25rem; font-weight: bold; color: var(--btn-green); margin-bottom: 15px; }
        .modal-buttons { margin-top: 20px; }
    </style>
</head>
<body>

    <header>
        <div style="font-weight:bold;"><i class="fas fa-tags"></i> Editar Precios</div>
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

        <form id="formPrecio" method="POST">
            <input type="hidden" name="action" value="actualizar_precio">

            <div class="card">
                <div class="card-header-custom">
                    Productos Registrados
                </div>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Calidad</th>
                                <th>Stock</th>
                                <th>Precio Actual ($)</th>
                                <th style="background-color: #e3f2fd; width: 25%;">Nuevo Precio ($)</th>
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
            <p id="msgExito">Precio Cambiado Exitosamente.</p>
            <div class="modal-buttons">
                <a href="../../index.php" class="btn btn-modal-close">Cerrar</a>
                <a href="precio.php" class="btn btn-modal-new">Realizar nueva edición</a>
            </div>
        </div>
    </div>

    <script>
        const selectMarca = document.getElementById('selectMarca');
        const selectModelo = document.getElementById('selectModelo');
        const inputAnio = document.getElementById('inputAnio');
        const tablaBody = document.getElementById('tablaBody');
        const modalExito = document.getElementById('modalExito');

        // --- Cargar Modelos ---
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

        // --- Al cambiar Modelo ---
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
                        let precioDisplay = parseFloat(item.precio_unitario).toFixed(2);
                        const row = `
                            <tr>
                                <td style="font-weight:bold;">${item.tipo_vidrio}</td>
                                <td>${item.nombre_calidad}</td>
                                <td>${item.stock}</td>
                                <td>
                                    <span class="badge-price">$${precioDisplay}</span>
                                    <input type="hidden" name="cambios[${item.id_inventario}][precio_actual]" value="${item.precio_unitario}">
                                </td>
                                <td>
                                    <input type="number" 
                                           name="cambios[${item.id_inventario}][nuevo_precio]" 
                                           class="form-control" 
                                           style="border: 1px solid #2196F3; text-align:center;" 
                                           step="0.01" 
                                           min="0"
                                           value="${item.precio_unitario}"> 
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
            document.getElementById('formPrecio').submit();
        }

        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('success')) {
                const n = parseInt(urlParams.get('n')) || 0;
                
                if(n > 0) {
                    document.getElementById('msgExito').innerText = "Precios actualizados exitosamente.";
                } else {
                    document.getElementById('msgExito').innerText = "No se detectaron cambios en los precios.";
                }
                
                modalExito.style.display = "flex";
            }
        });
    </script>
</body>
</html>