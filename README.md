# InfratozzSL 🚀

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-7.4+-blue.svg)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-orange.svg)](https://www.mysql.com/)

**InfratozzSL** es un proyecto web de ASIX que proporciona una plataforma de gestión de usuarios, estadísticas y panel de control, desarrollado con PHP, HTML y MySQL.

---

## 📂 Estructura del proyecto

```
InfratozzSL/
├── index.html           # Página principal
├── login.html           # Página de inicio de sesión
├── signup.html          # Página de registro
├── home.php             # Panel principal después del login
├── logout.php           # Script de cierre de sesión
├── valida.php           # Validación de login
├── conexio.php          # Conexión a la base de datos
├── stat-serv.php        # Estadísticas del servidor
├── translations.php     # Gestión de traducciones
├── usuarios_backup.sql  # Backup de la base de datos
├── img/                 # Carpeta con imágenes del proyecto
└── README.md            # Este archivo
```

---

## 🛠 Tecnologías utilizadas

- **Frontend**: HTML, CSS, JavaScript (según el proyecto)
- **Backend**: PHP 7.4+
- **Base de datos**: MySQL / MariaDB
- **Servidor**: Apache / Nginx / XAMPP

---

## ⚙️ Requisitos

- Servidor web compatible con PHP
- PHP >= 7.4
- MySQL o MariaDB
- Acceso para importar `usuarios_backup.sql`

---

## 🚀 Instalación

1. Clona el repositorio:

```bash
git clone https://github.com/AnasXokorro/InfratozzSL.git
cd InfratozzSL
```

2. Configura la conexión a la base de datos editando `conexio.php`:

```php
<?php
$host = "localhost";
$user = "tu_usuario";
$pass = "tu_contraseña";
$db   = "nombre_base_datos";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}
?>
```

3. Importa la base de datos:

```bash
mysql -u tu_usuario -p nombre_base_datos < usuarios_backup.sql
```

4. Inicia tu servidor web y abre `index.html` o `login.html` en el navegador.

---

## 🔒 Seguridad

- Cambia las credenciales de la base de datos antes de producción.
- Asegúrate de que `usuarios_backup.sql` no sea accesible públicamente.
- Valida y sanitiza entradas de usuario en `valida.php` y `signup.html` para prevenir inyecciones SQL.

---

## 📌 Uso

1. Accede desde `login.html`.
2. Regístrate en `signup.html` si eres nuevo.
3. Una vez logueado, serás redirigido a `home.php`.
4. Consulta estadísticas con `stat-serv.php` y administra usuarios.

---

## ✨ Capturas de pantalla

![Login](img/login.png)
*Página de inicio de sesión*

![Home](img/home.png)
*Panel principal de usuario*

---

## 🤝 Contribuciones

Si quieres contribuir:

1. Haz un fork del repositorio.
2. Crea un branch: `git checkout -b feature/nueva-funcionalidad`
3. Realiza tus cambios y haz commit: `git commit -m 'Añadir nueva funcionalidad'`
4. Haz push a tu branch: `git push origin feature/nueva-funcionalidad`
5. Abre un Pull Request.

---

## 📄 Licencia

MIT License © 2026 ASIX Infratozz SL

---

## 🔗 Enlaces útiles

- [Documentación PHP](https://www.php.net/manual/es/)
- [MySQL](https://dev.mysql.com/doc/)
- [GitHub](https://github.com/)
