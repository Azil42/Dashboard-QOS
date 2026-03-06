<div class="sidebar">
    <div class="sidebar-header">
        <h2>BALMON<br>Surabaya</h2>
        <p class="role">Admin Dashboard</p>
    </div>
    
    <nav class="sidebar-nav">
        <a href="index.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
            <span class="nav-icon">🏠</span>
            <span class="nav-text">Dashboard</span>
        </a>

        <a href="upload_DT.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'upload_DT.php' ? 'active' : '' ?>">
            <span class="nav-icon">🚗</span>
            <span class="nav-text">Upload File Drivetest</span>
        </a>
        
        <a href="upload_summary.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'upload_summary.php' ? 'active' : '' ?>">
            <span class="nav-icon">📤</span>
            <span class="nav-text">Upload File Data QoS </span>
        </a>
        
        <a href="kualitas_sinyal.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'kualitas_sinyal.php' ? 'active' : '' ?>">
            <span class="nav-icon">📶</span>
            <span class="nav-text">Kualitas Sinyal</span>
        </a>
        
        <a href="kualitas_layanan.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'kualitas_layanan.php' ? 'active' : '' ?>">
            <span class="nav-icon">📊</span>
            <span class="nav-text">Kualitas Layanan</span>
        </a>
        
        <a href="mapping.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'mapping.php' ? 'active' : '' ?>">
            <span class="nav-icon">🗺️</span>
            <span class="nav-text">Mapping</span>
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <a href="../auth/logout.php" class="logout-btn">
            <span class="nav-icon">🚪</span>
            <span class="nav-text">Logout</span>
        </a>
    </div>
</div>