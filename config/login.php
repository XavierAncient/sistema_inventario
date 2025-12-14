<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $usuario = $_POST['nombre_usuario'];
  $clave = $_POST['clave_usuario'];

  $stmt = $pdo->prepare("SELECT * FROM Usuarios WHERE nombre_usuario = ?");
  $stmt->execute([$usuario]);
  $user = $stmt->fetch();

 if ($user && sha1($clave) === $user['clave_usuario']) {
    $_SESSION['usuario_id'] = $user['id_usuario'];
    $_SESSION['usuario_nombre'] = $user['nombre_usuario'];
    $_SESSION['usuario_rol'] = $user['id_rol'];
    // Redirección
    header('Location: ../index.php');
    exit;
  } else {
    $error = "Usuario o clave incorrectos";
  }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - GJ Inventory</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #887f7fff; /* Fondo general del sistema */
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login-container {
            background-color: #2b2b2b; /* Color oscuro (igual que la sidebar) */
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 400px;
            text-align: center;
            color: white;
            border: 1px solid #3e3c3cff;
        }

        .login-container h2 {
            margin-bottom: 10px;
            font-weight: bold;
            color: #ffffff;
        }

        .login-container p {
            color: #ccc;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: #ddd;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #888;
        }

        .form-control {
            width: 100%;
            padding: 12px 12px 12px 40px; /* Espacio para el icono */
            border: 1px solid #555;
            border-radius: 4px;
            background-color: #444;
            color: white;
            font-size: 16px;
            box-sizing: border-box; /* Importante para que el padding no rompa el ancho */
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #27672cff; /* Borde verde al enfocar */
            background-color: #505050;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background-color: #27672cff; /* Verde del sistema */
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-top: 10px;
        }

        .btn-login:hover {
            background-color: #1b5517ff; /* Verde más oscuro al pasar el mouse */
        }

        .error-msg {
            background-color: #a72828;
            color: white;
            padding: 10px;
            border-radius: 4px;
            margin-top: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
    </style>
</head>
<body>

    <div class="login-container">
        <h2>GJ Inventory</h2>
        <p>Bienvenido al sistema. Inicie sesión para continuar.</p>
        
        <form method="POST">
            <div class="form-group">
                <label for="usuario">Usuario</label>
                <div class="input-wrapper">
                    <i class="fas fa-user"></i>
                    <input type="text" class="form-control" id="usuario" name="nombre_usuario" placeholder="Ingrese su usuario" required>
                </div>
            </div>

            <div class="form-group">
                <label for="clave">Contraseña</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" class="form-control" id="clave" name="clave_usuario" placeholder="Ingrese su contraseña" required>
                </div>
            </div>

            <button type="submit" class="btn-login">Ingresar</button>
        </form>

        <?php if (isset($error)): ?>
            <div class="error-msg">
                <i class="fas fa-exclamation-circle"></i>
                <?= $error ?>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>