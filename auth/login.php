<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login Admin | QoS Dashboard</title>

    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/style1.css">

    <!-- Font Awesome (ICON) -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* untuk password mata browser hilang */
        input[type="password"]::-webkit-credentials-auto-fill-button {
            display: none !important;
            visibility: hidden !important;
        }
        /* Hapus semua eye button browser */
        input::-ms-reveal,
        input::-ms-clear {
            display: none;
        }
    </style>
    <!-- SweetAlert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="login-bg">

<div class="login-wrapper">
    <div class="login-card">

        <!-- LOGO -->
        <div class="login-header">
            <div class="logo-container">
                <!-- Logo PNG asli -->
                <img src="../assets/img/komdigi.svg" class="logo-img" alt="Komdigi Logo">
                <div class="logo-text">
                    <h2>QoS Dashboard</h2>
                    <p class="login-subtitle">Balai Monitor SFR Kelas I Surabaya</p>
                </div>
            </div>
        </div>
        
        <form method="POST" action="login_process.php">

            <div class="input-group">
            <i class="fa-solid fa-user icon-left"></i>
            <input type="text" name="username" placeholder="Username" required>
            </div>

        <div class="input-group">
            <i class="fa-solid fa-lock icon-left"></i>
            <input type="password" id="password" name="password" placeholder="Password" required>
            <i class="fa-solid fa-eye icon-right" onclick="togglePassword()"></i>
        </div>

            <button type="submit" class="btn-login">
                <i class="fa fa-sign-in-alt"></i> Login
            </button>
        </form>

        <p class="login-footer">© 2026 QoS Monitoring System</p>
    </div>
</div>

<script>
function togglePassword() {
    const pwd = document.getElementById("password");
    pwd.type = pwd.type === "password" ? "text" : "password";
}
</script>

</body>
</html>
