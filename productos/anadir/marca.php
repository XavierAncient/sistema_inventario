<?php
session_start();
// Ruta corregida (subiendo 2 niveles)
require_once '../../config/db.php'; 

// --- LÓGICA PARA GUARDAR ---
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_marca = trim($_POST['nombre_marca'] ?? '');

    if (!empty($nombre_marca)) {
        try {
            // Verificar si ya existe
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Marcas WHERE nombre_marca = ?");
            $stmt->execute([$nombre_marca]);
            if ($stmt->fetchColumn() > 0) {
                $error = "¡Esa marca ya existe!";
            } else {
                // Insertar nueva marca
                $insert = $pdo->prepare("INSERT INTO Marcas (nombre_marca) VALUES (?)");
                $insert->execute([$nombre_marca]);
                $mensaje = "Marca guardada exitosamente.";
            }
        } catch (PDOException $e) {
            $error = "Error de base de datos: " . $e->getMessage();
        }
    } else {
        $error = "El nombre de la marca no puede estar vacío.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Marca</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* --- ESTÉTICA BASADA EN TU HEADER --- */
        body {
            background-color: #887f7fff; /* Mismo color de fondo del body del Header */
            color: white;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            display: flex;
            align-items: center; 
            justify-content: center;
            margin: 0;
        }

        /* El cuadro del formulario */
        .form-card {
            background-color: #6a5a5aff; /* Mismo color de fondo del Header */
            border-radius: 10px;
            padding: 30px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            border: 2px solid #3e3c3cff; /* Borde estilo Header */
        }

        /* Título */
        .form-title {
            text-align: center;
            font-weight: bold;
            font-size: 1.5rem;
            margin-bottom: 25px;
            position: relative;
            padding-bottom: 10px;
            color: #ffffff;
        }
        
        /* Línea decorativa bajo el título (verde del header) */
        .form-title::after {
            content: '';
            display: block;
            width: 50px;
            height: 4px;
            background-color: #27672cff; 
            margin: 10px auto 0;
            border-radius: 2px;
        }

        /* Estilo de los inputs */
        .form-label {
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 5px;
            color: #f0f0f0;
        }

        .form-control {
            background-color: #f0f0f0; 
            border: 1px solid #3e3c3cff;
            color: #333;
            border-radius: 5px;
            padding: 10px;
        }
        
        .form-control:focus {
            background-color: #fff;
            box-shadow: 0 0 0 3px rgba(39, 103, 44, 0.25); /* Foco verde */
            border-color: #27672cff;
        }

        /* --- BOTONES --- */
        
        /* Botón Guardar (VERDE - Estilo Header) */
        .btn-custom-save {
            background-color: #27672cff; /* Verde del Header */
            color: white;
            font-weight: bold;
            border: none;
            padding: 10px;
            width: 100%;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .btn-custom-save:hover {
            background-color: #1b5517ff; /* Verde oscuro hover del Header */
        }

        /* Botón Cancelar (ROJO) */
        .btn-custom-cancel {
            background-color: #dc3545; /* Rojo intenso */
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
            background-color: #bb2d3b; /* Rojo más oscuro al pasar mouse */
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
        <h2 class="form-title">Registro de Marca</h2>

        <?php if ($mensaje): ?>
            <div class="alert-custom alert-success-custom"><?= $mensaje ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-custom alert-error-custom"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-4">
                <label for="nombre_marca" class="form-label">Nombre</label>
                <input type="text" 
                       class="form-control" 
                       id="nombre_marca" 
                       name="nombre_marca" 
                       placeholder="Ingrese el nombre de la marca" 
                       required 
                       autocomplete="off">
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