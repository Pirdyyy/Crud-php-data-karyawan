<?php
session_start();
require_once 'functions.php';

// Aktifkan error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$results = [];
$message = '';

// Debug: Tampilkan informasi debugging jika parameter debug ada
$debugMode = isset($_GET['debug']) && $_GET['debug'] == '1';

// Tampilkan debug info dulu jika mode debug aktif
if ($debugMode && !empty($keyword)) {
    echo "<div class='debug-info'>";
    echo "<div class='debug-title'>🔧 DEBUG MODE AKTIF</div>";
    echo "<strong>Keyword:</strong> " . htmlspecialchars($keyword) . "<br>";
    echo "<strong>Session userid:</strong> " . (isset($_SESSION['userid']) ? $_SESSION['userid'] : 'Tidak login') . "<br>";
    echo "<strong>Session username:</strong> " . (isset($_SESSION['username']) ? $_SESSION['username'] : 'Tidak login') . "<br>";
    
    // Cek struktur tabel
    debugTableStructure();
    echo "</div>";
}

if (!empty($keyword)) {
    $results = searchContent($keyword);
    
    if (empty($results)) {
        $message = "Tidak ada hasil untuk '$keyword'";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: gallery.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Pencarian - Gallery Foto</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .search-header {
            background: #f8f9fa;
            padding: 20px 0;
            margin-bottom: 20px;
        }
        
        .search-box {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .search-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .search-form input {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .search-form button {
            padding: 12px 24px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .debug-link {
            padding: 8px 12px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
        }
        
        .debug-link:hover {
            background: #5a6268;
        }
        
        .results-info {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .section-title {
            color: #333;
            padding-bottom: 10px;
            border-bottom: 2px solid #007bff;
            margin-top: 30px;
        }
        
        .search-highlight {
            background-color: #ffeb3b;
            padding: 0 2px;
            border-radius: 2px;
            font-weight: bold;
        }
        
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .result-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            overflow: hidden;
            transition: transform 0.2s;
        }
        
        .result-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .album-cover {
            height: 150px;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: #6c757d;
        }
        
        .album-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .photo-img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .result-info {
            padding: 15px;
        }
        
        .result-title {
            font-weight: bold;
            margin-bottom: 8px;
            color: #333;
        }
        
        .result-title a {
            color: #007bff;
            text-decoration: none;
        }
        
        .result-title a:hover {
            text-decoration: underline;
        }
        
        .result-desc {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
            line-height: 1.4;
        }
        
        .result-meta {
            font-size: 12px;
            color: #888;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 5px;
        }
        
        .user-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .stats {
            display: flex;
            gap: 15px;
            margin-top: 5px;
        }
        
        .stat {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
        }
        
        .no-results {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Debug styles */
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #ddd;
            padding: 15px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 12px;
            color: #666;
            border-radius: 4px;
        }
        .debug-title {
            background: #007bff;
            color: white;
            padding: 5px 10px;
            font-weight: bold;
            margin: -15px -15px 15px -15px;
            border-radius: 4px 4px 0 0;
        }
        
        @media (max-width: 768px) {
            .results-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
            
            .search-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .debug-link {
                align-self: flex-start;
            }
        }
        
        @media (max-width: 480px) {
            .results-grid {
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
                    <input type="text" name="keyword" placeholder="Cari album atau foto..." 
                           value="<?php echo htmlspecialchars($keyword); ?>">
                    <?php if(!empty($keyword)): ?>
                        <!-- <a href="search.php?keyword=<?php echo urlencode($keyword); ?>&debug=1" class="debug-link">
                            🔧 Debug
                        </a> -->
                    <?php endif; ?>
                </form>

                <div class="nav-links">
                    <?php if (isset($_SESSION['userid'])): ?>
                        <a href="gallery.php">Beranda</a>
                        <a href="gallery.php">Galeri</a>
                        <a href="dashboard.php">Dashboard</a>
                        <a href="#" onclick="openModal('createAlbumModal')">Buat Album</a>
                        <a href="#" onclick="openModal('uploadFotoModal')">Upload Foto</a>
                        <a href="gf.php?logout=1">Logout (<?php echo $_SESSION['username']; ?>)</a>
                    <?php else: ?>
                        <a href="gallery.php">Beranda</a>
                        <a href="gallery.php">Galeri</a>
                        <a href="#" onclick="openModal('loginModal')">Login</a>
                        <a href="#" onclick="openModal('registerModal')">Daftar</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <div class="container main-content">
        <?php if (!empty($keyword)): ?>
            <div class="results-info">
                <strong>Hasil pencarian untuk:</strong> "<?php echo htmlspecialchars($keyword); ?>"
                <span style="margin-left: 20px; color: #666;">
                    Ditemukan <?php echo count($results); ?> hasil
                </span>
                
                <?php if (!$debugMode && !empty($keyword)): ?>
                    <!-- <div style="margin-top: 10px;">
                        <a href="search.php?keyword=<?php echo urlencode($keyword); ?>&debug=1" 
                           style="font-size: 12px; color: #666; text-decoration: none;">
                           🔧 Aktifkan Debug Mode
                        </a>
                    </div> -->
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($message)): ?>
            <div class="no-results">
                <h3><?php echo htmlspecialchars($message); ?></h3>
                <p>Coba gunakan kata kunci yang berbeda</p>
                
                <?php if (!$debugMode): ?>
                    <!-- <p>
                        <a href="search.php?keyword=<?php echo urlencode($keyword); ?>&debug=1" 
                           style="font-size: 12px; color: #007bff;">
                           🔧 Debug pencarian ini
                        </a>
                    </p> -->
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($results) && !empty($keyword)): ?>
            <!-- Album Results -->
            <?php 
            $albumResults = array_filter($results, function($item) { 
                return $item['type'] === 'album'; 
            });
            ?>
            
            <?php if (!empty($albumResults)): ?>
                <h2 class="section-title">Album (<?php echo count($albumResults); ?>)</h2>
                <div class="results-grid">
                    <?php foreach ($albumResults as $item): ?>
                        <div class="result-card">
                            <a href="album.php?album_id=<?php echo $item['id']; ?>">
                                <div class="album-cover">
                                    <?php if ($item['cover']): ?>
                                        <img src="<?php echo htmlspecialchars($item['cover']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['judul']); ?>">
                                    <?php else: ?>
                                        📁
                                    <?php endif; ?>
                                </div>
                            </a>
                            <div class="result-info">
                                <div class="result-title">
                                    <a href="album.php?album_id=<?php echo $item['id']; ?>">
                                        <?php echo highlightKeywords($item['judul'], $keyword); ?>
                                    </a>
                                </div>
                                <div class="result-desc">
                                    <?php echo highlightKeywords($item['deskripsi'], $keyword); ?>
                                </div>
                                <div class="user-info">
                                    <?php if ($item['user_photo']): ?>
                                        <img src="<?php echo htmlspecialchars($item['user_photo']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['username']); ?>"
                                             class="user-avatar">
                                    <?php endif; ?>
                                    <span><?php echo htmlspecialchars($item['username']); ?></span>
                                </div>
                                <div class="result-meta">
                                    <?php echo $item['jumlah_foto']; ?> foto • <?php echo date('d M Y', strtotime($item['tanggal'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Photo Results -->
            <?php 
            $photoResults = array_filter($results, function($item) { 
                return $item['type'] === 'foto'; 
            });
            ?>
            
            <?php if (!empty($photoResults)): ?>
                <h2 class="section-title">Foto (<?php echo count($photoResults); ?>)</h2>
                <div class="results-grid">
                    <?php foreach ($photoResults as $item): ?>
                        <div class="result-card">
                            <img src="<?php echo $item['lokasifile']; ?>" 
                                 alt="<?php echo htmlspecialchars($item['judul']); ?>"
                                 class="photo-img">
                            <div class="result-info">
                                <div class="result-title">
                                    <?php echo highlightKeywords($item['judul'], $keyword); ?>
                                </div>
                                <div class="result-desc">
                                    <?php echo highlightKeywords($item['deskripsi'], $keyword); ?>
                                </div>
                                <div class="user-info">
                                    <?php if ($item['user_photo']): ?>
                                        <img src="<?php echo htmlspecialchars($item['user_photo']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['username']); ?>"
                                             class="user-avatar">
                                    <?php endif; ?>
                                    <span><?php echo htmlspecialchars($item['username']); ?></span>
                                </div>
                                <div class="stats">
                                    <div class="stat">❤️ <?php echo $item['jumlah_like']; ?></div>
                                    <div class="stat">💬 <?php echo $item['jumlah_komentar']; ?></div>
                                </div>
                                <div class="result-meta">
                                    <?php echo date('d M Y', strtotime($item['tanggal'])); ?>
                                    <?php if ($item['album_judul']): ?>
                                        • Album: <?php echo highlightKeywords($item['album_judul'], $keyword); ?>
                                    <?php endif; ?>
                                </div>
                                <a href="foto.php?foto_id=<?php echo $item['id']; ?>" 
                                   style="display: inline-block; background: #28a745; color: white; padding: 5px 10px; border-radius: 3px; text-decoration: none; font-size: 12px; margin-top: 8px;">
                                    Lihat Foto
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
        <?php elseif (empty($keyword)): ?>
            <div class="no-results">
                <h3>Cari Album dan Foto</h3>
                <p>Gunakan form di atas untuk mencari konten berdasarkan judul, deskripsi, atau username</p>
            </div>
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

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });
        };
    </script>
</body>
</html>