<?php
session_start();
include 'conexio.php';

// Redirigir al usuario al login si no está autenticado
if (!isset($_SESSION['usuario'])) {
    header("Location: login.html");
    exit();
}

// Manejo del cambio de idioma
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['idioma'])) {
    $_SESSION['language'] = $_POST['idioma'];
    session_write_close();
    header("Location: home.php");
    exit();
} 

// Establecer idioma desde la sesión o usar el idioma predeterminado
$idioma = $_SESSION['language'] ?? 'es';
$translations = include 'translations.php';
$trans = $translations[$idioma] ?? $translations['es'];

$is_admin = ($_SESSION['usuario'] === 'admin');

// Handle actions for add, edit, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $username = $_POST['username'] ?? null;
    switch ($_POST['action']) {
        case 'add':
            $new_username = $_POST['new_username'];
            $nombre = $_POST['nombre'];
            $apellido = $_POST['apellido'];
            $email = $_POST['email'];
            $query = "INSERT INTO usuarios (username, nombre, apellido, email) VALUES ('$new_username', '$nombre', '$apellido', '$email')";
            mysqli_query($conn, $query);
            break;
        case 'edit':
            $nombre = $_POST['nombre'];
            $apellido = $_POST['apellido'];
            $email = $_POST['email'];
            $query = "UPDATE usuarios SET nombre = '$nombre', apellido = '$apellido', email = '$email' WHERE username = '$username'";
            mysqli_query($conn, $query);
            break;
        case 'delete':
            $query = "DELETE FROM usuarios WHERE username = '$username'";
            mysqli_query($conn, $query);
            break;
    }
    header("Location: home.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="<?php echo $idioma; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $trans['welcome']; ?> - Infratozz SL</title>
    <link rel="shortcut icon" href="img/Logo.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Orbitron:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #0056b3;
            --secondary-color: #003366;
            --accent-color: #00a1e0;
            --dark-color: #1a1a2e;
            --light-color: #f8f9fa;
            --text-color: #333;
            --text-light: #fff;
            --danger-color: #e63946;
            --success-color: #2a9d8f;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--dark-color);
            color: var(--text-light);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
        }

        h1, h2, h3, h4 {
            font-family: 'Orbitron', sans-serif;
            font-weight: 500;
            color: var(--accent-color);
            margin-bottom: 20px;
        }

        .admin-container {
            width: 100%;
            max-width: 1200px;
            background-color: rgba(26, 26, 46, 0.8);
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            margin-top: 20px;
        }

        .welcome-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid var(--accent-color);
            padding-bottom: 20px;
        }

        .welcome-header h1 {
            font-size: 2.2rem;
            margin-bottom: 10px;
        }

        .language-form {
            margin-bottom: 20px;
            text-align: center;
        }

        .language-form label {
            font-size: 1.1rem;
            margin-right: 10px;
        }

        .language-form select {
            padding: 8px 15px;
            border-radius: 5px;
            border: 1px solid var(--accent-color);
            background-color: var(--dark-color);
            color: var(--text-light);
            font-size: 1rem;
            cursor: pointer;
        }

        .menu {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .menu-btn {
            padding: 12px 25px;
            background-color: var(--accent-color);
            color: var(--text-light);
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Orbitron', sans-serif;
            letter-spacing: 1px;
        }

        .menu-btn:hover {
            background-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 161, 224, 0.4);
        }

        .panel {
            background-color: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid rgba(0, 161, 224, 0.3);
            display: none;
        }

        .panel.active {
            display: block;
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .user-info p {
            margin-bottom: 10px;
            font-size: 1.1rem;
            line-height: 1.6;
        }

        .user-info strong {
            color: var(--accent-color);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
            background-color: rgba(0, 0, 0, 0.2);
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid rgba(0, 161, 224, 0.3);
        }

        th {
            background-color: var(--secondary-color);
            color: var(--text-light);
            font-family: 'Orbitron', sans-serif;
            letter-spacing: 1px;
        }

        tr:hover {
            background-color: rgba(0, 161, 224, 0.1);
        }

        input[type="text"],
        input[type="email"] {
            width: 100%;
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid var(--accent-color);
            background-color: rgba(0, 0, 0, 0.3);
            color: var(--text-light);
            font-size: 1rem;
        }

        input[type="submit"] {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }

        input[type="submit"]:hover {
            transform: translateY(-2px);
        }

        .btn-edit {
            background-color: var(--accent-color);
            color: var(--text-light);
        }

        .btn-edit:hover {
            background-color: var(--primary-color);
        }

        .btn-delete {
            background-color: var(--danger-color);
            color: var(--text-light);
            margin-left: 5px;
        }

        .btn-delete:hover {
            background-color: #c1121f;
        }

        .logout-section {
            text-align: center;
            margin-top: 30px;
        }

        .logout-btn {
            padding: 12px 30px;
            background-color: var(--danger-color);
            color: var(--text-light);
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Orbitron', sans-serif;
        }

        .logout-btn:hover {
            background-color: #c1121f;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(230, 57, 70, 0.4);
        }

        .status-btn {
            background-color: var(--success-color);
            color: var(--text-light);
        }

        .status-btn:hover {
            background-color: #21867a;
        }

        @media (max-width: 768px) {
            .menu {
                flex-direction: column;
                align-items: center;
            }
            
            .menu-btn {
                width: 100%;
                max-width: 300px;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>

<body>
    <div class="admin-container">
        <header class="welcome-header">
            <img src="img/Infratozz-Logo.png" alt="Infratozz Logo" style="height: 60px; margin-bottom: 15px;">
            <h1><?php echo $trans['welcome']; ?>, <?php echo htmlspecialchars($_SESSION['nombre']); ?>!</h1>
            
            <form method="post" class="language-form">
                <label for="idioma"><?php echo $trans['select_language']; ?>:
                    <select name="idioma" id="idioma" onchange="this.form.submit()">
                        <option value="es" <?php echo ($idioma === 'es') ? 'selected' : ''; ?>>Español</option>
                        <option value="en" <?php echo ($idioma === 'en') ? 'selected' : ''; ?>>English</option>
                        <option value="ca" <?php echo ($idioma === 'ca') ? 'selected' : ''; ?>>Català</option>
                    </select>
                </label>
            </form>
        </header>

        <div class="menu">
            <button class="menu-btn" onclick="toggleSection('personal_info')">
                <i class="fas fa-user-circle"></i> <?php echo $trans['personal_info']; ?>
            </button>
            
            <?php if ($is_admin): ?>
                <button class="menu-btn" onclick="toggleSection('user_list')">
                    <i class="fas fa-users-cog"></i> <?php echo $trans['user_list']; ?>
                </button>
                <button class="menu-btn status-btn" onclick="window.location.href='stat-serv.php'">
                    <i class="fas fa-server"></i> Estado del Cluster
                </button>
            <?php endif; ?>
            
            <button class="menu-btn" onclick="toggleSection('logout_section')">
                <i class="fas fa-sign-out-alt"></i> <?php echo $trans['logout_section']; ?>
            </button>
        </div>

        <!-- Sección de Información Personal -->
        <div id="personal_info" class="panel">
            <h2><i class="fas fa-id-card"></i> <?php echo $trans['user_info']; ?></h2>
            <div class="user-info">
                <p><strong><?php echo $trans['username']; ?>:</strong> <?php echo htmlspecialchars($_SESSION['usuario']); ?></p>
                <p><strong><?php echo $trans['name']; ?>:</strong> <?php echo htmlspecialchars($_SESSION['nombre']); ?></p>
                <p><strong><?php echo $trans['surname']; ?>:</strong> <?php echo htmlspecialchars($_SESSION['apellido']); ?></p>
                <p><strong><?php echo $trans['email']; ?>:</strong> <?php echo htmlspecialchars($_SESSION['email']); ?></p>
            </div>
        </div>

        <?php if ($is_admin): ?>
            <!-- Sección de Lista de Usuarios para Admin -->
            <div id="user_list" class="panel">
                <h2><i class="fas fa-users"></i> <?php echo $trans['registered_users']; ?></h2>

                <table>
                    <thead>
                        <tr>
                            <th><?php echo $trans['username']; ?></th>
                            <th><?php echo $trans['name']; ?></th>
                            <th><?php echo $trans['surname']; ?></th>
                            <th><?php echo $trans['email']; ?></th>
                            <th><?php echo $trans['actions']; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT username, nombre, apellido, email FROM usuarios";
                        $result = mysqli_query($conn, $query);
                        while ($row = mysqli_fetch_assoc($result)) {
                            echo "<tr>";
                            echo "<form method='post'>";
                            echo "<input type='hidden' name='action' value='edit'>";
                            echo "<input type='hidden' name='username' value='" . htmlspecialchars($row['username']) . "'>";
                            echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                            echo "<td><input type='text' name='nombre' value='" . htmlspecialchars($row['nombre']) . "' required></td>";
                            echo "<td><input type='text' name='apellido' value='" . htmlspecialchars($row['apellido']) . "' required></td>";
                            echo "<td><input type='email' name='email' value='" . htmlspecialchars($row['email']) . "' required></td>";
                            echo "<td>";
                            echo '<input type="submit" class="btn-edit" value="' . htmlspecialchars($trans['save']) . '">';
                            echo "</form>";
                            echo "<form method='post' style='display:inline;' onsubmit='return confirm(\"" . htmlspecialchars($trans['confirm_delete']) . "\");'>";
                            echo "<input type='hidden' name='action' value='delete'>";
                            echo "<input type='hidden' name='username' value='" . htmlspecialchars($row['username']) . "'>";
                            echo '<input type="submit" class="btn-delete" value="' . htmlspecialchars($trans['delete']) . '">';
                            echo "</form>";
                            echo "</td>";
                            echo "</tr>";
                        }
                        mysqli_free_result($result);
                        ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Botón para cerrar sesión -->
        <div id="logout_section" class="panel">
            <div class="logout-section">
                <form action="logout.php" method="post">
                    <button type="submit" class="logout-btn" onclick="return confirm('<?php echo $trans['confirm_logout']; ?>');">
                        <i class="fas fa-sign-out-alt"></i> <?php echo $trans['logout']; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleSection(sectionId) {
            // Oculta todos los paneles primero
            document.querySelectorAll('.panel').forEach(panel => {
                panel.classList.remove('active');
            });
            
            // Muestra el panel seleccionado
            const section = document.getElementById(sectionId);
            section.classList.add('active');
            
            // Desplazamiento suave al panel
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        
        // Mostrar la sección de información personal por defecto
        document.addEventListener('DOMContentLoaded', function() {
            toggleSection('personal_info');
        });
    </script>
</body>
</html>