<?php
session_start();
require_once '../../config/db.php'; 

// 1. Verificación de Seguridad
if (!isset($_SESSION['usuario_id'])) {
    die("<h3 style='color:red; text-align:center;'>Acceso restringido.</h3>");
}

// 2. Obtener INVENTARIO ACTUAL (Desde Base de Datos)
$sql = "SELECT 
            m.nombre_marca, mo.nombre_modelo, mo.anio_modelo,
            v.tipo_vidrio, c.nombre_calidad, u.nombre_ubicacion,
            i.stock, i.precio_unitario
        FROM inventario i
        JOIN modelos mo ON i.id_modelo = mo.id_modelo
        JOIN marcas m ON mo.id_marca = m.id_marca
        JOIN vidrios v ON i.id_vidrio = v.id_vidrio
        JOIN calidades c ON i.id_calidad = c.id_calidad
        JOIN ubicaciones u ON i.id_ubicacion = u.id_ubicacion
        ORDER BY m.nombre_marca, mo.nombre_modelo";
$stmt = $pdo->query($sql);
$inventario = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Obtener HISTORIAL (Desde Archivo de Texto 'historial.log')
// El archivo debe estar en la misma carpeta o ajusta la ruta.
$archivo_log = 'historial.log';
$movimientos = [];

if (file_exists($archivo_log)) {
    // Leemos el archivo en un array (cada línea es un elemento)
    $lineas = file($archivo_log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    // Invertimos para ver lo más reciente primero
    $lineas = array_reverse($lineas);
    
    // Procesamos las líneas (Formato esperado: FECHA|USUARIO|ACCION|DETALLE)
    foreach ($lineas as $linea) {
        $partes = explode('|', $linea);
        if (count($partes) >= 4) {
            $movimientos[] = [
                'fecha' => $partes[0],
                'usuario' => $partes[1],
                'accion' => $partes[2],
                'detalle' => $partes[3]
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte General</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style>
        /* --- ESTILOS --- */
        :root {
            --theme-color: #333; 
            --bg-body: #f4f4f4;
            --btn-green: #28a745;
            --btn-green-hover: #218838;
        }
        
        body { font-family: Arial, sans-serif; background-color: var(--bg-body); margin: 0; padding: 0; color: #333; }
        
        /* Header */
        header { 
            background-color: var(--theme-color); 
            color: white; 
            padding: 15px 20px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
        }
        
        .container { max-width: 1100px; margin: 20px auto; padding: 0 15px; }
        
        /* Botones Pie de Página */
        .footer-actions {
            margin-top: 30px; margin-bottom: 50px;
            display: flex; justify-content: center; gap: 20px;
        }
        .btn { padding: 10px 25px; border: none; cursor: pointer; border-radius: 4px; text-decoration: none; font-size: 1rem; display: inline-flex; align-items: center; gap: 8px; color: white; }
        
        .btn-back { background-color: #6c757d; }
        .btn-back:hover { background-color: #5a6268; }

        .btn-save { background-color: var(--btn-green); }
        .btn-save:hover { background-color: var(--btn-green-hover); }

        /* Títulos */
        .section-title {
            color: var(--theme-color);
            border-bottom: 2px solid var(--theme-color);
            padding-bottom: 8px; margin-top: 30px; margin-bottom: 15px;
            font-size: 1.2rem; font-weight: bold;
        }

        /* Tablas */
        .table-container { background: white; padding: 15px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: var(--theme-color); color: white; text-align: center; }
        td { text-align: center; }
        tbody tr:nth-child(even) { background-color: #f9f9f9; }
        
        /* Etiquetas Historial */
        .tag { padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; color: white; font-weight: bold; display: inline-block; min-width: 80px; }
        .tag-gray { background-color: #6c757d; }
    </style>
</head>
<body>

    <header>
        <div style="font-weight:bold; font-size: 1.2rem;">
            <i class="fas fa-clipboard-list"></i> Reporte del Sistema
        </div>
        <div><?= date('d/m/Y H:i') ?></div>
    </header>

    <div class="container">
        
        <div id="area-impresion">
            
            <div class="section-title"><i class="fas fa-boxes"></i> Inventario Actual</div>
            <div class="table-container">
                <?php if(count($inventario) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Marca / Modelo</th>
                            <th>Año</th>
                            <th>Producto</th>
                            <th>Calidad</th>
                            <th>Ubicación</th>
                            <th>Precio</th>
                            <th>Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($inventario as $row): ?>
                        <tr>
                            <td style="text-align:left;">
                                <strong><?= htmlspecialchars($row['nombre_marca']) ?></strong><br>
                                <small><?= htmlspecialchars($row['nombre_modelo']) ?></small>
                            </td>
                            <td><?= $row['anio_modelo'] ?></td>
                            <td><?= $row['tipo_vidrio'] ?></td>
                            <td><?= $row['nombre_calidad'] ?></td>
                            <td><?= $row['nombre_ubicacion'] ?></td>
                            <td>$<?= number_format($row['precio_unitario'], 2) ?></td>
                            <td style="font-weight:bold; font-size:1.05rem;"><?= $row['stock'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p style="text-align:center; padding:20px;">Inventario vacío.</p>
                <?php endif; ?>
            </div>

            <div class="html2pdf__page-break"></div>

            <div class="section-title"><i class="fas fa-history"></i> Historial de Movimientos</div>
            <div class="table-container">
                <?php if(count($movimientos) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 15%;">Fecha</th>
                            <th style="width: 15%;">Usuario</th>
                            <th style="width: 20%;">Acción</th>
                            <th style="width: 50%;">Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($movimientos as $mov): ?>
                        <tr>
                            <td style="font-size:0.85rem;"><?= htmlspecialchars($mov['fecha']) ?></td>
                            <td><?= htmlspecialchars($mov['usuario']) ?></td>
                            <td>
                                <span class="tag tag-gray"><?= htmlspecialchars($mov['accion']) ?></span>
                            </td>
                            <td style="text-align:left; padding-left:20px; font-size:0.9rem;">
                                <?= htmlspecialchars($mov['detalle']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p style="text-align:center; padding:20px; color:gray;">
                        No hay movimientos registrados en <em>historial.log</em>.
                    </p>
                <?php endif; ?>
            </div>
            
        </div> <div class="footer-actions">
            <a href="../../index.php" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
            
            <button onclick="generarPDF()" class="btn btn-save">
                <i class="fas fa-file-pdf"></i> Guardar
            </button>
        </div>

    </div>

    <script>
        function generarPDF() {
            const element = document.getElementById('area-impresion');
            const opt = {
                margin: 0.5,
                filename: 'Reporte_Inventario.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
            };
            html2pdf().set(opt).from(element).save();
        }
    </script>
</body>
</html>