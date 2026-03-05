<?php
session_start();
require_once 'functions.php';

if (!isset($_SESSION['userid'])) {
    header("Location: gallery.php");
    exit;
}

$message = '';
$error = '';

// Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_profile') {
    $nama_lengkap = $_POST['nama_lengkap'];
    $email = $_POST['email'];
    $alamat = $_POST['alamat'];

    $foto_profile = null;

    // Handle upload foto profile
    if (isset($_FILES['foto_profile']) && $_FILES['foto_profile']['error'] == 0) {
        $target_dir = "uploads/profiles/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_extension = pathinfo($_FILES["foto_profile"]["name"], PATHINFO_EXTENSION);
        $target_file = $target_dir . "profile_" . $_SESSION['userid'] . "_" . time() . "." . $file_extension;

        if (move_uploaded_file($_FILES["foto_profile"]["tmp_name"], $target_file)) {
            $foto_profile = $target_file;

            // Hapus foto profile lama jika ada
            if (!empty($_SESSION['foto_profile']) && file_exists($_SESSION['foto_profile'])) {
                unlink($_SESSION['foto_profile']);
            }
        }
    }

    if (updateProfile($_SESSION['userid'], $nama_lengkap, $email, $alamat, $foto_profile)) {
        // Update session
        $_SESSION['nama_lengkap'] = $nama_lengkap;
        $_SESSION['email'] = $email;
        $_SESSION['alamat'] = $alamat;
        if ($foto_profile) {
            $_SESSION['foto_profile'] = $foto_profile;
        }

        $message = "Profile berhasil diupdate!";
    } else {
        $error = "Gagal mengupdate profile.";
    }
}

// Create Album
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'create_album') {
    $nama_album = $_POST['nama_album'];
    $deskripsi = $_POST['deskripsi'];

    $result = createAlbum($nama_album, $deskripsi, $_SESSION['userid']);
    if ($result['success']) {
        $message = $result['message'];
    } else {
        $error = $result['message'];
    }
}

// Upload Foto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'upload_foto') {
    $judul_foto = $_POST['judul_foto'];
    $deskripsi = $_POST['deskripsi'];
    $albumid = $_POST['albumid'];

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $result = uploadFoto($_FILES['foto'], $judul_foto, $deskripsi, $albumid, $_SESSION['userid']);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    } else {
        $error = "Silakan pilih file foto";
    }
}

// Get user data and stats
$userData = getUserData($_SESSION['userid']);
$userStats = getUserStats($_SESSION['userid']);
$userAlbums = getUserAlbums($_SESSION['userid']);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Gallery Foto</title>
    <link rel="stylesheet" href="./assets/css/style.css">
    <style>
        /* Styling untuk menu create */
        .create-menu {
            position: absolute;
            top: 60px;
            right: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            min-width: 200px;
            z-index: 1000;
            display: none;
            overflow: hidden;
        }

        .create-menu.show {
            display: block;
        }

        .create-option {
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: background 0.2s;
            border-bottom: 1px solid #f0f0f0;
        }

        .create-option:last-child {
            border-bottom: none;
        }

        .create-option:hover {
            background: #f8f9fa;
        }

        .create-option i {
            font-size: 18px;
            width: 24px;
            text-align: center;
        }

        .create-option span {
            font-size: 14px;
            font-weight: 500;
            color: #333;
        }

        /* Styling untuk tombol Create di navbar */
        .create-btn {
            position: relative;
            background: #007bff;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: 500;
            transition: background 0.3s;
        }

        .create-btn:hover {
            background: #0056b3;
        }

        /* Styling untuk Admin Panel button di card */
        .admin-panel-btn {
            background: #dc3545;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 10px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            width: 100%;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.2);
        }

        .admin-panel-btn:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.3);
        }

        .admin-panel-btn i {
            font-size: 16px;
        }

        /* Overlay untuk create menu */
        .create-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: transparent;
            z-index: 999;
            display: none;
        }

        .create-overlay.show {
            display: block;
        }

        /* Styling untuk card album yang diperbaiki */
        .albums-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .album-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
        }

        .album-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .album-cover {
            height: 200px;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: #6c757d;
            position: relative;
            overflow: hidden;
        }

        .album-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .album-info {
            padding: 20px;
        }

        .album-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            line-height: 1.4;
        }

        .album-title a {
            color: #333;
            text-decoration: none;
            transition: color 0.3s;
        }

        .album-title a:hover {
            color: #007bff;
        }

        .album-description {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .album-meta {
            font-size: 12px;
            color: #888;
            margin-top: 5px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 15px;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #f0f0f0;
        }

        .user-name {
            font-weight: 500;
            color: #555;
            font-size: 14px;
        }

        /* Stats info */
        .album-stats {
            display: flex;
            gap: 15px;
            margin-top: 10px;
            font-size: 12px;
        }

        .stat {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #666;
        }

        /* Styling untuk role badge */
        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .role-admin {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
            border: none;
        }

        .role-user {
            background: linear-gradient(135deg, #4ecdc4 0%, #44a08d 100%);
            color: white;
            border: none;
        }

        .role-karyawan {
            background: linear-gradient(135deg, #ffd166 0%, #f9c74f 100%);
            color: #333;
            border: none;
        }

        /* Profile card styling */
        .profile-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            position: relative;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
        }

        .profile-photo-container {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid #f0f0f0;
            box-shadow: 0 4px 15px rgba(128, 0, 32, 0.2);
        }

        .profile-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .profile-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: bold;
        }

        .profile-info h3 {
            margin: 0 0 5px 0;
            color: #333;
            font-size: 24px;
        }

        .profile-info p {
            margin: 5px 0;
            color: #666;
        }

        .profile-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
        }

        .detail-item {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }

        .detail-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .detail-item strong {
            color: #333;
            display: block;
            margin-bottom: 5px;
        }

        .detail-item span {
            color: #666;
        }

        /* User avatar container */
        .user-avatar-container {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid #f0f0f0;
        }

        .user-avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        @media (max-width: 768px) {
            .albums-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 15px;
            }
            
            .create-menu {
                right: 10px;
                min-width: 180px;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .albums-grid {
                grid-template-columns: 1fr;
            }
            
            .album-cover {
                height: 180px;
            }
        }

        /* Edit profile form */
        .edit-profile-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            margin-top: 30px;
        }

        .edit-profile-section h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #333;
            font-size: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .profile-form button[type="submit"] {
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .profile-form button[type="submit"]:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
        }
    </style>
</head>

<body>
    <header>
        <div class="container">
            <div class="navbar">
                <a href="gallery.php" class="logo">GF</a>

                <form class="search-form" method="GET" action="search.php">
                    <input type="text" name="keyword" placeholder="Cari album atau foto..." 
                           value="<?php echo isset($_GET['keyword']) ? htmlspecialchars($_GET['keyword']) : ''; ?>">
                </form>

                <div class="nav-links">
                    <?php if (isset($_SESSION['userid'])): ?>
                        <a href="gallery.php">Beranda</a>
                        <a href="gf.php">Album</a>
                        <a href="dashboard.php" class="active">Dashboard</a>
                        <!-- Tombol Create Terpadu -->
                        <a href="#" class="create-btn" onclick="openCreateMenu()">Create</a>
                        <a href="dashboard.php?logout=1">Logout (<?php echo $_SESSION['username']; ?>)</a>
                    <?php else: ?>
                        <a href="gallery.php">Beranda</a>
                        <a href="gf.php">Album</a>
                        <a href="#" onclick="openModal('loginModal')">Login</a>
                        <a href="#" onclick="openModal('registerModal')">Daftar</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Menu Create Popup -->
    <div class="create-menu" id="createMenu">
        <div class="create-option" onclick="openModal('createAlbumModal'); closeCreateMenu();">
            <i>📁</i>
            <span>Buat Album</span>
        </div>
        <div class="create-option" onclick="openModal('uploadFotoModal'); closeCreateMenu();">
            <i>📷</i>
            <span>Upload Foto</span>
        </div>
    </div>
    <div class="create-overlay" id="createOverlay" onclick="closeCreateMenu()"></div>

    <div class="container main-content">
        <?php if (!empty($message)): ?>
            <div class="alert alert-success" style="margin: 20px 0; padding: 12px 20px; border-radius: 8px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error" style="margin: 20px 0; padding: 12px 20px; border-radius: 8px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <h2 class="section-title">Dashboard - <?php echo htmlspecialchars($userData['nama_lengkap']); ?></h2>

        <div class="dashboard-grid">
            <!-- Profile Info Card -->
            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-photo-container">
                        <?php if (!empty($userData['foto_profile']) && file_exists($userData['foto_profile'])): ?>
                            <img src="<?php echo $userData['foto_profile']; ?>" 
                                 alt="Profile Photo" 
                                 class="profile-img">
                        <?php else: ?>
                            <div class="profile-placeholder">
                                <?php echo strtoupper(substr($userData['nama_lengkap'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="profile-info">
                        <h3><?php echo htmlspecialchars($userData['nama_lengkap']); ?></h3>
                        <p>@<?php echo htmlspecialchars($userData['username']); ?></p>
                        <span class="role-badge role-<?php echo $userData['role']; ?>">
                            <?php echo ucfirst($userData['role']); ?>
                        </span>
                    </div>
                </div>

                <div class="profile-details">
                    <div class="detail-item">
                        <strong>Email:</strong>
                        <span><?php echo htmlspecialchars($userData['email']); ?></span>
                    </div>
                    <div class="detail-item">
                        <strong>Alamat:</strong>
                        <span><?php echo htmlspecialchars($userData['alamat']); ?></span>
                    </div>
                    <div class="detail-item">
                        <strong>Member sejak:</strong>
                        <span><?php echo date('d M Y', strtotime($userData['tanggal_dibuat'])); ?></span>
                    </div>
                    
                    <!-- Button Admin Panel untuk Admin -->
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #e9ecef;">
                            <a href="admin_panel.php" class="admin-panel-btn">
                                <i class="fas fa-cogs"></i> Admin Panel
                            </a>
                            <p style="font-size: 12px; color: #666; text-align: center; margin-top: 8px;">
                                Manage users, albums, and system settings
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Statistics Card -->
            <div class="profile-card">
                <h3 style="margin-top: 0; margin-bottom: 20px; color: #333;">Statistik</h3>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                    <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                        <div style="font-size: 24px; font-weight: bold; color: #007bff; margin-bottom: 5px;">
                            <?php echo $userStats['total_albums']; ?>
                        </div>
                        <div style="font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">
                            Album
                        </div>
                    </div>
                    <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                        <div style="font-size: 24px; font-weight: bold; color: #28a745; margin-bottom: 5px;">
                            <?php echo $userStats['total_fotos']; ?>
                        </div>
                        <div style="font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">
                            Foto
                        </div>
                    </div>
                    <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                        <div style="font-size: 24px; font-weight: bold; color: #ffc107; margin-bottom: 5px;">
                            <?php echo $userStats['total_likes']; ?>
                        </div>
                        <div style="font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">
                            Like Diberikan
                        </div>
                    </div>
                    <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                        <div style="font-size: 24px; font-weight: bold; color: #17a2b8; margin-bottom: 5px;">
                            <?php echo $userStats['total_fotos_liked']; ?>
                        </div>
                        <div style="font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">
                            Like Diterima
                        </div>
                    </div>
                </div>
                
                <!-- Admin Statistics untuk Admin -->
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e9ecef;">
                        <h4 style="margin-bottom: 15px; color: #333; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-user-shield"></i> Admin Tools
                        </h4>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                            <a href="admin_panel.php?tab=users" style="text-decoration: none;">
                                <div style="text-align: center; padding: 12px; background: #d1ecf1; border-radius: 8px; transition: all 0.3s ease; cursor: pointer;">
                                    <div style="font-size: 20px; color: #17a2b8; margin-bottom: 5px;">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div style="font-size: 11px; color: #0c5460; text-transform: uppercase; letter-spacing: 0.5px;">
                                        Users
                                    </div>
                                </div>
                            </a>
                            <a href="admin_panel.php?tab=reports" style="text-decoration: none;">
                                <div style="text-align: center; padding: 12px; background: #f8d7da; border-radius: 8px; transition: all 0.3s ease; cursor: pointer;">
                                    <div style="font-size: 20px; color: #dc3545; margin-bottom: 5px;">
                                        <i class="fas fa-chart-bar"></i>
                                    </div>
                                    <div style="font-size: 11px; color: #721c24; text-transform: uppercase; letter-spacing: 0.5px;">
                                        Reports
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- User Albums Section -->
        <div class="section">
            <h3>Album Saya</h3>
            <?php if (empty($userAlbums)): ?>
                <div style="text-align: center; padding: 40px; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h3>Belum ada album yang dibuat</h3>
                    <p>Mulai dengan membuat album pertama Anda!</p>
                    <a href="#" onclick="openModal('createAlbumModal')" 
                       style="background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin-top: 10px; text-decoration: none; display: inline-block;">
                        Buat Album Pertama
                    </a>
                </div>
            <?php else: ?>
                <div class="albums-grid">
                    <?php foreach ($userAlbums as $album): 
                        // Ambil foto pertama untuk cover jika ada
                        $cover = null;
                        $pdo = getDBConnection();
                        $coverQuery = $pdo->prepare("SELECT lokasifile FROM foto WHERE albumid = ? ORDER BY fotoid ASC LIMIT 1");
                        $coverQuery->execute([$album['albumid']]);
                        $coverResult = $coverQuery->fetch(PDO::FETCH_ASSOC);
                        $cover = $coverResult ? $coverResult['lokasifile'] : null;
                        
                        // Hitung jumlah foto dalam album
                        $countQuery = $pdo->prepare("SELECT COUNT(*) as jumlah_foto FROM foto WHERE albumid = ?");
                        $countQuery->execute([$album['albumid']]);
                        $countResult = $countQuery->fetch(PDO::FETCH_ASSOC);
                        $jumlah_foto = $countResult ? $countResult['jumlah_foto'] : 0;
                    ?>
                        
                        <div class="album-card">
                            <a href="album.php?album_id=<?php echo $album['albumid']; ?>">
                                <div class="album-cover">
                                    <?php if ($cover && file_exists($cover)): ?>
                                        <img src="<?php echo htmlspecialchars($cover); ?>" 
                                             alt="<?php echo htmlspecialchars($album['nama_album']); ?>">
                                    <?php else: ?>
                                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                            <span style="font-size: 48px;">📁</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </a>
                            <div class="album-info">
                                <div class="user-info">
                                    <div class="user-avatar-container">
                                        <?php if ($userData['foto_profile'] && file_exists($userData['foto_profile'])): ?>
                                            <img src="<?php echo htmlspecialchars($userData['foto_profile']); ?>" 
                                                 alt="<?php echo htmlspecialchars($userData['nama_lengkap']); ?>" 
                                                 class="user-avatar-img">
                                        <?php else: ?>
                                            <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px;">
                                                <?php echo strtoupper(substr($userData['nama_lengkap'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="user-name"><?php echo htmlspecialchars($userData['username']); ?></span>
                                </div>
                                
                                <div class="album-title">
                                    <a href="album.php?album_id=<?php echo $album['albumid']; ?>">
                                        <?php echo htmlspecialchars($album['nama_album']); ?>
                                    </a>
                                </div>
                                
                                <div class="album-description">
                                    <?php echo htmlspecialchars($album['deskripsi'] ?: 'Tidak ada deskripsi'); ?>
                                </div>
                                
                                <div class="album-meta">
                                    <span><i>📅</i> <?php echo date('d M Y', strtotime($album['tanggal'])); ?></span>
                                </div>
                                
                                <div class="album-stats">
                                    <div class="stat">
                                        <span style="color: #007bff; font-weight: bold;">📷</span>
                                        <span><?php echo $jumlah_foto; ?> foto</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <br><br>

        <!-- Edit Profile Form -->
        <div class="edit-profile-section">
            <h3>Edit Profile</h3>
            <form method="POST" enctype="multipart/form-data" class="profile-form">
                <input type="hidden" name="action" value="update_profile">

                <div class="form-row">
                    <div class="form-group">
                        <label for="nama_lengkap">Nama Lengkap</label>
                        <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-control"
                            value="<?php echo htmlspecialchars($userData['nama_lengkap']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control"
                            value="<?php echo htmlspecialchars($userData['email']); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="alamat">Alamat</label>
                    <textarea id="alamat" name="alamat" class="form-control"><?php echo htmlspecialchars($userData['alamat']); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="foto_profile">Foto Profile</label>
                    <input type="file" id="foto_profile" name="foto_profile" class="form-control" accept="image/*">
                    <small>Biarkan kosong jika tidak ingin mengubah foto profile</small>
                </div>

                <button type="submit" class="btn">Update Profile</button>
            </form>
            
            <!-- Admin Panel Button untuk Admin di bagian bawah edit profile -->
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e9ecef;">
                    <h4 style="margin-bottom: 15px; color: #333; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-user-shield"></i> Administrator Controls
                    </h4>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a href="admin_panel.php" class="admin-panel-btn" style="background: #dc3545;">
                            <i class="fas fa-cogs"></i> Go to Admin Panel
                        </a>
                        <a href="admin_panel.php?tab=users" style="text-decoration: none;">
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #007bff; transition: all 0.3s ease; cursor: pointer;">
                                <div style="font-weight: 500; color: #333;">Manage Users</div>
                                <div style="font-size: 12px; color: #666; margin-top: 5px;">Add, edit, or remove users</div>
                            </div>
                        </a>
                        <a href="admin_panel.php?tab=settings" style="text-decoration: none;">
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745; transition: all 0.3s ease; cursor: pointer;">
                                <div style="font-weight: 500; color: #333;">System Settings</div>
                                <div style="font-size: 12px; color: #666; margin-top: 5px;">Configure application settings</div>
                            </div>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Buat Album -->
    <div id="createAlbumModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Buat Album Baru</h3>
                <button class="close-modal" onclick="closeModal('createAlbumModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_album">

                <div class="form-group">
                    <label for="nama_album">Nama Album</label>
                    <input type="text" id="nama_album" name="nama_album" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="deskripsi">Deskripsi</label>
                    <textarea id="deskripsi" name="deskripsi" class="form-control"></textarea>
                </div>

                <button type="submit" class="btn">Buat Album</button>
            </form>
        </div>
    </div>

    <!-- Modal Upload Foto -->
    <div id="uploadFotoModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Upload Foto Baru</h3>
                <button class="close-modal" onclick="closeModal('uploadFotoModal')">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_foto">

                <div class="form-group">
                    <label for="judul_foto">Judul Foto</label>
                    <input type="text" id="judul_foto" name="judul_foto" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="deskripsi">Deskripsi</label>
                    <textarea id="deskripsi" name="deskripsi" class="form-control"></textarea>
                </div>

                <div class="form-group">
                    <label for="albumid">Album</label>
                    <select id="albumid" name="albumid" class="form-control" required>
                        <option value="">Pilih Album</option>
                        <?php foreach ($userAlbums as $album): ?>
                            <option value="<?php echo $album['albumid']; ?>">
                                <?php echo htmlspecialchars($album['nama_album']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="foto">Pilih Foto</label>
                    <input type="file" id="foto" name="foto" class="form-control" accept="image/*" required>
                </div>

                <button type="submit" class="btn">Upload Foto</button>
            </form>
        </div>
    </div>

    <!-- Modal Register -->
    <div id="registerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Daftar Akun Baru</h3>
                <button class="close-modal" onclick="closeModal('registerModal')">&times;</button>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="register">

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="nama_lengkap">Nama Lengkap</label>
                    <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="alamat">Alamat</label>
                    <textarea id="alamat" name="alamat" class="form-control"></textarea>
                </div>

                <button type="submit" class="btn">Daftar</button>
            </form>
        </div>
    </div>

    <!-- Modal Login -->
    <div id="loginModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Login</h3>
                <button class="close-modal" onclick="closeModal('loginModal')">&times;</button>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="login">

                <div class="form-group">
                    <label for="login_username">Username</label>
                    <input type="text" id="login_username" name="username" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="login_password">Password</label>
                    <input type="password" id="login_password" name="password" class="form-control" required>
                </div>

                <button type="submit" class="btn">Login</button>
            </form>
        </div>
    </div>

    <script>
        // Variabel untuk melacak status menu create
        let createMenuOpen = false;

        // Fungsi untuk membuka menu create
        function openCreateMenu() {
            const createMenu = document.getElementById('createMenu');
            const createOverlay = document.getElementById('createOverlay');
            
            if (createMenuOpen) {
                closeCreateMenu();
                return;
            }
            
            createMenu.classList.add('show');
            createOverlay.classList.add('show');
            createMenuOpen = true;
            
            // Tambahkan event listener untuk esc key
            document.addEventListener('keydown', handleEscapeKey);
        }

        // Fungsi untuk menutup menu create
        function closeCreateMenu() {
            const createMenu = document.getElementById('createMenu');
            const createOverlay = document.getElementById('createOverlay');
            
            createMenu.classList.remove('show');
            createOverlay.classList.remove('show');
            createMenuOpen = false;
            
            // Hapus event listener
            document.removeEventListener('keydown', handleEscapeKey);
        }

        // Fungsi untuk menangani tombol escape
        function handleEscapeKey(event) {
            if (event.key === 'Escape') {
                if (createMenuOpen) {
                    closeCreateMenu();
                }
            }
        }

        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal ketika klik di luar
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let i = 0; i < modals.length; i++) {
                if (event.target == modals[i]) {
                    modals[i].style.display = 'none';
                }
            }
            
            // Jika klik di luar create menu
            const createMenu = document.getElementById('createMenu');
            const createBtn = document.querySelector('.create-btn');
            if (createMenuOpen && 
                !createMenu.contains(event.target) && 
                !createBtn.contains(event.target)) {
                closeCreateMenu();
            }
        };

        // Auto-hide alerts setelah 5 detik
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>