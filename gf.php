<?php
session_start();
require_once 'functions.php';

// Dapatkan koneksi database
$pdo = getDBConnection();

// Proses form jika ada
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'register':
                $success = registerUser(
                    $_POST['username'],
                    $_POST['password'],
                    $_POST['email'],
                    $_POST['nama_lengkap'],
                    $_POST['alamat']
                );
                if ($success) {
                    $message = "Registrasi berhasil! Silakan login.";
                } else {
                    $error = "Registrasi gagal. Username atau email mungkin sudah digunakan.";
                }
                break;

            case 'login':
                $success = loginUser($_POST['username'], $_POST['password']);
                if ($success) {
                    header("Location: gallery.php");
                    exit;
                } else {
                    $error = "Login gagal. Periksa username dan password Anda.";
                }
                break;

            case 'create_album':
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
                }
                break;

            case 'upload_foto':
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
                break;

            case 'like_foto':
                if (isset($_SESSION['userid'])) {
                    $result = likeFoto($_POST['fotoid'], $_SESSION['userid']);
                    header("Location: " . $_SERVER['HTTP_REFERER']);
                    exit;
                }
                break;

            case 'add_comment':
                if (isset($_SESSION['userid'])) {
                    $result = addComment(
                        $_POST['fotoid'],
                        $_SESSION['userid'],
                        $_POST['isi_komen']
                    );
                    if ($result['success']) {
                        header("Location: " . $_SERVER['HTTP_REFERER']);
                        exit;
                    }
                }
                break;
        }
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: gallery.php");
    exit;
}

// Dapatkan semua album untuk ditampilkan
$albums = getAllAlbums();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery Foto - Pinterest Style</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --maroon: #800020;
            --dark-maroon: #5a0017;
            --light-maroon: #a83232;
            --dark-choco: #3c2a21;
            --light-choco: #5c4033;
            --white: #ffffff;
            --off-white: #f8f9fa;
            --black: #000000;
            --dark-gray: #333333;
            --medium-gray: #666666;
            --light-gray: #e0e0e0;
        }

        /* Styling untuk card album seperti di search.php */
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

        /* Section title styling */
        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin: 30px 0 20px 0;
            padding-bottom: 10px;
            display: inline-block;
        }

        /* Alert messages */
        .alert {
            padding: 12px 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
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

        /* Responsive */
        @media (max-width: 768px) {
            .albums-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 15px;
            }
        }

        @media (max-width: 480px) {
            .albums-grid {
                grid-template-columns: 1fr;
            }

            .album-cover {
                height: 180px;
            }

            .create-menu {
                right: 10px;
                min-width: 180px;
            }
        }

        /* Styling untuk search form */
        .search-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .search-form input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            border-radius: 25px;
        }

        .search-form button {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .main-content {
            padding: 20px 0;
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
                        <a href="gf.php" class="active">Album</a>
                        <a href="dashboard.php">Dashboard</a>
                        <!-- Tombol Create -->
                        <a href="#" class="create-btn" onclick="openCreateMenu()">Create</a>
                        <a href="gf.php?logout=1">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>
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
        <?php if (isset($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Tampilkan semua album -->
        <h2 class="section-title">Semua Album</h2>

        <?php if (empty($albums)): ?>
            <div style="text-align: center; padding: 40px; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3>Belum ada album yang dibuat</h3>
                <p>Jadilah yang pertama membuat album!</p>
                <?php if (isset($_SESSION['userid'])): ?>
                    <button onclick="openModal('createAlbumModal')"
                        style="background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin-top: 10px;">
                        Buat Album Pertama
                    </button>
                <?php else: ?>
                    <p>Silakan <a href="#" onclick="openModal('loginModal')" style="color: #007bff; text-decoration: underline;">login</a> untuk membuat album</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="albums-grid">
                <?php foreach ($albums as $album): ?>
                    <?php
                    // Ambil foto pertama untuk cover jika ada
                    $cover = null;
                    $coverQuery = $pdo->prepare("SELECT lokasifile FROM foto WHERE albumid = ? ORDER BY fotoid ASC LIMIT 1");
                    $coverQuery->execute([$album['albumid']]);
                    $coverResult = $coverQuery->fetch(PDO::FETCH_ASSOC);
                    $cover = $coverResult ? $coverResult['lokasifile'] : null;

                    // Ambil data user
                    $userData = getUserData($album['userid']);
                    $userPhoto = $userData ? $userData['foto_profile'] : null;

                    // Hitung jumlah foto dalam album
                    $countQuery = $pdo->prepare("SELECT COUNT(*) as jumlah_foto FROM foto WHERE albumid = ?");
                    $countQuery->execute([$album['albumid']]);
                    $countResult = $countQuery->fetch(PDO::FETCH_ASSOC);
                    $jumlah_foto = $countResult ? $countResult['jumlah_foto'] : 0;
                    ?>

                    <div class="album-card">
                        <a href="album.php?album_id=<?php echo $album['albumid']; ?>">
                            <div class="album-cover">
                                <?php if ($cover): ?>
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
                                <?php if ($userPhoto): ?>
                                    <img src="<?php echo htmlspecialchars($userPhoto); ?>"
                                        alt="<?php echo htmlspecialchars($album['username']); ?>"
                                        class="user-avatar">
                                <?php else: ?>
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                                        <?php echo strtoupper(substr($album['username'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <span class="user-name"><?php echo htmlspecialchars($album['username']); ?></span>
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

    <!-- Modal Registrasi -->
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
                    <label for="deskripsi_foto">Deskripsi</label>
                    <textarea id="deskripsi_foto" name="deskripsi" class="form-control"></textarea>
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

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });
        };

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
    </script>
</body>

</html>