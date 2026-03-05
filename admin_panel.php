<?php
session_start();
require_once 'functions.php';

// Cek apakah user adalah admin
if (!isset($_SESSION['userid']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: gallery.php");
    exit;
}

$pdo = getDBConnection();
$message = '';
$error = '';

// Get active tab from URL or default to 'users'
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'users';

// ================ PROCESS USER ACTIONS ================
// Proses Delete User
if (isset($_GET['delete_user'])) {
    $userid_to_delete = $_GET['delete_user'];
    
    // Jangan biarkan admin menghapus dirinya sendiri
    if ($userid_to_delete != $_SESSION['userid']) {
        try {
            // Mulai transaksi
            $pdo->beginTransaction();
            
            // 1. Hapus album user (dan semua foto di dalamnya)
            $albums = $pdo->prepare("SELECT albumid FROM album WHERE userid = ?");
            $albums->execute([$userid_to_delete]);
            $userAlbums = $albums->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($userAlbums as $album) {
                // Hapus semua foto dalam album
                $fotos = $pdo->prepare("SELECT * FROM foto WHERE albumid = ?");
                $fotos->execute([$album['albumid']]);
                $albumFotos = $fotos->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($albumFotos as $foto) {
                    // Hapus file fisik
                    if (file_exists($foto['lokasifile'])) {
                        unlink($foto['lokasifile']);
                    }
                    
                    // Hapus likes terkait
                    $pdo->prepare("DELETE FROM likes WHERE fotoid = ?")->execute([$foto['fotoid']]);
                    
                    // Hapus komentar terkait
                    $pdo->prepare("DELETE FROM komentar WHERE fotoid = ?")->execute([$foto['fotoid']]);
                }
                
                // Hapus foto dari database
                $pdo->prepare("DELETE FROM foto WHERE albumid = ?")->execute([$album['albumid']]);
                
                // Hapus album
                $pdo->prepare("DELETE FROM album WHERE albumid = ?")->execute([$album['albumid']]);
            }
            
            // 2. Hapus likes yang diberikan user
            $pdo->prepare("DELETE FROM likes WHERE userid = ?")->execute([$userid_to_delete]);
            
            // 3. Hapus komentar yang dibuat user
            $pdo->prepare("DELETE FROM komentar WHERE userid = ?")->execute([$userid_to_delete]);
            
            // 4. Hapus user
            $stmt = $pdo->prepare("DELETE FROM user WHERE userid = ?");
            if ($stmt->execute([$userid_to_delete])) {
                $pdo->commit();
                $message = "User berhasil dihapus beserta semua kontennya!";
            } else {
                $pdo->rollBack();
                $error = "Gagal menghapus user.";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = "Tidak dapat menghapus akun sendiri!";
    }
}

// Proses Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit_user') {
    $userid = $_POST['userid'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $nama_lengkap = $_POST['nama_lengkap'];
    $alamat = $_POST['alamat'];
    $role = $_POST['role'];
    
    try {
        $sql = "UPDATE user SET username = ?, email = ?, nama_lengkap = ?, alamat = ?, role = ? WHERE userid = ?";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$username, $email, $nama_lengkap, $alamat, $role, $userid])) {
            $message = "Data user berhasil diupdate!";
        } else {
            $error = "Gagal mengupdate data user.";
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Proses Add New User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_user') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $email = $_POST['email'];
    $nama_lengkap = $_POST['nama_lengkap'];
    $alamat = $_POST['alamat'];
    $role = $_POST['role'];
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        // Cek apakah username atau email sudah ada
        $checkStmt = $pdo->prepare("SELECT userid FROM user WHERE username = ? OR email = ?");
        $checkStmt->execute([$username, $email]);
        
        if ($checkStmt->rowCount() > 0) {
            $error = "Username atau email sudah digunakan!";
        } else {
            $sql = "INSERT INTO user (username, password, email, nama_lengkap, alamat, role) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$username, $hashed_password, $email, $nama_lengkap, $alamat, $role])) {
                $message = "User baru berhasil ditambahkan!";
            } else {
                $error = "Gagal menambahkan user baru.";
            }
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// ================ PROCESS ALBUM ACTIONS ================
// Proses Delete Album
if (isset($_GET['delete_album'])) {
    $albumid_to_delete = $_GET['delete_album'];
    
    try {
        // Mulai transaksi
        $pdo->beginTransaction();
        
        // Ambil semua foto dalam album
        $fotos = $pdo->prepare("SELECT * FROM foto WHERE albumid = ?");
        $fotos->execute([$albumid_to_delete]);
        $albumFotos = $fotos->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($albumFotos as $foto) {
            // Hapus file fisik
            if (file_exists($foto['lokasifile'])) {
                unlink($foto['lokasifile']);
            }
            
            // Hapus likes terkait
            $pdo->prepare("DELETE FROM likes WHERE fotoid = ?")->execute([$foto['fotoid']]);
            
            // Hapus komentar terkait
            $pdo->prepare("DELETE FROM komentar WHERE fotoid = ?")->execute([$foto['fotoid']]);
        }
        
        // Hapus foto dari database
        $pdo->prepare("DELETE FROM foto WHERE albumid = ?")->execute([$albumid_to_delete]);
        
        // Hapus album
        $stmt = $pdo->prepare("DELETE FROM album WHERE albumid = ?");
        if ($stmt->execute([$albumid_to_delete])) {
            $pdo->commit();
            $message = "Album berhasil dihapus beserta semua foto di dalamnya!";
        } else {
            $pdo->rollBack();
            $error = "Gagal menghapus album.";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Proses Edit Album
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit_album') {
    $albumid = $_POST['albumid'];
    $nama_album = $_POST['nama_album'];
    $deskripsi = $_POST['deskripsi'];
    $userid = $_POST['userid']; // Transfer ownership jika perlu
    
    try {
        $sql = "UPDATE album SET nama_album = ?, deskripsi = ?, userid = ? WHERE albumid = ?";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$nama_album, $deskripsi, $userid, $albumid])) {
            $message = "Album berhasil diupdate!";
        } else {
            $error = "Gagal mengupdate album.";
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Proses Add New Album
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_album') {
    $nama_album = $_POST['nama_album'];
    $deskripsi = $_POST['deskripsi'];
    $userid = $_POST['userid'];
    
    try {
        $sql = "INSERT INTO album (nama_album, deskripsi, userid) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$nama_album, $deskripsi, $userid])) {
            $message = "Album baru berhasil ditambahkan!";
        } else {
            $error = "Gagal menambahkan album baru.";
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// ================ PROCESS PHOTO ACTIONS ================
// Proses Delete Photo
if (isset($_GET['delete_photo'])) {
    $photoid_to_delete = $_GET['delete_photo'];
    
    try {
        // Ambil data foto untuk hapus file fisik
        $foto = $pdo->prepare("SELECT * FROM foto WHERE fotoid = ?");
        $foto->execute([$photoid_to_delete]);
        $photoData = $foto->fetch(PDO::FETCH_ASSOC);
        
        if ($photoData) {
            // Mulai transaksi
            $pdo->beginTransaction();
            
            // Hapus file fisik
            if (file_exists($photoData['lokasifile'])) {
                unlink($photoData['lokasifile']);
            }
            
            // Hapus likes terkait
            $pdo->prepare("DELETE FROM likes WHERE fotoid = ?")->execute([$photoid_to_delete]);
            
            // Hapus komentar terkait
            $pdo->prepare("DELETE FROM komentar WHERE fotoid = ?")->execute([$photoid_to_delete]);
            
            // Hapus foto dari database
            $stmt = $pdo->prepare("DELETE FROM foto WHERE fotoid = ?");
            if ($stmt->execute([$photoid_to_delete])) {
                $pdo->commit();
                $message = "Foto berhasil dihapus!";
            } else {
                $pdo->rollBack();
                $error = "Gagal menghapus foto.";
            }
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Proses Edit Photo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit_photo') {
    $fotoid = $_POST['fotoid'];
    $judul_foto = $_POST['judul_foto'];
    $deskripsi = $_POST['deskripsi'];
    $albumid = $_POST['albumid'];
    
    try {
        $sql = "UPDATE foto SET judul_foto = ?, deskripsi = ?, albumid = ? WHERE fotoid = ?";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$judul_foto, $deskripsi, $albumid, $fotoid])) {
            $message = "Foto berhasil diupdate!";
        } else {
            $error = "Gagal mengupdate foto.";
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// ================ PROCESS SYSTEM SETTINGS ================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_settings') {
    // Simpan settings ke file atau database
    $site_title = $_POST['site_title'];
    $site_description = $_POST['site_description'];
    $max_upload_size = $_POST['max_upload_size'];
    $allowed_extensions = $_POST['allowed_extensions'];
    
    // Dalam implementasi nyata, simpan ke database atau file config
    $message = "Settings berhasil diupdate! (Simulasi)";
}

// ================ GET ALL DATA ================
// Ambil semua data user
$users = $pdo->query("SELECT * FROM user ORDER BY tanggal_dibuat DESC")->fetchAll(PDO::FETCH_ASSOC);

// Ambil semua album dengan info user
$albums = $pdo->query("
    SELECT a.*, u.username, u.nama_lengkap as user_name,
           (SELECT COUNT(*) FROM foto f WHERE f.albumid = a.albumid) as jumlah_foto
    FROM album a 
    LEFT JOIN user u ON a.userid = u.userid 
    ORDER BY a.tanggal DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Ambil semua foto dengan info album dan user
$photos = $pdo->query("
    SELECT f.*, a.nama_album, u.username,
           (SELECT COUNT(*) FROM likes l WHERE l.fotoid = f.fotoid) as jumlah_likes,
           (SELECT COUNT(*) FROM komentar k WHERE k.fotoid = f.fotoid) as jumlah_komentar
    FROM foto f 
    LEFT JOIN album a ON f.albumid = a.albumid 
    LEFT JOIN user u ON f.userid = u.userid 
    ORDER BY f.tanggal_unggah DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Ambil semua komentar
$comments = $pdo->query("
    SELECT k.*, u.username, f.judul_foto, a.nama_album
    FROM komentar k 
    LEFT JOIN user u ON k.userid = u.userid 
    LEFT JOIN foto f ON k.fotoid = f.fotoid 
    LEFT JOIN album a ON f.albumid = a.albumid 
    ORDER BY k.tanggal_komen DESC
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

// ================ STATISTICS ================
$totalUsers = count($users);
$totalAdmins = $pdo->query("SELECT COUNT(*) as total FROM user WHERE role = 'admin'")->fetch(PDO::FETCH_ASSOC)['total'];
$totalRegular = $pdo->query("SELECT COUNT(*) as total FROM user WHERE role = 'user'")->fetch(PDO::FETCH_ASSOC)['total'];
$totalToday = $pdo->query("SELECT COUNT(*) as total FROM user WHERE DATE(tanggal_dibuat) = CURDATE()")->fetch(PDO::FETCH_ASSOC)['total'];

$totalAlbums = count($albums);
$totalPhotos = count($photos);
$totalLikes = $pdo->query("SELECT COUNT(*) as total FROM likes")->fetch(PDO::FETCH_ASSOC)['total'];
$totalComments = $pdo->query("SELECT COUNT(*) as total FROM komentar")->fetch(PDO::FETCH_ASSOC)['total'];

// System info
$serverInfo = [
    'PHP Version' => phpversion(),
    'Server Software' => $_SERVER['SERVER_SOFTWARE'],
    'Database' => 'MySQL',
    'Max Upload Size' => ini_get('upload_max_filesize'),
    'Memory Limit' => ini_get('memory_limit'),
    'Post Max Size' => ini_get('post_max_size'),
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Gallery Foto</title>
    <link rel="stylesheet" href="./assets/css/style.css">
    <link rel="stylesheet" href="./assets/css/admin_panel.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

</head>
<body>
    <header>
        <div class="container">
            <div class="navbar">
                <a href="gallery.php" class="logo">GF</a>

                <div class="nav-links">
                    <?php if (isset($_SESSION['userid'])): ?>
                        <a href="gallery.php">Beranda</a>
                        <a href="dashboard.php">Dashboard</a>
                        <a href="admin_panel.php" class="active">Admin Panel</a>
                        <a href="admin_panel.php?logout=1">Logout (<?php echo $_SESSION['username']; ?>)</a>
                    <?php else: ?>
                        <a href="gallery.php">Beranda</a>
                        <a href="#" onclick="openModal('loginModal')">Login</a>
                        <a href="#" onclick="openModal('registerModal')">Daftar</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <div class="container main-content">
        <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="admin-header">
            <h1 class="admin-title">Admin Panel</h1>
            <p class="admin-subtitle">Sistem Manajemen Gallery Foto</p>
            <div class="admin-quick-stats">
                <div class="quick-stat"><i class="fas fa-users"></i> <?php echo $totalUsers; ?> Users</div>
                <div class="quick-stat"><i class="fas fa-images"></i> <?php echo $totalPhotos; ?> Photos</div>
                <div class="quick-stat"><i class="fas fa-layer-group"></i> <?php echo $totalAlbums; ?> Albums</div>
            </div>
        </div>

        <!-- Statistik Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-friends"></i></div>
                <div class="stat-number"><?php echo $totalUsers; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                <div class="stat-number"><?php echo $totalAlbums; ?></div>
                <div class="stat-label">Total Albums</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-image"></i></div>
                <div class="stat-number"><?php echo $totalPhotos; ?></div>
                <div class="stat-label">Total Photos</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-heart"></i></div>
                <div class="stat-number"><?php echo $totalLikes; ?></div>
                <div class="stat-label">Total Likes</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-comment"></i></div>
                <div class="stat-number"><?php echo $totalComments; ?></div>
                <div class="stat-label">Total Comments</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-shield"></i></div>
                <div class="stat-number"><?php echo $totalAdmins; ?></div>
                <div class="stat-label">Admin Users</div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="admin-tabs">
            <button class="tab-btn <?php echo $activeTab === 'users' ? 'active' : ''; ?>" onclick="openTab('users')">
                <i class="fas fa-users"></i> Users
            </button>
            <button class="tab-btn <?php echo $activeTab === 'albums' ? 'active' : ''; ?>" onclick="openTab('albums')">
                <i class="fas fa-layer-group"></i> Albums
            </button>
            <button class="tab-btn <?php echo $activeTab === 'photos' ? 'active' : ''; ?>" onclick="openTab('photos')">
                <i class="fas fa-images"></i> Photos
            </button>
            <button class="tab-btn <?php echo $activeTab === 'comments' ? 'active' : ''; ?>" onclick="openTab('comments')">
                <i class="fas fa-comments"></i> Comments
            </button>
            <button class="tab-btn <?php echo $activeTab === 'stats' ? 'active' : ''; ?>" onclick="openTab('stats')">
                <i class="fas fa-chart-bar"></i> Statistics
            </button>
            <button class="tab-btn <?php echo $activeTab === 'system' ? 'active' : ''; ?>" onclick="openTab('system')">
                <i class="fas fa-cogs"></i> System
            </button>
        </div>

        <!-- Tab: Kelola Users -->
        <div id="users" class="tab-content <?php echo $activeTab === 'users' ? 'active' : ''; ?>">
            <div class="data-table-container">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-users"></i> Daftar Users
                    </div>
                    <div class="table-actions">
                        <input type="text" class="search-box" placeholder="Cari user..." onkeyup="filterTable('usersTable', this)">
                        <button class="btn-add" onclick="openModal('addUserModal')">
                            <i class="fas fa-plus"></i> Tambah User
                        </button>
                    </div>
                </div>
                <table class="data-table" id="usersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Tanggal Daftar</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['userid']; ?></td>
                            <td>
                                <?php if (!empty($user['foto_profile'])): ?>
                                    <img src="<?php echo htmlspecialchars($user['foto_profile']); ?>" alt="Avatar" class="user-avatar">
                                <?php else: ?>
                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; display: inline-flex; align-items: center; justify-content: center; margin-right: 10px;">
                                        <?php echo strtoupper(substr($user['nama_lengkap'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <strong><?php echo htmlspecialchars($user['username']); ?></strong><br>
                                <small><?php echo htmlspecialchars($user['nama_lengkap']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <?php 
                                $roleClass = 'role-' . $user['role'];
                                $roleText = ucfirst($user['role']);
                                echo "<span class='role-badge $roleClass'>$roleText</span>";
                                ?>
                            </td>
                            <td><?php echo date('d M Y', strtotime($user['tanggal_dibuat'])); ?></td>
                            <td>
                                <span class="status-badge status-active">Active</span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-icon btn-edit" onclick="openEditUserModal(<?php echo $user['userid']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['email']); ?>', '<?php echo htmlspecialchars($user['nama_lengkap']); ?>', '<?php echo htmlspecialchars($user['alamat']); ?>', '<?php echo $user['role']; ?>')" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($user['userid'] != $_SESSION['userid']): ?>
                                        <button class="btn-icon btn-delete" onclick="confirmDelete('user', <?php echo $user['userid']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="pagination">
                    <button class="page-btn active">1</button>
                    <button class="page-btn">2</button>
                    <button class="page-btn">3</button>
                </div>
            </div>
        </div>

        <!-- Tab: Kelola Albums -->
        <div id="albums" class="tab-content <?php echo $activeTab === 'albums' ? 'active' : ''; ?>">
            <div class="data-table-container">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-layer-group"></i> Daftar Albums
                    </div>
                    <div class="table-actions">
                        <input type="text" class="search-box" placeholder="Cari album..." onkeyup="filterTable('albumsTable', this)">
                        <button class="btn-add" onclick="openModal('addAlbumModal')">
                            <i class="fas fa-plus"></i> Tambah Album
                        </button>
                    </div>
                </div>
                <table class="data-table" id="albumsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cover</th>
                            <th>Nama Album</th>
                            <th>Pemilik</th>
                            <th>Jumlah Foto</th>
                            <th>Tanggal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($albums as $album): 
                            // Cari cover photo
                            $cover = $pdo->prepare("SELECT lokasifile FROM foto WHERE albumid = ? LIMIT 1");
                            $cover->execute([$album['albumid']]);
                            $coverPhoto = $cover->fetch(PDO::FETCH_ASSOC);
                        ?>
                        <tr>
                            <td><?php echo $album['albumid']; ?></td>
                            <td>
                                <?php if ($coverPhoto && file_exists($coverPhoto['lokasifile'])): ?>
                                    <img src="<?php echo htmlspecialchars($coverPhoto['lokasifile']); ?>" alt="Cover" class="album-cover">
                                <?php else: ?>
                                    <div style="width: 60px; height: 60px; border-radius: 8px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-images"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($album['nama_album']); ?></strong><br>
                                <small style="color: #666;"><?php echo htmlspecialchars($album['deskripsi'] ?: 'Tidak ada deskripsi'); ?></small>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($album['user_name'] ?: 'Unknown'); ?><br>
                                <small style="color: #666;">@<?php echo htmlspecialchars($album['username']); ?></small>
                            </td>
                            <td>
                                <span style="font-weight: 600; color: #667eea;"><?php echo $album['jumlah_foto']; ?></span> foto
                            </td>
                            <td><?php echo date('d M Y', strtotime($album['tanggal'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-icon btn-view" onclick="window.open('album.php?album_id=<?php echo $album['albumid']; ?>', '_blank')" title="View">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-icon btn-edit" onclick="openEditAlbumModal(<?php echo $album['albumid']; ?>, '<?php echo htmlspecialchars($album['nama_album']); ?>', '<?php echo htmlspecialchars($album['deskripsi']); ?>', <?php echo $album['userid']; ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-icon btn-delete" onclick="confirmDelete('album', <?php echo $album['albumid']; ?>, '<?php echo htmlspecialchars($album['nama_album']); ?>')" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab: Kelola Photos -->
        <div id="photos" class="tab-content <?php echo $activeTab === 'photos' ? 'active' : ''; ?>">
            <div class="data-table-container">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-images"></i> Daftar Photos
                    </div>
                    <div class="table-actions">
                        <input type="text" class="search-box" placeholder="Cari foto..." onkeyup="filterTable('photosTable', this)">
                    </div>
                </div>
                <table class="data-table" id="photosTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Thumbnail</th>
                            <th>Judul</th>
                            <th>Album</th>
                            <th>Uploader</th>
                            <th>Likes</th>
                            <th>Tanggal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($photos as $photo): ?>
                        <tr>
                            <td><?php echo $photo['fotoid']; ?></td>
                            <td>
                                <?php if (file_exists($photo['lokasifile'])): ?>
                                    <img src="<?php echo htmlspecialchars($photo['lokasifile']); ?>" alt="Thumbnail" class="photo-thumbnail">
                                <?php else: ?>
                                    <div style="width: 60px; height: 60px; border-radius: 8px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($photo['judul_foto']); ?></strong><br>
                                <small style="color: #666;"><?php echo htmlspecialchars(substr($photo['deskripsi'] ?: 'Tidak ada deskripsi', 0, 50)); ?>...</small>
                            </td>
                            <td><?php echo htmlspecialchars($photo['nama_album']); ?></td>
                            <td><?php echo htmlspecialchars($photo['username']); ?></td>
                            <td>
                                <span style="color: #ff6b6b;"><i class="fas fa-heart"></i> <?php echo $photo['jumlah_likes']; ?></span><br>
                                <span style="color: #74b9ff;"><i class="fas fa-comment"></i> <?php echo $photo['jumlah_komentar']; ?></span>
                            </td>
                            <td><?php echo date('d M Y', strtotime($photo['tanggal_unggah'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-icon btn-view" onclick="window.open('foto.php?foto_id=<?php echo $photo['fotoid']; ?>', '_blank')" title="View">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-icon btn-edit" onclick="openEditPhotoModal(<?php echo $photo['fotoid']; ?>, '<?php echo htmlspecialchars($photo['judul_foto']); ?>', '<?php echo htmlspecialchars($photo['deskripsi']); ?>', <?php echo $photo['albumid']; ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-icon btn-delete" onclick="confirmDelete('photo', <?php echo $photo['fotoid']; ?>, '<?php echo htmlspecialchars($photo['judul_foto']); ?>')" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab: Kelola Comments -->
        <div id="comments" class="tab-content <?php echo $activeTab === 'comments' ? 'active' : ''; ?>">
            <div class="data-table-container">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-comments"></i> Daftar Comments
                    </div>
                    <div class="table-actions">
                        <input type="text" class="search-box" placeholder="Cari komentar..." onkeyup="filterTable('commentsTable', this)">
                    </div>
                </div>
                <div style="max-height: 600px; overflow-y: auto;">
                    <?php foreach ($comments as $comment): ?>
                    <div class="comment-item">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <strong><?php echo htmlspecialchars($comment['username']); ?></strong>
                                <small style="color: #888; margin-left: 10px;"><?php echo date('d M Y H:i', strtotime($comment['tanggal_komen'])); ?></small>
                            </div>
                            <button class="btn-icon btn-delete" onclick="confirmDelete('comment', <?php echo $comment['komentarid']; ?>, 'komentar dari <?php echo htmlspecialchars($comment['username']); ?>')" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <div class="comment-content">
                            <?php echo htmlspecialchars($comment['isi_komen']); ?>
                        </div>
                        <div class="comment-meta">
                            <span>Pada: <strong><?php echo htmlspecialchars($comment['judul_foto']); ?></strong></span>
                            <span>Album: <?php echo htmlspecialchars($comment['nama_album']); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Tab: Statistics -->
        <div id="stats" class="tab-content <?php echo $activeTab === 'stats' ? 'active' : ''; ?>">
            <div class="dashboard-cards">
                <div class="dashboard-card">
                    <div class="card-title"><i class="fas fa-chart-pie"></i> User Statistics</div>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                        <div class="stat-card" style="padding: 15px;">
                            <div class="stat-number" style="font-size: 24px;"><?php echo $totalAdmins; ?></div>
                            <div class="stat-label" style="font-size: 12px;">Admin Users</div>
                        </div>
                        <div class="stat-card" style="padding: 15px;">
                            <div class="stat-number" style="font-size: 24px;"><?php echo $totalRegular; ?></div>
                            <div class="stat-label" style="font-size: 12px;">Regular Users</div>
                        </div>
                        <div class="stat-card" style="padding: 15px;">
                            <div class="stat-number" style="font-size: 24px;"><?php echo $totalToday; ?></div>
                            <div class="stat-label" style="font-size: 12px;">New Today</div>
                        </div>
                        <div class="stat-card" style="padding: 15px;">
                            <div class="stat-number" style="font-size: 24px;"><?php echo round($totalPhotos / max($totalUsers, 1), 1); ?></div>
                            <div class="stat-label" style="font-size: 12px;">Photos/User</div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="card-title"><i class="fas fa-chart-line"></i> Growth</div>
                    <?php
                    // Ambil data growth per bulan
                    $growthStats = $pdo->query("
                        SELECT 
                            DATE_FORMAT(tanggal_dibuat, '%Y-%m') as month,
                            COUNT(*) as new_users
                        FROM user 
                        WHERE tanggal_dibuat >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                        GROUP BY DATE_FORMAT(tanggal_dibuat, '%Y-%m')
                        ORDER BY month
                    ")->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <div style="padding: 20px 0;">
                        <?php foreach ($growthStats as $stat): ?>
                        <div style="margin-bottom: 10px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                <span style="font-size: 14px; color: #333;"><?php echo date('M Y', strtotime($stat['month'] . '-01')); ?></span>
                                <span style="font-weight: 600; color: #667eea;"><?php echo $stat['new_users']; ?> users</span>
                            </div>
                            <div style="height: 8px; background: #f0f0f0; border-radius: 4px; overflow: hidden;">
                                <div style="height: 100%; width: <?php echo min($stat['new_users'] * 10, 100); ?>%; background: linear-gradient(90deg, #667eea, #764ba2); border-radius: 4px;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab: System -->
        <div id="system" class="tab-content <?php echo $activeTab === 'system' ? 'active' : ''; ?>">
            <div class="dashboard-cards">
                <div class="dashboard-card">
                    <div class="card-title"><i class="fas fa-server"></i> Server Information</div>
                    <div class="system-info-grid">
                        <?php foreach ($serverInfo as $key => $value): ?>
                        <div class="system-info-item">
                            <div class="info-label"><?php echo $key; ?></div>
                            <div class="info-value"><?php echo $value; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="card-title"><i class="fas fa-cog"></i> System Settings</div>
                    <form method="POST" id="settingsForm">
                        <input type="hidden" name="action" value="update_settings">
                        
                        <div class="form-group">
                            <label for="site_title">Site Title</label>
                            <input type="text" id="site_title" name="site_title" class="form-control" value="Gallery Foto">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="site_description">Site Description</label>
                                <textarea id="site_description" name="site_description" class="form-control" rows="3">Platform berbagi foto terbaik</textarea>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="max_upload_size">Max Upload Size (MB)</label>
                                <input type="number" id="max_upload_size" name="max_upload_size" class="form-control" value="10">
                            </div>
                            <div class="form-group">
                                <label for="allowed_extensions">Allowed Extensions</label>
                                <input type="text" id="allowed_extensions" name="allowed_extensions" class="form-control" value="jpg,jpeg,png,gif">
                            </div>
                        </div>
                        
                        <div class="modal-footer" style="padding: 20px 0 0 0; border-top: 1px solid #f0f0f0;">
                            <button type="button" class="btn-secondary">Reset</button>
                            <button type="submit" class="btn-primary">Save Settings</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- =============== MODALS =============== -->
    <!-- Modal Edit User -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-user-edit"></i> Edit User</h3>
                <button class="close-modal" onclick="closeModal('editUserModal')">&times;</button>
            </div>
            <form method="POST" id="editUserForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="userid" id="edit_userid">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_username">Username</label>
                            <input type="text" id="edit_username" name="username" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_email">Email</label>
                            <input type="email" id="edit_email" name="email" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_nama_lengkap">Nama Lengkap</label>
                        <input type="text" id="edit_nama_lengkap" name="nama_lengkap" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_alamat">Alamat</label>
                        <textarea id="edit_alamat" name="alamat" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_role">Role</label>
                            <select id="edit_role" name="role" class="form-control" required>
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('editUserModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Add User -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-user-plus"></i> Add New User</h3>
                <button class="close-modal" onclick="closeModal('addUserModal')">&times;</button>
            </div>
            <form method="POST" id="addUserForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_user">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add_username">Username *</label>
                            <input type="text" id="add_username" name="username" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="add_password">Password *</label>
                            <input type="password" id="add_password" name="password" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add_email">Email *</label>
                            <input type="email" id="add_email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="add_nama_lengkap">Nama Lengkap *</label>
                            <input type="text" id="add_nama_lengkap" name="nama_lengkap" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="add_alamat">Alamat</label>
                        <textarea id="add_alamat" name="alamat" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add_role">Role *</label>
                            <select id="add_role" name="role" class="form-control" required>
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                                <option value="karyawan">Karyawan</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('addUserModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Album -->
    <div id="editAlbumModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-edit"></i> Edit Album</h3>
                <button class="close-modal" onclick="closeModal('editAlbumModal')">&times;</button>
            </div>
            <form method="POST" id="editAlbumForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_album">
                    <input type="hidden" name="albumid" id="edit_albumid">
                    
                    <div class="form-group">
                        <label for="edit_album_name">Nama Album *</label>
                        <input type="text" id="edit_album_name" name="nama_album" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_album_description">Deskripsi</label>
                        <textarea id="edit_album_description" name="deskripsi" class="form-control" rows="4"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_album_owner">Pemilik Album</label>
                        <select id="edit_album_owner" name="userid" class="form-control" required>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['userid']; ?>"><?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars($user['nama_lengkap']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('editAlbumModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Add Album -->
    <div id="addAlbumModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-plus"></i> Add New Album</h3>
                <button class="close-modal" onclick="closeModal('addAlbumModal')">&times;</button>
            </div>
            <form method="POST" id="addAlbumForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_album">
                    
                    <div class="form-group">
                        <label for="new_album_name">Nama Album *</label>
                        <input type="text" id="new_album_name" name="nama_album" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_album_description">Deskripsi</label>
                        <textarea id="new_album_description" name="deskripsi" class="form-control" rows="4"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_album_owner">Pemilik Album *</label>
                        <select id="new_album_owner" name="userid" class="form-control" required>
                            <option value="">Pilih User</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['userid']; ?>"><?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars($user['nama_lengkap']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('addAlbumModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Add Album</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Photo -->
    <div id="editPhotoModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-edit"></i> Edit Photo</h3>
                <button class="close-modal" onclick="closeModal('editPhotoModal')">&times;</button>
            </div>
            <form method="POST" id="editPhotoForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_photo">
                    <input type="hidden" name="fotoid" id="edit_fotoid">
                    
                    <div class="form-group">
                        <label for="edit_photo_title">Judul Foto *</label>
                        <input type="text" id="edit_photo_title" name="judul_foto" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_photo_description">Deskripsi</label>
                        <textarea id="edit_photo_description" name="deskripsi" class="form-control" rows="4"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_photo_album">Album *</label>
                        <select id="edit_photo_album" name="albumid" class="form-control" required>
                            <?php foreach ($albums as $album): ?>
                            <option value="<?php echo $album['albumid']; ?>"><?php echo htmlspecialchars($album['nama_album']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('editPhotoModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus -->
    <div id="confirmDeleteModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Konfirmasi Hapus</h3>
                <button class="close-modal" onclick="closeModal('confirmDeleteModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p id="deleteMessage" style="font-size: 16px; line-height: 1.5;"></p>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 15px;">
                    <p style="color: #721c24; margin: 0; font-size: 14px;">
                        <i class="fas fa-exclamation-circle"></i> <strong>Peringatan:</strong> Tindakan ini tidak dapat dibatalkan!
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('confirmDeleteModal')">Batal</button>
                <a id="confirmDeleteLink" href="#" class="btn-primary" style="background: #dc3545;">Hapus Permanen</a>
            </div>
        </div>
    </div>

    <script>
        // Tab Navigation
        function openTab(tabName) {
            // Sembunyikan semua tab
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Hapus active dari semua tombol
            const tabBtns = document.querySelectorAll('.tab-btn');
            tabBtns.forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Tampilkan tab yang dipilih
            document.getElementById(tabName).classList.add('active');
            
            // Aktifkan tombol yang dipilih
            event.currentTarget.classList.add('active');
            
            // Update URL tanpa reload
            history.pushState(null, null, `?tab=${tabName}`);
        }
        
        // Filter Table
        function filterTable(tableId, input) {
            const filter = input.value.toUpperCase();
            const table = document.getElementById(tableId);
            const tr = table.getElementsByTagName("tr");
            
            for (let i = 1; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName("td");
                let found = false;
                
                for (let j = 0; j < td.length; j++) {
                    if (td[j]) {
                        const txtValue = td[j].textContent || td[j].innerText;
                        if (txtValue.toUpperCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                
                tr[i].style.display = found ? "" : "none";
            }
        }
        
        // Modal Functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Open Edit User Modal
        function openEditUserModal(userid, username, email, nama_lengkap, alamat, role) {
            document.getElementById('edit_userid').value = userid;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_nama_lengkap').value = nama_lengkap;
            document.getElementById('edit_alamat').value = alamat;
            document.getElementById('edit_role').value = role;
            
            openModal('editUserModal');
        }
        
        // Open Edit Album Modal
        function openEditAlbumModal(albumid, nama_album, deskripsi, userid) {
            document.getElementById('edit_albumid').value = albumid;
            document.getElementById('edit_album_name').value = nama_album;
            document.getElementById('edit_album_description').value = deskripsi;
            document.getElementById('edit_album_owner').value = userid;
            
            openModal('editAlbumModal');
        }
        
        // Open Edit Photo Modal
        function openEditPhotoModal(fotoid, judul_foto, deskripsi, albumid) {
            document.getElementById('edit_fotoid').value = fotoid;
            document.getElementById('edit_photo_title').value = judul_foto;
            document.getElementById('edit_photo_description').value = deskripsi;
            document.getElementById('edit_photo_album').value = albumid;
            
            openModal('editPhotoModal');
        }
        
        // Confirm Delete
        function confirmDelete(type, id, name) {
            let message = '';
            let deleteUrl = '';
            
            switch(type) {
                case 'user':
                    message = `Apakah Anda yakin ingin menghapus user <strong>"${name}"</strong>?<br><br>Semua data user termasuk album, foto, likes, dan komentar akan dihapus permanen.`;
                    deleteUrl = `admin_panel.php?delete_user=${id}`;
                    break;
                case 'album':
                    message = `Apakah Anda yakin ingin menghapus album <strong>"${name}"</strong>?<br><br>Semua foto dalam album ini juga akan dihapus.`;
                    deleteUrl = `admin_panel.php?delete_album=${id}`;
                    break;
                case 'photo':
                    message = `Apakah Anda yakin ingin menghapus foto <strong>"${name}"</strong>?<br><br>Foto akan dihapus permanen dari sistem.`;
                    deleteUrl = `admin_panel.php?delete_photo=${id}`;
                    break;
                case 'comment':
                    message = `Apakah Anda yakin ingin menghapus ${name}?`;
                    deleteUrl = `admin_panel.php?delete_comment=${id}`;
                    break;
            }
            
            document.getElementById('deleteMessage').innerHTML = message;
            document.getElementById('confirmDeleteLink').href = deleteUrl;
            openModal('confirmDeleteModal');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target == modal) {
                    closeModal(modal.id);
                }
            });
        }
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => {
                    if (modal.style.display === 'flex') {
                        closeModal(modal.id);
                    }
                });
            }
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);
        
        // Initialize active tab from URL
        window.addEventListener('load', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if (tab) {
                openTab(tab);
            }
        });
        
        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const requiredFields = form.querySelectorAll('[required]');
                let valid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        valid = false;
                        field.style.borderColor = '#dc3545';
                    } else {
                        field.style.borderColor = '#e9ecef';
                    }
                });
                
                if (!valid) {
                    e.preventDefault();
                    alert('Harap isi semua field yang wajib diisi!');
                }
            });
        });
    </script>
</body>
</html>