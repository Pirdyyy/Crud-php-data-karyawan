<?php
session_start();
require_once 'functions.php';

if (!isset($_GET['album_id'])) {
    header("Location: gallery.php");
    exit;
}

$albumid = $_GET['album_id'];

// Dapatkan data album
$pdo = getDBConnection();
$sql = "SELECT a.*, u.username, COUNT(f.fotoid) as jumlah_foto 
        FROM album a 
        LEFT JOIN user u ON a.userid = u.userid 
        LEFT JOIN foto f ON a.albumid = f.albumid 
        WHERE a.albumid = ? 
        GROUP BY a.albumid";
$stmt = $pdo->prepare($sql);
$stmt->execute([$albumid]);
$album = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$album) {
    echo "Error: Album tidak ditemukan!";
    exit;
}

// Dapatkan foto dalam album
$fotos = getFotosInAlbum($albumid);

// Proses upload foto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_foto') {
    if (isset($_SESSION['userid'])) {
        $judul_foto = $_POST['judul_foto'];
        $deskripsi = $_POST['deskripsi'];
        $albumid = $_POST['albumid'];
        
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $result = uploadFoto($_FILES['foto'], $judul_foto, $deskripsi, $albumid, $_SESSION['userid']);
            if ($result['success']) {
                $message = "Foto berhasil diupload!";
                // Refresh daftar foto
                $fotos = getFotosInAlbum($albumid);
            } else {
                $error = $result['message'];
            }
        } else {
            $error = "Silakan pilih file foto yang valid.";
        }
    }
}

// Proses hapus album
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_album') {
    if (isset($_SESSION['userid'])) {
        $success = deleteAlbum($_POST['albumid'], $_SESSION['userid'], $_SESSION['role']);
        if ($success) {
            header("Location: gallery.php");
            exit;
        } else {
            $error = "Gagal menghapus album.";
        }
    }
}

// Proses edit album
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_album') {
    if (isset($_SESSION['userid']) && ($_SESSION['userid'] == $album['userid'] || $_SESSION['role'] == 'admin' || $_SESSION['role'] == 'karyawan')) {
        $nama_album = $_POST['nama_album'];
        $deskripsi = $_POST['deskripsi'];
        
        $sql = "UPDATE album SET nama_album = ?, deskripsi = ? WHERE albumid = ?";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$nama_album, $deskripsi, $albumid])) {
            $message = "Album berhasil diupdate!";
            // Refresh data album
            $sql = "SELECT a.*, u.username, COUNT(f.fotoid) as jumlah_foto 
                    FROM album a 
                    LEFT JOIN user u ON a.userid = u.userid 
                    LEFT JOIN foto f ON a.albumid = f.albumid 
                    WHERE a.albumid = ? 
                    GROUP BY a.albumid";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$albumid]);
            $album = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $error = "Gagal mengupdate album.";
        }
    }
}

// Proses login dari modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    if (loginUser($username, $password)) {
        header("Location: album.php?album_id=" . $albumid);
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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($album['nama_album']); ?> - Gallery Foto</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <style>
        .photos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .photo-item {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            background: white;
        }
        
        .photo-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .photo-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
            cursor: pointer;
            display: block;
        }
        
        .photo-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.7));
            color: white;
            padding: 20px 15px 15px;
            transform: translateY(100%);
            transition: transform 0.3s ease;
        }
        
        .photo-item:hover .photo-overlay {
            transform: translateY(0);
        }
        
        .photo-title {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .photo-stats {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .empty-album {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .album-header {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            position: relative;
        }
        
        .album-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin: 15px 0;
        }
        
        .album-meta span {
            background: #f8f9fa;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 14px;
            color: #666;
        }

        .album-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .edit-album-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .edit-album-btn:hover {
            background: #218838;
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
        <?php if (isset($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Header Album -->
        <div class="album-header">
            <h1 class="section-title"><?php echo htmlspecialchars($album['nama_album']); ?></h1>
            <p style="color: #666; font-size: 16px; line-height: 1.6;"><?php echo htmlspecialchars($album['deskripsi']); ?></p>
            
            <div class="album-meta">
                <span>👤 Dibuat oleh: <?php echo htmlspecialchars($album['username']); ?></span>
                <span>🖼️ Total foto: <?php echo $album['jumlah_foto']; ?></span>
                <span>📅 Tanggal dibuat: <?php echo date('d M Y', strtotime($album['tanggal'])); ?></span>
            </div>

            <div class="album-actions">
                <?php if (isset($_SESSION['userid']) && ($_SESSION['userid'] == $album['userid'] || $_SESSION['role'] == 'admin' || $_SESSION['role'] == 'karyawan')): ?>
                    <button class="edit-album-btn" onclick="openModal('editAlbumModal')">
                        ✏️ Edit Album
                    </button>
                    
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete_album">
                        <input type="hidden" name="albumid" value="<?php echo $album['albumid']; ?>">
                        <button type="submit" class="btn btn-secondary" onclick="return confirm('Yakin ingin menghapus album ini? Semua foto dalam album juga akan dihapus.')">
                            🗑️ Hapus Album
                        </button>
                    </form>
                    
                    <button class="btn" onclick="openModal('uploadFotoModal')">
                        📤 Upload Foto ke Album Ini
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Daftar Foto -->
        <h2 style="margin-bottom: 20px;">Foto dalam Album (<?php echo count($fotos); ?>)</h2>
        
        <?php if (count($fotos) > 0): ?>
            <div class="photos-grid">
                <?php foreach ($fotos as $foto): ?>
                    <div class="photo-item">
                        <img src="<?php echo $foto['lokasifile']; ?>" 
                             alt="<?php echo htmlspecialchars($foto['judul_foto']); ?>" 
                             class="photo-image"
                             onclick="window.location.href='foto.php?foto_id=<?php echo $foto['fotoid']; ?>'">
                        
                        <div class="photo-overlay">
                            <div class="photo-title"><?php echo htmlspecialchars($foto['judul_foto']); ?></div>
                            <div class="photo-stats">
                                <?php echo $foto['jumlah_like']; ?><i class="fa-solid fa-heart"></i>
                                💬 <?php echo $foto['jumlah_komentar']; ?> Komentar
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-album">
                <h3 style="color: #666; margin-bottom: 15px;">📷 Belum ada foto dalam album ini</h3>
                <p style="color: #999; margin-bottom: 20px;">Jadilah yang pertama mengupload foto ke album ini!</p>
                <?php if (isset($_SESSION['userid']) && $_SESSION['userid'] == $album['userid']): ?>
                    <button class="btn" onclick="openModal('uploadFotoModal')">📤 Upload Foto Pertama</button>
                <?php else: ?>
                    <p style="color: #999;">Login sebagai pemilik album untuk upload foto</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Edit Album -->
    <?php if (isset($_SESSION['userid']) && ($_SESSION['userid'] == $album['userid'] || $_SESSION['role'] == 'admin' || $_SESSION['role'] == 'karyawan')): ?>
    <div id="editAlbumModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Album</h3>
                <button class="close-modal" onclick="closeModal('editAlbumModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit_album">
                
                <div class="form-group">
                    <label for="edit_nama_album">Nama Album</label>
                    <input type="text" id="edit_nama_album" name="nama_album" class="form-control" 
                           value="<?php echo htmlspecialchars($album['nama_album']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="edit_deskripsi">Deskripsi Album</label>
                    <textarea id="edit_deskripsi" name="deskripsi" class="form-control" rows="4"><?php echo htmlspecialchars($album['deskripsi']); ?></textarea>
                </div>

                <button type="submit" class="btn">Simpan Perubahan</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

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

    <!-- Modal Upload Foto -->
    <?php if (isset($_SESSION['userid'])): ?>
    <div id="uploadFotoModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Upload Foto Baru</h3>
                <button class="close-modal" onclick="closeModal('uploadFotoModal')">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_foto">
                <input type="hidden" name="albumid" value="<?php echo $albumid; ?>">

                <div class="form-group">
                    <label for="judul_foto">Judul Foto</label>
                    <input type="text" id="judul_foto" name="judul_foto" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="deskripsi_foto">Deskripsi</label>
                    <textarea id="deskripsi_foto" name="deskripsi" class="form-control"></textarea>
                </div>

                <div class="form-group">
                    <label for="foto">Pilih Foto</label>
                    <input type="file" id="foto" name="foto" class="form-control" accept="image/*" required>
                </div>

                <button type="submit" class="btn">Upload Foto</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal Buat Album -->
    <?php if (isset($_SESSION['userid'])): ?>
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
    </script>
</body>
</html>