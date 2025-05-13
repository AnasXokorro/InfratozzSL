<?php
session_start();
include 'conexio.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Inicio de sesión
    if (isset($_POST['us']) && isset($_POST['pw'])) {
        $username = mysqli_real_escape_string($conn, $_POST['us']);
        $password = $_POST['pw'];

        $query = "SELECT * FROM usuarios WHERE username = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['usuario'] = $user['username'];
            $_SESSION['language'] = $user['preferred_language'];
            $_SESSION['nombre'] = $user['nombre'];
            $_SESSION['apellido'] = $user['apellido'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['is_admin'] = $user['is_admin'];
            header("Location: home.php");  // Redirige a la página principal
            exit();
        } else {
            echo "Usuario o contraseña incorrectos. <a href='signup.html'>Registrarse</a>";
        }
        mysqli_stmt_close($stmt);

    // Registro de usuario
    } elseif (isset($_POST['register']) && isset($_POST['user']) && isset($_POST['passwd'])) {
        $username = mysqli_real_escape_string($conn, $_POST['user']);
        $password = password_hash($_POST['passwd'], PASSWORD_BCRYPT);
        $nombre = mysqli_real_escape_string($conn, $_POST['nombre'] ?? '');
        $apellido = mysqli_real_escape_string($conn, $_POST['apellido'] ?? '');
        $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
        $language = mysqli_real_escape_string($conn, $_POST['language'] ?? 'ca');

        $query = "INSERT INTO usuarios (username, nombre, apellido, email, password, preferred_language) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ssssss", $username, $nombre, $apellido, $email, $password, $language);

        if (mysqli_stmt_execute($stmt)) {
            echo "<div>Usuario registrado exitosamente. <a href='login.html'><button>Iniciar sesión</button></a></div> <style> body { display: grid; place-content: center;  text-align: center; font-family: Arial, sans-serif; background-color: #333;} div{ color: black; background-color: white; margin-top: 200px; border-radius: 15px; padding: 25px;}</style>";
        } else {
            echo "Error al registrar el usuario: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

mysqli_close($conn);
?>
