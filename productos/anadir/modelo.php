<?php
session_start();
// Conexión a la base de datos (subiendo 2 niveles)
require_once '../../config/db.php'; 

$mensaje = '';
$error = '';

// --- OBTENER MARCAS PARA EL SELECT ---
try {
    $stmt_marcas = $pdo->query("SELECT id_marca, nombre_marca FROM Marcas ORDER BY nombre_marca ASC");
    $marcas = $stmt_marcas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al cargar marcas: " . $e->getMessage();
}

// --- LÓGICA PARA GUARDAR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_marca = $_POST['id_marca'] ?? '';
    $nombre_modelo = trim($_POST['nombre_modelo'] ?? '');
    $anio_modelo = $_POST['anio_modelo'] ?? '';

    if (!empty($id_marca) && !empty($nombre_modelo) && !empty($anio_modelo)) {
        try {
            // Verificar si ya existe este modelo para esta marca y año
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Modelos WHERE id_marca = ? AND nombre_modelo = ? AND anio_modelo = ?");
            $stmt->execute([$id_marca, $nombre_modelo, $anio_modelo]);
            
            if ($stmt->fetchColumn() > 0) {
                $error = "¡Ese modelo ya está registrado!";
            } else {
                // Insertar nuevo modelo
                $insert = $pdo->prepare("INSERT INTO Modelos (id_marca, nombre_modelo, anio_modelo) VALUES (?, ?, ?)");
                $insert->execute([$id_marca, $nombre_modelo, $anio_modelo]);
                $mensaje = "Modelo guardado exitosamente.";
            }
        } catch (PDOException $e) {
            $error = "Error de base de datos: " . $e->getMessage();
        }
    } else {
        $error = "Todos los campos son obligatorios.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Modelo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* --- ESTÉTICA IDENTICA A MARCA (HEADER STYLE) --- */
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
            background-color: #27672cff; /* Verde Header */
            margin: 10px auto 0;
            border-radius: 2px;
        }

        .form-label {
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 5px;
            color: #f0f0f0;
        }

        .form-control, .form-select {
            background-color: #f0f0f0; 
            border: 1px solid #3e3c3cff;
            color: #333;
            border-radius: 5px;
            padding: 10px;
        }
        
        .form-control:focus, .form-select:focus {
            background-color: #fff;
            box-shadow: 0 0 0 3px rgba(39, 103, 44, 0.25); 
            border-color: #27672cff;
        }

        /* Botones */
        .btn-custom-save {
            background-color: #27672cff; 
            color: white;
            font-weight: bold;
            border: none;
            padding: 10px;
            width: 100%;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .btn-custom-save:hover {
            background-color: #1b5517ff; 
        }

        .btn-custom-cancel {
            background-color: #dc3545; /* Rojo */
            color: white;
            font-weight: bold;
            border: none;
            padding: 10px;
            width: 100%;
            border-radius: 5px;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-custom-cancel:hover {
            background-color: #bb2d3b; 
            color: white;
        }

        /* Alertas */
        .alert-custom {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 0.9rem;
        }
        .alert-success-custom { background-color: #d1e7dd; color: #0f5132; }
        .alert-error-custom { background-color: #f8d7da; color: #842029; }
    </style>
</head>
<body>

    <div class="form-card">
        <h2 class="form-title">Registro de Modelo</h2>

        <?php if ($mensaje): ?>
            <div class="alert-custom alert-success-custom"><?= $mensaje ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-custom alert-error-custom"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            
            <div class="mb-3">
                <label for="id_marca" class="form-label">Marca</label>
                <select name="id_marca" id="id_marca" class="form-select" required>
                    <option value="">Seleccione una marca...</option>
                    <?php if (!empty($marcas)): ?>
                        <?php foreach ($marcas as $marca): ?>
                            <option value="<?= $marca['id_marca'] ?>">
                                <?= htmlspecialchars($marca['nombre_marca']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="" disabled>No hay marcas registradas</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="nombre_modelo" class="form-label">Nombre del Modelo</label>
                <input type="text" 
                       class="form-control" 
                       id="nombre_modelo" 
                       name="nombre_modelo" 
                       placeholder="Ej: Corolla, Civic, Fiesta" 
                       required 
                       autocomplete="off">
            </div>

            <div class="mb-4">
                <label for="anio_modelo" class="form-label">Año</label>
                <input type="number" 
                       class="form-control" 
                       id="anio_modelo" 
                       name="anio_modelo" 
                       placeholder="Ej: 2024" 
                       required 
                       min="1900" 
                       max="2100">
            </div>

            <div class="row g-2">
                <div class="col-6">
                    <a href="../../index.php" class="btn-custom-cancel">Cancelar</a>
                </div>
                <div class="col-6">
                    <button type="submit" class="btn-custom-save">Guardar</button>
                </div>
            </div>
        </form>
    </div>

</body>
</html>