<?php
// 1. Conexión a Base de Datos
require_once '../config/db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Configurar hora de Venezuela
date_default_timezone_set('America/Caracas');

// 2. Obtener Administradores (Rol 1)
$sqlAdmin = "SELECT * FROM usuarios WHERE id_rol = 1 ORDER BY nombre_usuario ASC";
$admins = $pdo->query($sqlAdmin)->fetchAll(PDO::FETCH_ASSOC);

// 3. Obtener Empleados (Rol 2)
$sqlEmp = "SELECT * FROM usuarios WHERE id_rol = 2 ORDER BY nombre_usuario ASC";
$empleados = $pdo->query($sqlEmp)->fetchAll(PDO::FETCH_ASSOC);

// 4. Incluir Header
include '../includes/header_personalmode.php';
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<style>
    .report-container {
        max-width: 800px;
        margin: 40px auto;
        background-color: #f9f9f9;
        padding: 40px;
        border-radius: 8px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        color: #333;
        position: relative; z-index: 1;
    }
    .report-header { text-align: center; border-bottom: 3px solid #27672c; padding-bottom: 15px; margin-bottom: 30px; }
    .report-header h2 { margin: 0; color: #333; font-size: 24px; }
    .report-date { color: #777; font-size: 14px; margin-top: 5px; }
    
    .section-title {
        background-color: #6a5a5a; color: white; padding: 8px 15px;
        font-size: 18px; font-weight: bold; border-radius: 4px;
        margin-top: 30px; margin-bottom: 15px;
    }
    
    table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
    th { background-color: #e9ecef; color: #333; font-weight: bold; border-bottom: 2px solid #ccc; }
    tr:nth-child(even) { background-color: #f8f9fa; }

    .btn-actions { display: flex; justify-content: space-between; margin-top: 40px; border-top: 1px solid #eee; padding-top: 20px; }
    .btn { padding: 12px 25px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold; text-decoration: none; display: inline-flex; align-items: center; gap: 10px; transition: 0.3s; }
    .btn-cancel { background-color: #6a5a5a; color: white; } 
    .btn-download { background-color: #27672c; color: white; }
    .empty-msg { font-style: italic; color: #777; padding: 10px; }
</style>

<div class="report-container">
    
    <div id="area-impresion">
        <div class="report-header">
            <h2><i class="fas fa-users"></i> Listado de Personal</h2>
            <div class="report-date">Generado el: <?php echo date('d/m/Y H:i A'); ?></div>
        </div>

        <div class="section-title">Administradores</div>
        <?php if(count($admins) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th style="width: 10%;">ID</th>
                        <th style="width: 50%;">Nombre de Usuario</th>
                        <th style="width: 40%;">Rol</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($admins as $a): ?>
                    <tr>
                        <td><?php echo $a['id_usuario']; ?></td>
                        <td><strong><?php echo htmlspecialchars($a['nombre_usuario']); ?></strong></td>
                        <td style="color: #27672c; font-weight: bold;">Administrador</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-msg">No hay administradores registrados.</div>
        <?php endif; ?>

        <div class="section-title" style="margin-top: 40px;">Empleados</div>
        <?php if(count($empleados) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th style="width: 10%;">ID</th>
                        <th style="width: 50%;">Nombre de Usuario</th>
                        <th style="width: 40%;">Rol</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($empleados as $e): ?>
                    <tr>
                        <td><?php echo $e['id_usuario']; ?></td>
                        <td><?php echo htmlspecialchars($e['nombre_usuario']); ?></td>
                        <td>Empleado</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-msg">No hay empleados registrados actualmente.</div>
        <?php endif; ?>
        
        <div style="margin-top: 50px; text-align: center; font-size: 12px; color: #999;">
            Documento interno de GJ Inventory
        </div>
    </div>
    <div class="btn-actions">
        <a href="../includes/header_personalmode.php" class="btn btn-cancel">
            <i class="fas fa-arrow-left"></i> Cancelar
        </a>
        <button onclick="generarPDF()" class="btn btn-download">
            Descargar PDF <i class="fas fa-file-pdf"></i>
        </button>
    </div>

</div>

<script>
    function generarPDF() {
        const elemento = document.getElementById('area-impresion');
        const opciones = {
            margin:       0.5,
            filename:     'Reporte_Personal_' + new Date().toISOString().slice(0,10) + '.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2 },
            jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
        };
        html2pdf().set(opciones).from(elemento).save();
    }
    
    if ( window.history.replaceState ) {
        window.history.replaceState( null, null, window.location.href );
    }
</script>

</body>
</html>