<?php
session_start();
require_once 'functions.php';

$message = '';
$error = '';

// Proses Registrasi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'register') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $email = $_POST['email'];
    $nama_lengkap = $_POST['nama_lengkap'];
    $alamat = $_POST['alamat'];

    $result = registerUser($username, $password, $email, $nama_lengkap, $alamat);
    if ($result['success']) {
        $message = $result['message'];
    } else {
        $error = $result['message'];
    }
}

// Proses Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'login') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (loginUser($username, $password)) {
        header("Location: gallery.php");
        exit;
    } else {
        $error = "Username/email atau password salah";
    }
}

// Proses Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: gallery.php");
    exit;
}

// Proses Create Album
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'create_album') {
    if (isset($_SESSION['userid'])) {
        $result = createAlbum(
            $_POST['nama_album'],
            $_POST['deskripsi'],
            $_SESSION['userid']
        );
        if ($result['success']) {
            $message = "Album berhasil dibuat!";
        } else {
            $error = $result['message'];
        }
    } else {
        $error = "Silakan login terlebih dahulu untuk membuat album.";
    }
}

// Proses Upload Foto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'upload_foto') {
    if (isset($_SESSION['userid']) && isset($_FILES['foto'])) {
        $result = uploadFoto(
            $_FILES['foto'],
            $_POST['judul_foto'],
            $_POST['deskripsi'],
            $_POST['albumid'],
            $_SESSION['userid']
        );
        if ($result['success']) {
            $message = "Foto berhasil diupload!";
        } else {
            $error = $result['message'];
        }
    }
}

// Proses like dari gallery
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'like_foto') {
    if (isset($_SESSION['userid'])) {
        $result = likeFoto($_POST['fotoid'], $_SESSION['userid']);
        // Redirect kembali ke halaman yang sama untuk menghindari resubmission
        header("Location: gallery.php" . (isset($_GET['page']) ? "?page=" . $_GET['page'] : ""));
        exit;
    }
}

// Ambil semua foto dengan pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 24; // Jumlah foto per halaman
$offset = ($page - 1) * $limit;

// Hitung total foto
$total_fotos = getTotalPhotosCount();
$total_pages = ceil($total_fotos / $limit);

// Ambil foto dengan pagination
$fotos = getAllFotosForGallery($limit, $offset);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galeri Semua Foto - Gallery Foto</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .gallery-header {
            text-align: center;
            margin-bottom: 2rem;
            padding: 1rem 0;
        }

        .gallery-header h1 {
            color: #333;
            margin-bottom: 0.5rem;
        }

        .gallery-stats {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 1rem;
        }

        .stat {
            background: #f8f9fa;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            color: #666;
        }

        .photos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin: 2rem 0;
        }

        .photo-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .photo-card:hover {
            transform: translateY(-3px);
        }

        .photo-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            display: block;
        }

        .photo-info {
            padding: 1rem;
        }

        .photo-title {
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .photo-meta {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.5rem;
        }

        .photo-author {
            display: block;
        }

        .photo-album {
            display: block;
            font-style: italic;
        }

        .photo-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 0.5rem;
        }

        .like-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            padding: 0.3rem;
        }

        .like-btn.liked {
            color: #e60023;
        }

        .view-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 0.3rem 0.8rem;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin: 2rem 0;
        }

        .page-btn {
            padding: 0.5rem 1rem;
            background: white;
            border: 1px solid #ddd;
            border-radius: 3px;
            text-decoration: none;
            color: #333;
        }

        .page-btn:hover, .page-btn.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .filter-bar {
            margin-bottom: 2rem;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .filter-select {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 3px;
            margin-right: 1rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

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

        @media (max-width: 768px) {
            .photos-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
            
            .create-menu {
                right: 10px;
                min-width: 180px;
            }
        }

        @media (max-width: 480px) {
            .photos-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="navbar">
                <a href="gallery.php" class="logo">GF</a>

                <form class="search-form" method="GET" action="search.php">
                    <input type="text" name="keyword" placeholder="Cari album atau foto..." value="<?php echo isset($_GET['keyword']) ? htmlspecialchars($_GET['keyword']) : ''; ?>">
                </form>

                <div class="nav-links">
                    <?php if (isset($_SESSION['userid'])): ?>
                        <a href="gallery.php" class="active">Beranda</a>
                        <a href="gf.php">Album</a>
                        <a href="dashboard.php">Dashboard</a>
                        <!-- Tambah Tombol Create di Navbar -->
                        <a href="#" class="create-btn" onclick="openCreateMenu()">Create</a>
                        <a href="gallery.php?logout=1">Logout (<?php echo $_SESSION['username']; ?>)</a>
                    <?php else: ?>
                        <a href="gallery.php" class="active">Beranda</a>
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
        <!-- Tampilkan pesan sukses/error -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="gallery-header">
            <h1>📸 Galeri Foto</h1>
            <p>Jelajahi koleksi foto dari seluruh komunitas</p>
            <div class="gallery-stats">
                <div class="stat">Total: <?php echo $total_fotos; ?> Foto</div>
                <div class="stat">Halaman: <?php echo $page; ?> dari <?php echo $total_pages; ?></div>
            </div>
        </div>
        <?php if (empty($fotos)): ?>
            <div class="empty-state">
                <h3>Belum ada foto yang diupload</h3>
                <p>Jadilah yang pertama untuk mengupload foto!</p>
                <?php if (isset($_SESSION['userid'])): ?>
                    <a href="#" onclick="openModal('uploadFotoModal')" class="btn">Upload Foto Pertama</a>
                <?php else: ?>
                    <a href="#" onclick="openModal('registerModal')" class="btn">Daftar untuk Upload Foto</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Grid Layout -->
            <div class="photos-grid">
                <?php foreach ($fotos as $foto): 
                    $isLiked = isset($_SESSION['userid']) ? isLiked($foto['fotoid'], $_SESSION['userid']) : false;
                    $likeCount = $foto['jumlah_like'];
                ?>
                    <div class="photo-card">
                        <img src="<?php echo $foto['lokasifile']; ?>" 
                             alt="<?php echo htmlspecialchars($foto['judul_foto']); ?>"
                             class="photo-image">
                        
                        <div class="photo-info">
                            <div class="photo-title"><?php echo htmlspecialchars($foto['judul_foto']); ?></div>
                            
                            <div class="photo-meta">
                                <span class="photo-author">Oleh: <?php echo htmlspecialchars($foto['username']); ?></span>
                                <?php if ($foto['nama_album']): ?>
                                    <span class="photo-album">Album: <?php echo htmlspecialchars($foto['nama_album']); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="photo-actions">
                                <?php if (isset($_SESSION['userid'])): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="like_foto">
                                        <input type="hidden" name="fotoid" value="<?php echo $foto['fotoid']; ?>">
                                        <button type="submit" class="like-btn <?php echo $isLiked ? 'liked' : ''; ?>">
                                            ❤️ <?php echo $likeCount; ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span>❤️ <?php echo $likeCount; ?></span>
                                <?php endif; ?>
                                
                                <a href="foto.php?foto_id=<?php echo $foto['fotoid']; ?>" class="view-btn">Lihat</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="gallery.php?page=<?php echo $page - 1; ?>" class="page-btn">← Sebelumnya</a>
                    <?php else: ?>
                        <span class="page-btn">← Sebelumnya</span>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="gallery.php?page=<?php echo $i; ?>" class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="gallery.php?page=<?php echo $page + 1; ?>" class="page-btn">Selanjutnya →</a>
                    <?php else: ?>
                        <span class="page-btn">Selanjutnya →</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Modal Login -->
    <div id="loginModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Login</h3>
                <button class="close-modal" onclick="closeModal('loginModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="login">

                <div class="form-group">
                    <label for="login_username">Username atau Email</label>
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

    <!-- Modal Register -->
    <div id="registerModal" class="modal" style="display: none;">
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

    <!-- Modal Buat Album -->
    <div id="createAlbumModal" class="modal" style="display: none;">
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
    <div id="uploadFotoModal" class="modal" style="display: none;">
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
                        <?php
                        if (isset($_SESSION['userid'])) {
                            $userAlbums = getUserAlbums($_SESSION['userid']);
                            foreach ($userAlbums as $album) {
                                echo '<option value="' . $album['albumid'] . '">' . htmlspecialchars($album['nama_album']) . '</option>';
                            }
                        }
                        ?>
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
                closeCreateMenu();
            }
        }

        // Modal functions (untuk modal lainnya)
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
            // Tutup menu create jika terbuka
            if (createMenuOpen) {
                closeCreateMenu();
            }
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Filter functions
        function filterByAlbum(albumId) {
            if (albumId) {
                window.location.href = `gallery.php?album=${albumId}`;
            } else {
                window.location.href = `gallery.php`;
            }
        }

        function filterByUser(userId) {
            if (userId) {
                window.location.href = `gallery.php?user=${userId}`;
            } else {
                window.location.href = `gallery.php`;
            }
        }

        // Close modal ketika klik di luar
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // Close create menu when clicking outside
        document.addEventListener('click', function(event) {
            const createMenu = document.getElementById('createMenu');
            const createBtn = document.querySelector('.create-btn');
            
            // Jika klik di luar menu create dan di luar tombol create
            if (createMenuOpen && 
                !createMenu.contains(event.target) && 
                !createBtn.contains(event.target)) {
                closeCreateMenu();
            }
        });

        // Auto close alerts setelah 5 detik
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.style.display = 'none';
                });
            }, 5000);
        });
    </script>
</body>
</html>