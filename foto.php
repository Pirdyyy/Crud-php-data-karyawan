<?php
session_start();
require_once 'functions.php';

if (!isset($_GET['foto_id'])) {
    header("Location: gallery.php");
    exit;
}

$fotoid = $_GET['foto_id'];

// Dapatkan data foto
$pdo = getDBConnection();
$sql = "SELECT f.*, u.username, u.userid as foto_userid, u.foto_profile as uploader_foto_profile, a.nama_album, a.albumid 
        FROM foto f 
        JOIN user u ON f.userid = u.userid 
        JOIN album a ON f.albumid = a.albumid 
        WHERE f.fotoid = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$fotoid]);
$foto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$foto) {
    echo "Error: Foto tidak ditemukan!";
    exit;
}

// Dapatkan jumlah like dan komentar
$likes = getLikesCount($fotoid);
$comments = getCommentsOnFoto($fotoid);
$isLiked = isset($_SESSION['userid']) ? isLiked($fotoid, $_SESSION['userid']) : false;

// Proses login dari modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    if (loginUser($username, $password)) {
        header("Location: foto.php?foto_id=" . $fotoid);
        exit;
    } else {
        $login_error = "Login gagal. Periksa username dan password Anda.";
    }
}

// Proses registrasi dari modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $success = registerUser(
        $_POST['username'],
        $_POST['password'],
        $_POST['email'],
        $_POST['nama_lengkap'],
        $_POST['alamat']
    );
    if ($success) {
        $register_success = "Registrasi berhasil! Silakan login.";
    } else {
        $register_error = "Registrasi gagal. Username atau email mungkin sudah digunakan.";
    }
}

// Proses download foto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'download_foto') {
    $filepath = $foto['lokasifile'];
    
    if (file_exists($filepath)) {
        // Set headers untuk download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        flush(); // Flush system output buffer
        readfile($filepath);
        exit;
    } else {
        $error = "File foto tidak ditemukan!";
    }
}

// Proses form actions lainnya
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'like_foto':
                if (isset($_SESSION['userid'])) {
                    likeFoto($fotoid, $_SESSION['userid']);
                    header("Location: foto.php?foto_id=" . $fotoid);
                    exit;
                }
                break;

            case 'add_comment':
                if (isset($_SESSION['userid'])) {
                    addComment($fotoid, $_SESSION['userid'], $_POST['isi_komen']);
                    header("Location: foto.php?foto_id=" . $fotoid);
                    exit;
                }
                break;

            case 'delete_comment':
                if (isset($_SESSION['userid'])) {
                    deleteComment($_POST['komentarid'], $_SESSION['userid'], $_SESSION['role']);
                    header("Location: foto.php?foto_id=" . $fotoid);
                    exit;
                }
                break;

            case 'delete_foto':
                if (isset($_SESSION['userid'])) {
                    $success = deleteFoto($fotoid, $_SESSION['userid'], $_SESSION['role']);
                    if ($success) {
                        header("Location: album.php?album_id=" . $foto['albumid']);
                        exit;
                    }
                }
                break;

            case 'update_foto':
                if (isset($_SESSION['userid']) && ($_SESSION['userid'] == $foto['foto_userid'] || $_SESSION['role'] == 'admin' || $_SESSION['role'] == 'karyawan')) {
                    // Update foto data
                    $judul = $_POST['judul_foto'];
                    $deskripsi = $_POST['deskripsi'];
                    
                    $sql = "UPDATE foto SET judul_foto = ?, deskripsi = ? WHERE fotoid = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$judul, $deskripsi, $fotoid]);
                    
                    header("Location: foto.php?foto_id=" . $fotoid);
                    exit;
                }
                break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($foto['judul_foto']); ?> - Gallery Foto</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <style>
        .foto-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .foto-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .foto-image-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 30px;
            position: relative;
        }
        
        .foto-image {
            width: 100%;
            max-height: 70vh;
            object-fit: contain;
            display: block;
        }
        
        .foto-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 40px;
            margin-top: 30px;
        }
        
        .foto-info {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .foto-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
            line-height: 1.3;
        }
        
        .foto-meta {
            display: flex;
            gap: 15px;
            margin: 20px 0;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .foto-meta span {
            background: #f8f9fa;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .foto-description {
            line-height: 1.7;
            color: #555;
            margin: 25px 0;
            padding: 20px;
            background: #fafbfc;
            border-radius: 12px;
            border-left: 4px solid #007bff;
            font-size: 16px;
        }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            margin: 25px 0;
            flex-wrap: wrap;
            padding: 20px 0;
            border-top: 1px solid #eee;
            border-bottom: 1px solid #eee;
        }
        
        .btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .like-btn {
            background: <?php echo $isLiked ? '#dc3545' : '#c7c0c0ff'; ?>;
            color: <?php echo $isLiked ? '#fff' : '#242222ff'; ?>;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .like-btn:hover {
            color: <?php echo $isLiked ? '#c82332' : '#242222ff'; ?>;
            transform: translateY(-2px);
        }
        
        .comments-section {
            margin-top: 40px;
        }
        
        .comments-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .comments-count {
            background: #007bff;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .comment-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        
        .comment-form textarea {
            width: 100%;
            height: 100px;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            resize: vertical;
            font-family: inherit;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .comment-form textarea:focus {
            outline: none;
            border-color: #007bff;
        }
        
        .comments-list {
            max-height: 500px;
            overflow-y: auto;
            padding-right: 10px;
        }
        
        .comment {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 16px;
            border: 1px solid #f0f0f0;
            transition: transform 0.2s ease;
        }
        
        .comment:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .comment-author {
            font-weight: 600;
            color: #333;
            font-size: 15px;
        }
        
        .comment-date {
            font-size: 12px;
            color: #999;
        }
        
        .comment-content {
            color: #555;
            line-height: 1.6;
            font-size: 14px;
        }
        
        .delete-comment-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            margin-top: 8px;
            transition: background 0.3s ease;
        }
        
        .delete-comment-btn:hover {
            background: #c82332;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
            padding: 10px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            text-decoration: none;
            background: #007bff;
            color: white;
            transform: translateX(-5px);
        }
        
        .sidebar {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            height: fit-content;
            position: sticky;
            top: 20px;
        }
        
        .sidebar-section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .sidebar-section:last-child {
            margin-bottom: 0;
            border-bottom: none;
        }
        
        .sidebar-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .album-info {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .album-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 10px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
        }
        
        .album-link:hover {
            background: #007bff;
            color: white;
            transform: translateX(5px);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .stat-number {
            font-size: 20px;
            font-weight: bold;
            color: #007bff;
            display: block;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        /* Download Button Styles */
        .download-overlay {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .foto-image-container:hover .download-overlay {
            opacity: 1;
        }
        
        .download-btn {
            background: rgba(255, 255, 255, 0.9);
            color: #333;
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 18px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .download-btn:hover {
            background: white;
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }
        
        .download-btn i {
            color: #007bff;
        }
        
        .file-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
            font-size: 14px;
        }
        
        .file-info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .file-info-item:last-child {
            margin-bottom: 0;
        }
        
        .file-label {
            color: #666;
            font-weight: 500;
        }
        
        .file-value {
            color: #333;
            font-weight: 600;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .foto-content {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .foto-image {
                max-height: 50vh;
            }
            
            .sidebar {
                position: static;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .download-overlay {
                opacity: 1;
                top: 15px;
                right: 15px;
            }
            
            .download-btn {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
        }
    </style>
</head>
<body class="<?php echo isset($_SESSION['userid']) ? 'logged-in' : ''; ?>">
    <header>
        <div class="container">
            <div class="navbar">
                <a href="gallery.php" class="logo">GF</a>

                <form class="search-form" method="GET" action="search.php">
                    <input type="text" name="keyword" placeholder="Cari album atau foto...">
                </form>

                <div class="nav-links">
                    <?php if (isset($_SESSION['userid'])): ?>
                        <a href="gallery.php">Beranda</a>
                        <a href="dashboard.php">Dashboard</a>
                        <a href="album.php?album_id=<?php echo $foto['albumid']; ?>">Kembali ke Album</a>
                        <a href="gallery.php?logout=1">Logout (<?php echo $_SESSION['username']; ?>)</a>
                    <?php else: ?>
                        <a href="#" onclick="openModal('loginModal')">Login</a>
                        <a href="#" onclick="openModal('registerModal')">Daftar</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <div class="container main-content">
        <a href="album.php?album_id=<?php echo $foto['albumid']; ?>" class="back-btn">
            ← Kembali ke Album
                    </a>

        <div class="foto-container">
            <!-- Foto Besar di Atas -->
            <div class="foto-image-container">
                <img src="<?php echo $foto['lokasifile']; ?>" 
                     alt="<?php echo htmlspecialchars($foto['judul_foto']); ?>" 
                     class="foto-image">
                
                <!-- Download Overlay -->
                <div class="download-overlay">
                    <!-- Download Button Form -->
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="download_foto">
                        <button type="submit" class="download-btn" title="Download Foto">
                            <i class="fas fa-download"></i>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Konten di Bawah -->
            <div class="foto-content">
                <!-- Bagian Kiri: Deskripsi dan Komentar -->
                <div class="foto-info">
                    <h1 class="foto-title"><?php echo htmlspecialchars($foto['judul_foto']); ?></h1>
                    
                    <div class="foto-meta">
                        <span>👤 <?php echo htmlspecialchars($foto['username']); ?></span>
                        <span>📅 <?php echo date('d M Y H:i', strtotime($foto['tanggal_unggah'])); ?></span>
                        <span> <?php echo $likes; ?> <i class="fa-solid fa-heart"></i></span>
                        <span>💬 <?php echo count($comments); ?> Komentar</span>
                    </div>

                    <?php if (!empty($foto['deskripsi'])): ?>
                        <div class="foto-description">
                            <?php echo nl2br(htmlspecialchars($foto['deskripsi'])); ?>
                        </div>
                    <?php endif; ?>

                    <!-- File Information -->
                    <div class="file-info">
                        <div class="file-info-item">
                            <span class="file-label">Nama File:</span>
                            <span class="file-value"><?php echo basename($foto['lokasifile']); ?></span>
                        </div>
                        <?php if (file_exists($foto['lokasifile'])): ?>
                            <?php 
                            $file_size = filesize($foto['lokasifile']);
                            $file_size_formatted = formatFileSize($file_size);
                            ?>
                            <div class="file-info-item">
                                <span class="file-label">Ukuran File:</span>
                                <span class="file-value"><?php echo $file_size_formatted; ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="file-info-item">
                            <span class="file-label">Format:</span>
                            <span class="file-value"><?php echo strtoupper(pathinfo($foto['lokasifile'], PATHINFO_EXTENSION)); ?></span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <!-- Download Button -->
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="download_foto">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-download"></i>
                            </button>
                        </form>
                        
                        <!-- Like Button -->
                        <?php if (isset($_SESSION['userid'])): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="like_foto">
                                <button type="submit" class="like-btn">
                                    <i class="fa-solid fa-heart"></i><?php echo $isLiked ? '' : ''; ?> (<?php echo $likes; ?>)
                                </button>
                            </form>
                        <?php else: ?>
                            <button class="like-btn" style="background: #6c757d;" onclick="openModal('loginModal')">
                                 <?php echo $likes; ?> <i class="fa-solid fa-heart"></i>
                            </button>
                        <?php endif; ?>

                        <!-- Edit & Delete Buttons -->
                        <?php if (isset($_SESSION['userid']) && ($_SESSION['userid'] == $foto['foto_userid'] || $_SESSION['role'] == 'admin' || $_SESSION['role'] == 'karyawan')): ?>
                            <button class="btn" onclick="openModal('editFotoModal')">✏️</button>
                            
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="delete_foto">
                                <button type="submit" class="btn btn-secondary" 
                                        onclick="return confirm('Yakin ingin menghapus foto ini?')">
                                    🗑️
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <!-- Komentar Section -->
                    <div class="comments-section">
                        <div class="comments-header">
                            <h3 style="margin: 0;">Komentar</h3>
                            <span class="comments-count"><?php echo count($comments); ?></span>
                        </div>

                        <!-- Form Tambah Komentar -->
                        <?php if (isset($_SESSION['userid'])): ?>
                            <form method="POST" class="comment-form">
                                <input type="hidden" name="action" value="add_comment">
                                <textarea name="isi_komen" placeholder="Tulis komentar Anda di sini..." required></textarea>
                                <button type="submit" class="btn" style="margin-top: 15px; width: 100%;">Kirim Komentar</button>
                            </form>
                        <?php else: ?>
                            <div class="comment-form" style="text-align: center;">
                                <p style="color: #666; margin: 0;">
                                    Silakan <a href="#" onclick="openModal('loginModal')" style="color: #007bff; font-weight: 500;">login</a> untuk menambahkan komentar.
                                </p>
                            </div>
                        <?php endif; ?>

                        <!-- Daftar Komentar -->
                        <div class="comments-list">
                            <?php if (count($comments) > 0): ?>
                                <?php foreach ($comments as $comment): ?>
                                    <div class="comment">
                                        <div class="comment-header">
                                            <span class="comment-author"><?php echo htmlspecialchars($comment['username']); ?></span>
                                            <span class="comment-date"><?php echo date('d M Y H:i', strtotime($comment['tanggal_komen'])); ?></span>
                                        </div>
                                        <div class="comment-content"><?php echo htmlspecialchars($comment['isi_komen']); ?></div>
                                        
                                        <?php if (isset($_SESSION['userid']) && ($_SESSION['userid'] == $comment['userid'] || $_SESSION['role'] == 'admin' || $_SESSION['role'] == 'karyawan')): ?>
                                            <form method="POST" style="margin-top: 10px;">
                                                <input type="hidden" name="action" value="delete_comment">
                                                <input type="hidden" name="komentarid" value="<?php echo $comment['komentarid']; ?>">
                                                <button type="submit" class="delete-comment-btn" 
                                                        onclick="return confirm('Yakin ingin menghapus komentar ini?')">
                                                    Hapus Komentar
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="text-align: center; padding: 40px 20px; color: #999;">
                                    <p style="font-style: italic; margin: 0;">Belum ada komentar. Jadilah yang pertama berkomentar!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Kanan -->
                <div class="sidebar">
                    <div class="sidebar-section">
                        <h4 class="sidebar-title">📁 Album</h4>
                        <div class="album-info">
                            <a href="album.php?album_id=<?php echo $foto['albumid']; ?>" class="album-link">
                                <span><?php echo htmlspecialchars($foto['nama_album']); ?></span>
                            </a>
                        </div>
                    </div>

                    <div class="sidebar-section">
                        <h4 class="sidebar-title">📊 Statistik</h4>
                        <div class="stats-grid">
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $likes; ?></span>
                                <span class="stat-label">Likes</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo count($comments); ?></span>
                                <span class="stat-label">Komentar</span>
                            </div>
                        </div>
                    </div>

                    <div class="sidebar-section">
                        <h4 class="sidebar-title">📥 Download</h4>
                        <div style="text-align: center;">
                            <form method="POST" style="margin-bottom: 10px;">
                                <input type="hidden" name="action" value="download_foto">
                                <button type="submit" class="btn btn-success" style="width: 100%;">
                                    <i class="fas fa-download"></i> Download Foto
                                </button>
                            </form>
                            <small style="color: #666; display: block; margin-top: 10px;">
                                Klik untuk mengunduh foto ke perangkat Anda
                            </small>
                        </div>
                    </div>

                    <div class="sidebar-section">
                        <h4 class="sidebar-title">👤 Uploader</h4>
                        <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: #f8f9fa; border-radius: 10px;">
                            <?php if ($foto['uploader_foto_profile'] && file_exists($foto['uploader_foto_profile'])): ?>
                                <div style="width: 40px; height: 40px; border-radius: 50%; overflow: hidden; border: 2px solid #007bff;">
                                    <img src="<?php echo $foto['uploader_foto_profile']; ?>" 
                                         alt="<?php echo htmlspecialchars($foto['username']); ?>" 
                                         style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                            <?php else: ?>
                                <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                                            border-radius: 50%; display: flex; align-items: center; justify-content: center; 
                                            color: white; font-weight: bold;">
                                    <?php echo strtoupper(substr($foto['username'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div>
                                <div style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($foto['username']); ?></div>
                                <div style="font-size: 12px; color: #666;">Uploader</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Registrasi, Login, dan Edit Foto -->
    <!-- Modal Registrasi -->
    <div id="registerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Daftar Akun Baru</h3>
                <button class="close-modal" onclick="closeModal('registerModal')">&times;</button>
            </div>
            
            <?php if (isset($register_error)): ?>
                <div class="alert alert-error"><?php echo $register_error; ?></div>
            <?php endif; ?>
            
            <?php if (isset($register_success)): ?>
                <div class="alert alert-success"><?php echo $register_success; ?></div>
            <?php endif; ?>

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
            
            <?php if (isset($login_error)): ?>
                <div class="alert alert-error"><?php echo $login_error; ?></div>
            <?php endif; ?>

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

    <!-- Modal Edit Foto -->
    <?php if (isset($_SESSION['userid']) && ($_SESSION['userid'] == $foto['foto_userid'] || $_SESSION['role'] == 'admin' || $_SESSION['role'] == 'karyawan')): ?>
    <div id="editFotoModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Foto</h3>
                <button class="close-modal" onclick="closeModal('editFotoModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_foto">
                
                <div class="form-group">
                    <label for="edit_judul_foto">Judul Foto</label>
                    <input type="text" id="edit_judul_foto" name="judul_foto" class="form-control" 
                           value="<?php echo htmlspecialchars($foto['judul_foto']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="edit_deskripsi">Deskripsi</label>
                    <textarea id="edit_deskripsi" name="deskripsi" class="form-control" 
                              rows="4"><?php echo htmlspecialchars($foto['deskripsi']); ?></textarea>
                </div>

                <button type="submit" class="btn">Simpan Perubahan</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Tutup modal jika klik di luar konten modal
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let i = 0; i < modals.length; i++) {
                if (event.target == modals[i]) {
                    modals[i].style.display = 'none';
                }
            }
        }

        // Auto-hide alerts setelah 5 detik
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);

        // Share photo function
        function sharePhoto() {
            if (navigator.share) {
                navigator.share({
                    title: '<?php echo addslashes($foto['judul_foto']); ?>',
                    text: 'Lihat foto ini di Gallery Foto: <?php echo addslashes($foto['judul_foto']); ?>',
                    url: window.location.href
                })
                .then(() => console.log('Berhasil dibagikan'))
                .catch((error) => console.log('Error sharing:', error));
            } else {
                // Fallback: Copy URL to clipboard
                navigator.clipboard.writeText(window.location.href)
                    .then(() => {
                        alert('URL foto telah disalin ke clipboard!');
                    })
                    .catch(err => {
                        alert('Gagal menyalin URL. Silakan salin secara manual.');
                    });
            }
        }

        // Confirm download
        document.querySelectorAll('form[action*="download_foto"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                // Optional: Add confirmation
                // if (!confirm('Apakah Anda yakin ingin mendownload foto ini?')) {
                //     e.preventDefault();
                // }
            });
        });
    </script>
</body>
</html>

<?php
// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return '1 byte';
    } else {
        return '0 bytes';
    }
}
?>