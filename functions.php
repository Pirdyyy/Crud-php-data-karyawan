<?php
require_once 'config.php';

// Fungsi untuk mendapatkan koneksi database
function getDBConnection()
{
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Koneksi database gagal: " . $e->getMessage());
        die("Koneksi database gagal: " . $e->getMessage());
    }
}

// Fungsi untuk memulai session dengan aman
function startSecureSession()
{
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
        // Regenerate session ID untuk mencegah session fixation
        session_regenerate_id(true);
    }
}

// ========== FUNGSI PENCARIAN YANG DIPERBAIKI ==========

// Fungsi untuk mencari konten (album dan foto) - SESUAI STRUKTUR DATABASE
function searchContent($keyword) {
    $pdo = getDBConnection();
    $results = [];
    
    // DEBUG: Aktifkan debugging dengan mengatur parameter GET ?debug=1
    $debugMode = isset($_GET['debug']) && $_GET['debug'] == '1';
    
    if ($debugMode) {
        echo "<div style='background: #f0f0f0; padding: 10px; border: 1px solid #ccc; margin-bottom: 10px;'>";
        echo "<strong>🔍 DEBUG MODE AKTIF - Keyword: '$keyword'</strong><br>";
    }
    
    // Membersihkan keyword
    $keyword = trim($keyword);
    if (empty($keyword)) {
        if ($debugMode) echo "⚠️ Keyword kosong, mengembalikan array kosong<br>";
        return $results;
    }
    
    $searchTerm = "%" . $keyword . "%";
    if ($debugMode) echo "🔎 Search term: '$searchTerm'<br>";
    
    try {
        // DEBUG: Cek apakah tabel album ada dan berisi data
        if ($debugMode) {
            $debugStmt = $pdo->prepare("SELECT COUNT(*) as total FROM album");
            $debugStmt->execute();
            $debugResult = $debugStmt->fetch(PDO::FETCH_ASSOC);
            echo "📊 Total album dalam database: " . $debugResult['total'] . "<br>";
            
            // DEBUG: Cek apakah ada album dengan keyword
            $debugKeywordStmt = $pdo->prepare("SELECT albumid, nama_album FROM album WHERE nama_album LIKE ?");
            $debugKeywordStmt->execute([$searchTerm]);
            $debugKeywordResults = $debugKeywordStmt->fetchAll(PDO::FETCH_ASSOC);
            echo "🔍 Album yang cocok dengan keyword: " . count($debugKeywordResults) . "<br>";
            foreach ($debugKeywordResults as $debugAlbum) {
                echo "&nbsp;&nbsp;- Album ID: " . $debugAlbum['albumid'] . ", Nama: " . $debugAlbum['nama_album'] . "<br>";
            }
        }
        
        // ================== MENCARI ALBUM ==================
        if ($debugMode) echo "<br>📁 <strong>MENCARI ALBUM...</strong><br>";
        
        // PERBAIKAN: Query sederhana untuk menghindari error syntax
        $albumQuery = $pdo->prepare("
            SELECT 
                'album' as type, 
                a.albumid as id, 
                a.nama_album as judul, 
                a.deskripsi, 
                a.tanggal, 
                a.userid,
                u.username,
                u.foto_profile as user_photo
            FROM album a 
            JOIN user u ON a.userid = u.userid 
            WHERE a.nama_album LIKE :keyword OR a.deskripsi LIKE :keyword
            ORDER BY a.tanggal DESC
        ");
        
        if ($debugMode) {
            echo "🎯 Executing album query...<br>";
        }
        
        $albumQuery->execute([':keyword' => $searchTerm]);
        $albumCount = $albumQuery->rowCount();
        
        if ($debugMode) {
            echo "✅ Query album selesai. Ditemukan $albumCount album<br>";
        }
        
        $albumFound = 0;
        while ($album = $albumQuery->fetch(PDO::FETCH_ASSOC)) {
            $albumFound++;
            if ($debugMode) echo "&nbsp;&nbsp;📝 Memproses album $albumFound: '" . $album['judul'] . "' (ID: " . $album['id'] . ")<br>";
            
            // Hitung jumlah foto dalam album
            $countQuery = $pdo->prepare("SELECT COUNT(*) as jumlah_foto FROM foto WHERE albumid = ?");
            $countQuery->execute([$album['id']]);
            $countResult = $countQuery->fetch(PDO::FETCH_ASSOC);
            $jumlah_foto = $countResult ? $countResult['jumlah_foto'] : 0;
            
            // Get album cover - ambil foto pertama jika ada
            $cover = null;
            $coverQuery = $pdo->prepare("
                SELECT lokasifile FROM foto 
                WHERE albumid = ? 
                ORDER BY fotoid ASC LIMIT 1
            ");
            $coverQuery->execute([$album['id']]);
            $coverResult = $coverQuery->fetch(PDO::FETCH_ASSOC);
            $cover = $coverResult ? $coverResult['lokasifile'] : null;
            
            $results[] = [
                'type' => 'album',
                'id' => $album['id'],
                'judul' => $album['judul'],
                'deskripsi' => $album['deskripsi'],
                'tanggal' => $album['tanggal'],
                'username' => $album['username'],
                'user_photo' => $album['user_photo'],
                'jumlah_foto' => $jumlah_foto,
                'cover' => $cover
            ];
            
            if ($debugMode) echo "&nbsp;&nbsp;✅ Album '" . $album['judul'] . "' ditambahkan ke hasil<br>";
        }
        
        if ($debugMode && $albumFound == 0) {
            echo "&nbsp;&nbsp;⚠️ Tidak ada album yang ditemukan<br>";
        }
        
        // ================== MENCARI FOTO ==================
        if ($debugMode) echo "<br>📸 <strong>MENCARI FOTO...</strong><br>";
        
        // PERBAIKAN: Query sederhana untuk menghindari error syntax
        $fotoQuery = $pdo->prepare("
            SELECT 
                'foto' as type,
                f.fotoid as id,
                f.judul_foto as judul,
                f.deskripsi,
                f.tanggal_unggah as tanggal,
                f.lokasifile,
                f.albumid,
                f.userid,
                u.username,
                u.foto_profile as user_photo
            FROM foto f 
            JOIN user u ON f.userid = u.userid 
            WHERE f.judul_foto LIKE :keyword OR f.deskripsi LIKE :keyword
            ORDER BY f.tanggal_unggah DESC
        ");
        
        if ($debugMode) {
            echo "🎯 Executing foto query...<br>";
        }
        
        $fotoQuery->execute([':keyword' => $searchTerm]);
        $fotoCount = $fotoQuery->rowCount();
        
        if ($debugMode) {
            echo "✅ Query foto selesai. Ditemukan $fotoCount foto<br>";
        }
        
        $fotoFound = 0;
        while ($foto = $fotoQuery->fetch(PDO::FETCH_ASSOC)) {
            $fotoFound++;
            if ($debugMode) echo "&nbsp;&nbsp;📝 Memproses foto $fotoFound: '" . $foto['judul'] . "' (ID: " . $foto['id'] . ")<br>";
            
            // Ambil nama album
            $album_judul = null;
            if ($foto['albumid']) {
                $albumQuery = $pdo->prepare("SELECT nama_album FROM album WHERE albumid = ?");
                $albumQuery->execute([$foto['albumid']]);
                $albumResult = $albumQuery->fetch(PDO::FETCH_ASSOC);
                $album_judul = $albumResult ? $albumResult['nama_album'] : null;
            }
            
            // Hitung jumlah like
            $likeQuery = $pdo->prepare("SELECT COUNT(*) as jumlah_like FROM likes WHERE fotoid = ?");
            $likeQuery->execute([$foto['id']]);
            $likeResult = $likeQuery->fetch(PDO::FETCH_ASSOC);
            $jumlah_like = $likeResult ? $likeResult['jumlah_like'] : 0;
            
            // Hitung jumlah komentar
            $commentQuery = $pdo->prepare("SELECT COUNT(*) as jumlah_komentar FROM komentar WHERE fotoid = ?");
            $commentQuery->execute([$foto['id']]);
            $commentResult = $commentQuery->fetch(PDO::FETCH_ASSOC);
            $jumlah_komentar = $commentResult ? $commentResult['jumlah_komentar'] : 0;
            
            $results[] = [
                'type' => 'foto',
                'id' => $foto['id'],
                'judul' => $foto['judul'],
                'deskripsi' => $foto['deskripsi'],
                'tanggal' => $foto['tanggal'],
                'lokasifile' => $foto['lokasifile'],
                'albumid' => $foto['albumid'],
                'album_judul' => $album_judul,
                'username' => $foto['username'],
                'user_photo' => $foto['user_photo'],
                'jumlah_like' => $jumlah_like,
                'jumlah_komentar' => $jumlah_komentar
            ];
            
            if ($debugMode) echo "&nbsp;&nbsp;✅ Foto '" . $foto['judul'] . "' ditambahkan ke hasil<br>";
        }
        
        if ($debugMode && $fotoFound == 0) {
            echo "&nbsp;&nbsp;⚠️ Tidak ada foto yang ditemukan<br>";
        }
        
        // Urutkan hasil berdasarkan relevansi
        if ($debugMode) echo "<br>📊 <strong>Mengurutkan " . count($results) . " hasil berdasarkan relevansi...</strong><br>";
        
        usort($results, function($a, $b) use ($keyword) {
            return calculateRelevanceScore($b, $keyword) <=> calculateRelevanceScore($a, $keyword);
        });
        
        if ($debugMode) {
            echo "✅ Pencarian selesai. Total hasil ditemukan: " . count($results) . "<br>";
            
            // Tampilkan semua hasil yang ditemukan
            echo "<br>📋 <strong>HASIL AKHIR:</strong><br>";
            if (count($results) > 0) {
                foreach ($results as $index => $item) {
                    $no = $index + 1;
                    echo "$no. [" . strtoupper($item['type']) . "] " . $item['judul'] . " (oleh: " . $item['username'] . ")<br>";
                }
            } else {
                echo "⚠️ Tidak ada hasil yang ditemukan<br>";
            }
            
            echo "</div>"; // Tutup debug container
        }
        
        return $results;
        
    } catch (PDOException $e) {
        if ($debugMode) {
            echo "<div style='background: #ffcccc; padding: 10px; border: 1px solid red; margin-bottom: 10px;'>";
            echo "❌ <strong>ERROR dalam searchContent:</strong> " . $e->getMessage() . "<br>";
            echo "🔧 <strong>SQL Error Info:</strong><br>";
            echo "<pre>" . htmlspecialchars(print_r($pdo->errorInfo(), true)) . "</pre>";
            echo "</div>";
        }
        
        error_log("❌ ERROR dalam searchContent: " . $e->getMessage());
        error_log("🔧 SQL Error Info: " . print_r($pdo->errorInfo(), true));
        
        return [];
    }
}

// Fungsi untuk menghitung skor relevansi
function calculateRelevanceScore($item, $keyword) {
    $score = 0;
    $keyword = strtolower($keyword);
    $keywords = explode(' ', $keyword);
    
    foreach ($keywords as $kw) {
        if (strlen(trim($kw)) < 2) continue;
        
        // Cek kecocokan di judul
        if (stripos(strtolower($item['judul']), $kw) !== false) {
            $score += 3;
        }
        
        // Cek kecocokan di deskripsi
        if (stripos(strtolower($item['deskripsi']), $kw) !== false) {
            $score += 1;
        }
        
        // Untuk album, cek jumlah foto
        if ($item['type'] == 'album') {
            $score += ($item['jumlah_foto'] * 0.01);
        }
        
        // Untuk foto, cek jumlah like dan komentar
        if ($item['type'] == 'foto') {
            $score += ($item['jumlah_like'] * 0.05);
            $score += ($item['jumlah_komentar'] * 0.1);
        }
    }
    
    return $score;
}

// Fungsi untuk highlight keyword dalam teks
function highlightKeywords($text, $keyword) {
    if (empty($keyword) || empty($text)) {
        return htmlspecialchars($text);
    }
    
    $keywords = explode(' ', $keyword);
    $highlighted = htmlspecialchars($text);
    
    foreach ($keywords as $kw) {
        if (strlen(trim($kw)) > 1) {
            $pattern = '/(' . preg_quote($kw, '/') . ')/i';
            $replacement = '<span class="search-highlight">$1</span>';
            $highlighted = preg_replace($pattern, $replacement, $highlighted);
        }
    }
    
    return $highlighted;
}

// ========== FUNGSI LAINNYA YANG DIPERBAIKI ==========

// Fungsi untuk membuat album (sesuai struktur)
function createAlbum($nama_album, $deskripsi, $userid)
{
    $pdo = getDBConnection();

    // Validasi input
    if (empty($nama_album)) {
        return ['success' => false, 'message' => 'Nama album wajib diisi'];
    }

    try {
        $sql = "INSERT INTO album (nama_album, deskripsi, userid) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nama_album, $deskripsi, $userid]);

        $albumid = $pdo->lastInsertId();
        return ['success' => true, 'albumid' => $albumid, 'message' => 'Album berhasil dibuat'];
    } catch (PDOException $e) {
        error_log("Gagal membuat album: " . $e->getMessage());
        return ['success' => false, 'message' => 'Gagal membuat album: ' . $e->getMessage()];
    }
}

// Fungsi untuk upload foto (sesuai struktur)
function uploadFoto($file, $judul, $deskripsi, $albumid, $userid)
{
    // Validasi input
    if (empty($judul) || empty($albumid)) {
        return ['success' => false, 'message' => 'Judul dan album wajib diisi'];
    }

    $target_dir = "uploads/fotos/";

    // Buat folder jika belum ada
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    // Validasi file upload
    $validation_errors = validateUploadedFile($file);
    if (!empty($validation_errors)) {
        return ['success' => false, 'message' => implode(', ', $validation_errors)];
    }

    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;

    // Pindahkan file
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        // Simpan ke database dengan path yang benar
        $pdo = getDBConnection();
        $sql = "INSERT INTO foto (judul_foto, deskripsi, lokasifile, albumid, userid) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);

        try {
            if ($stmt->execute([$judul, $deskripsi, $target_file, $albumid, $userid])) {
                return ['success' => true, 'message' => 'Foto berhasil diupload', 'file_path' => $target_file];
            } else {
                // Hapus file jika gagal menyimpan ke database
                unlink($target_file);
                return ['success' => false, 'message' => 'Gagal menyimpan data foto'];
            }
        } catch (PDOException $e) {
            unlink($target_file);
            error_log("Gagal upload foto: " . $e->getMessage());
            return ['success' => false, 'message' => 'Gagal menyimpan data foto: ' . $e->getMessage()];
        }
    }

    return ['success' => false, 'message' => 'Gagal mengupload file'];
}

// Fungsi untuk registrasi user
function registerUser($username, $password, $email, $nama_lengkap, $alamat, $role = 'user')
{
    $pdo = getDBConnection();

    // Validasi input
    if (empty($username) || empty($password) || empty($email) || empty($nama_lengkap)) {
        return ['success' => false, 'message' => 'Semua field wajib diisi'];
    }

    // Validasi email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Format email tidak valid'];
    }

    // Validasi username (hanya huruf, angka, underscore)
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        return ['success' => false, 'message' => 'Username hanya boleh mengandung huruf, angka, dan underscore'];
    }

    // Cek apakah username sudah ada
    if (isUsernameExists($username)) {
        return ['success' => false, 'message' => 'Username sudah digunakan'];
    }

    // Cek apakah email sudah ada
    if (isEmailExists($email)) {
        return ['success' => false, 'message' => 'Email sudah digunakan'];
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO user (username, password, email, nama_lengkap, alamat, role) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute([$username, $hashed_password, $email, $nama_lengkap, $alamat, $role]);
        return ['success' => true, 'message' => 'Registrasi berhasil'];
    } catch (PDOException $e) {
        error_log("Registrasi gagal: " . $e->getMessage());
        return ['success' => false, 'message' => 'Registrasi gagal: ' . $e->getMessage()];
    }
}

// Cek apakah username sudah ada
function isUsernameExists($username)
{
    $pdo = getDBConnection();
    $sql = "SELECT userid FROM user WHERE username = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username]);
    return $stmt->rowCount() > 0;
}

// Cek apakah email sudah ada
function isEmailExists($email)
{
    $pdo = getDBConnection();
    $sql = "SELECT userid FROM user WHERE email = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    return $stmt->rowCount() > 0;
}

// Fungsi untuk login
function loginUser($username, $password)
{
    $pdo = getDBConnection();

    $sql = "SELECT * FROM user WHERE username = ? OR email = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Start secure session
        startSecureSession();

        // Set session variables
        $_SESSION['userid'] = $user['userid'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
        $_SESSION['alamat'] = $user['alamat'];
        $_SESSION['foto_profile'] = $user['foto_profile'];
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    return false;
}

// Fungsi untuk logout
function logoutUser()
{
    startSecureSession();
        
    // Hapus semua session variables
    $_SESSION = array();
    
    // Hapus session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Hancurkan session
    session_destroy();
}

// Fungsi untuk like foto
function likeFoto($fotoid, $userid)
{
    $pdo = getDBConnection();

    try {
        // Cek apakah sudah like sebelumnya
        $sql = "SELECT * FROM likes WHERE fotoid = ? AND userid = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fotoid, $userid]);

        if ($stmt->rowCount() > 0) {
            // Jika sudah like, maka unlike
            $sql = "DELETE FROM likes WHERE fotoid = ? AND userid = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$fotoid, $userid])) {
                return ['action' => 'unliked', 'success' => true];
            }
        } else {
            // Jika belum like, maka like
            $sql = "INSERT INTO likes (fotoid, userid) VALUES (?, ?)";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$fotoid, $userid])) {
                return ['action' => 'liked', 'success' => true];
            }
        }
    } catch (PDOException $e) {
        error_log("Gagal like/unlike foto: " . $e->getMessage());
    }

    return ['success' => false];
}

// Fungsi untuk mendapatkan jumlah like
function getLikesCount($fotoid)
{
    $pdo = getDBConnection();
    $sql = "SELECT COUNT(*) as total FROM likes WHERE fotoid = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fotoid]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'];
}

// Fungsi untuk menambah komentar
function addComment($fotoid, $userid, $isi_komen)
{
    $pdo = getDBConnection();

    // Validasi komentar tidak kosong
    if (empty(trim($isi_komen))) {
        return ['success' => false, 'message' => 'Komentar tidak boleh kosong'];
    }

    // Validasi panjang komentar
    if (strlen(trim($isi_komen)) > 500) {
        return ['success' => false, 'message' => 'Komentar terlalu panjang (maksimal 500 karakter)'];
    }

    $sql = "INSERT INTO komentar (fotoid, userid, isi_komen) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute([$fotoid, $userid, trim($isi_komen)]);
        $komentarid = $pdo->lastInsertId();
        return ['success' => true, 'komentarid' => $komentarid];
    } catch (PDOException $e) {
        error_log("Gagal menambah komentar: " . $e->getMessage());
        return ['success' => false, 'message' => 'Gagal menambah komentar: ' . $e->getMessage()];
    }
}

// Fungsi untuk menghapus komentar
function deleteComment($komentarid, $userid, $role)
{
    $pdo = getDBConnection();

    try {
        // Cek apakah user adalah pemilik komentar atau admin/karyawan
        $sql = "SELECT * FROM komentar WHERE komentarid = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$komentarid]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($comment && ($comment['userid'] == $userid || $role == 'admin' || $role == 'karyawan')) {
            $sql = "DELETE FROM komentar WHERE komentarid = ?";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$komentarid]);
        }
        return false;
    } catch (PDOException $e) {
        error_log("Gagal menghapus komentar: " . $e->getMessage());
        return false;
    }
}

// Fungsi untuk menghapus album
function deleteAlbum($albumid, $userid, $role)
{
    $pdo = getDBConnection();

    try {
        // Cek apakah user adalah pemilik album atau admin/karyawan
        $sql = "SELECT * FROM album WHERE albumid = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$albumid]);
        $album = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($album && ($album['userid'] == $userid || $role == 'admin' || $role == 'karyawan')) {
            // Hapus semua foto dalam album terlebih dahulu
            $fotos = getFotosInAlbum($albumid);
            foreach ($fotos as $foto) {
                deleteFoto($foto['fotoid'], $userid, $role);
            }

            $sql = "DELETE FROM album WHERE albumid = ?";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$albumid]);
        }
        return false;
    } catch (PDOException $e) {
        error_log("Gagal menghapus album: " . $e->getMessage());
        return false;
    }
}

// Fungsi untuk menghapus foto
function deleteFoto($fotoid, $userid, $role)
{
    $pdo = getDBConnection();

    try {
        // Cek apakah user adalah pemilik foto atau admin/karyawan
        $sql = "SELECT * FROM foto WHERE fotoid = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fotoid]);
        $foto = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($foto && ($foto['userid'] == $userid || $role == 'admin' || $role == 'karyawan')) {
            // Hapus file fisik jika ada
            if (file_exists($foto['lokasifile'])) {
                unlink($foto['lokasifile']);
            }

            // Hapus likes terkait
            $sql = "DELETE FROM likes WHERE fotoid = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$fotoid]);

            // Hapus komentar terkait
            $sql = "DELETE FROM komentar WHERE fotoid = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$fotoid]);

            // Hapus foto
            $sql = "DELETE FROM foto WHERE fotoid = ?";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$fotoid]);
        }
        return false;
    } catch (PDOException $e) {
        error_log("Gagal menghapus foto: " . $e->getMessage());
        return false;
    }
}

// Fungsi untuk mendapatkan semua album
function getAllAlbums($limit = null)
{
    $pdo = getDBConnection();

    $sql = "SELECT a.*, u.username, COUNT(f.fotoid) as jumlah_foto 
            FROM album a 
            LEFT JOIN user u ON a.userid = u.userid 
            LEFT JOIN foto f ON a.albumid = f.albumid 
            GROUP BY a.albumid 
            ORDER BY a.tanggal DESC";

    if ($limit) {
        $sql .= " LIMIT " . intval($limit);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fungsi untuk mendapatkan semua album (untuk admin/karyawan)
function getAllAlbumsForAdmin()
{
    $pdo = getDBConnection();

    $sql = "SELECT a.*, u.username, COUNT(f.fotoid) as jumlah_foto 
            FROM album a 
            LEFT JOIN user u ON a.userid = u.userid 
            LEFT JOIN foto f ON a.albumid = f.albumid 
            GROUP BY a.albumid 
            ORDER BY a.tanggal DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fungsi untuk mendapatkan semua foto (untuk admin/karyawan)
function getAllFotosForAdmin($limit = null)
{
    $pdo = getDBConnection();

    $sql = "SELECT f.*, u.username, a.nama_album,
            (SELECT COUNT(*) FROM likes l WHERE l.fotoid = f.fotoid) as jumlah_like,
            (SELECT COUNT(*) FROM komentar k WHERE k.fotoid = f.fotoid) as jumlah_komentar
            FROM foto f 
            LEFT JOIN user u ON f.userid = u.userid 
            LEFT JOIN album a ON f.albumid = a.albumid 
            ORDER BY f.tanggal_unggah DESC";

    if ($limit) {
        $sql .= " LIMIT " . intval($limit);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fungsi untuk mendapatkan total users
function getTotalUsers()
{
    $pdo = getDBConnection();
    $sql = "SELECT COUNT(*) as total FROM user";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'];
}

// Fungsi untuk mendapatkan total likes
function getTotalLikes()
{
    $pdo = getDBConnection();
    $sql = "SELECT COUNT(*) as total FROM likes";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'];
}

// Fungsi untuk mendapatkan album by user
function getUserAlbums($userid)
{
    $pdo = getDBConnection();

    $sql = "SELECT a.*, COUNT(f.fotoid) as jumlah_foto 
            FROM album a 
            LEFT JOIN foto f ON a.albumid = f.albumid 
            WHERE a.userid = ? 
            GROUP BY a.albumid 
            ORDER BY a.tanggal DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userid]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ========== FUNGSI BARU YANG DITAMBAHKAN ==========

// Fungsi untuk mendapatkan foto user
function getUserPhotos($userid, $limit = null)
{
    $pdo = getDBConnection();

    $sql = "SELECT f.*, u.username, a.nama_album,
            (SELECT COUNT(*) FROM likes l WHERE l.fotoid = f.fotoid) as jumlah_like,
            (SELECT COUNT(*) FROM komentar k WHERE k.fotoid = f.fotoid) as jumlah_komentar
            FROM foto f 
            LEFT JOIN user u ON f.userid = u.userid 
            LEFT JOIN album a ON f.albumid = a.albumid 
            WHERE f.userid = ? 
            ORDER BY f.tanggal_unggah DESC";

    if ($limit) {
        $sql .= " LIMIT " . intval($limit);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userid]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fungsi untuk mendapatkan total like yang diterima user
function getUserTotalLikes($userid)
{
    $pdo = getDBConnection();
    
    $sql = "SELECT COUNT(*) as total 
            FROM likes l 
            JOIN foto f ON l.fotoid = f.fotoid 
            WHERE f.userid = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userid]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'];
}

// ========== FUNGSI YANG SUDAH ADA ==========

// Fungsi untuk mendapatkan foto dalam album
function getFotosInAlbum($albumid)
{
    $pdo = getDBConnection();

    $sql = "SELECT f.*, u.username, 
            (SELECT COUNT(*) FROM likes l WHERE l.fotoid = f.fotoid) as jumlah_like,
            (SELECT COUNT(*) FROM komentar k WHERE k.fotoid = f.fotoid) as jumlah_komentar
            FROM foto f 
            LEFT JOIN user u ON f.userid = u.userid 
            WHERE f.albumid = ? 
            ORDER BY f.tanggal_unggah DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$albumid]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fungsi untuk mendapatkan komentar pada foto
function getCommentsOnFoto($fotoid)
{
    $pdo = getDBConnection();

    $sql = "SELECT k.*, u.username, u.foto_profile 
            FROM komentar k 
            LEFT JOIN user u ON k.userid = u.userid 
            WHERE k.fotoid = ? 
            ORDER BY k.tanggal_komen ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fotoid]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fungsi untuk mengecek apakah user sudah like foto
function isLiked($fotoid, $userid)
{
    $pdo = getDBConnection();

    $sql = "SELECT * FROM likes WHERE fotoid = ? AND userid = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fotoid, $userid]);

    return $stmt->rowCount() > 0;
}

// Fungsi untuk mendapatkan statistik user
function getUserStats($userid)
{
    $pdo = getDBConnection();

    $stats = [
        'total_albums' => 0,
        'total_fotos' => 0,
        'total_likes' => 0,
        'total_fotos_liked' => 0
    ];

    try {
        // Total albums
        $sql = "SELECT COUNT(*) as total FROM album WHERE userid = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userid]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_albums'] = $result['total'];

        // Total foto
        $sql = "SELECT COUNT(*) as total FROM foto WHERE userid = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userid]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_fotos'] = $result['total'];

        // Total like yang diberikan user
        $sql = "SELECT COUNT(*) as total FROM likes WHERE userid = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userid]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_likes'] = $result['total'];

        // Total like yang diterima foto user
        $sql = "SELECT COUNT(*) as total FROM likes l 
                JOIN foto f ON l.fotoid = f.fotoid 
                WHERE f.userid = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userid]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_fotos_liked'] = $result['total'];
    } catch (PDOException $e) {
        error_log("Gagal mendapatkan statistik user: " . $e->getMessage());
    }

    return $stats;
}

// Fungsi untuk update profile user
function updateProfile($userid, $nama_lengkap, $email, $alamat, $foto_profile = null)
{
    $pdo = getDBConnection();

    // Validasi input
    if (empty($nama_lengkap) || empty($email)) {
        return ['success' => false, 'message' => 'Nama lengkap dan email wajib diisi'];
    }

    // Validasi email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Format email tidak valid'];
    }

    // Cek apakah email sudah digunakan oleh user lain
    $sql = "SELECT userid FROM user WHERE email = ? AND userid != ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email, $userid]);

    if ($stmt->rowCount() > 0) {
        return ['success' => false, 'message' => 'Email sudah digunakan oleh user lain'];
    }

    try {
        if ($foto_profile) {
            $sql = "UPDATE user SET nama_lengkap = ?, email = ?, alamat = ?, foto_profile = ? WHERE userid = ?";
            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute([$nama_lengkap, $email, $alamat, $foto_profile, $userid]);
        } else {
            $sql = "UPDATE user SET nama_lengkap = ?, email = ?, alamat = ? WHERE userid = ?";
            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute([$nama_lengkap, $email, $alamat, $userid]);
        }

        if ($success) {
            // Update session jika session sudah started
            if (session_status() == PHP_SESSION_ACTIVE) {
                $_SESSION['nama_lengkap'] = $nama_lengkap;
                $_SESSION['email'] = $email;
                $_SESSION['alamat'] = $alamat;
                if ($foto_profile) {
                    $_SESSION['foto_profile'] = $foto_profile;
                }
            }
            return ['success' => true, 'message' => 'Profile berhasil diupdate'];
        }

        return ['success' => false, 'message' => 'Gagal mengupdate profile'];
    } catch (PDOException $e) {
        error_log("Gagal update profile: " . $e->getMessage());
        return ['success' => false, 'message' => 'Gagal mengupdate profile: ' . $e->getMessage()];
    }
}

// Fungsi untuk mendapatkan data user
function getUserData($userid)
{
    $pdo = getDBConnection();

    $sql = "SELECT userid, username, email, nama_lengkap, alamat, foto_profile, role, tanggal_dibuat 
            FROM user WHERE userid = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userid]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fungsi untuk mendapatkan foto terbaru
function getLatestFotos($limit = 12)
{
    $pdo = getDBConnection();

    $sql = "SELECT f.*, u.username, a.nama_album,
            (SELECT COUNT(*) FROM likes l WHERE l.fotoid = f.fotoid) as jumlah_like,
            (SELECT COUNT(*) FROM komentar k WHERE k.fotoid = f.fotoid) as jumlah_komentar
            FROM foto f 
            LEFT JOIN user u ON f.userid = u.userid 
            LEFT JOIN album a ON f.albumid = a.albumid 
            ORDER BY f.tanggal_unggah DESC 
            LIMIT ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([intval($limit)]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fungsi untuk mendapatkan data foto by ID
function getFotoById($fotoid)
{
    $pdo = getDBConnection();

    $sql = "SELECT f.*, u.username, u.userid as foto_userid, a.nama_album, a.albumid 
            FROM foto f 
            JOIN user u ON f.userid = u.userid 
            JOIN album a ON f.albumid = a.albumid 
            WHERE f.fotoid = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fotoid]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fungsi untuk validasi file upload
function validateUploadedFile($file)
{
    $errors = [];

    // Cek error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => 'File terlalu besar',
            UPLOAD_ERR_FORM_SIZE => 'File terlalu besar',
            UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
            UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diupload',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ada',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file',
            UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh extension'
        ];
        $errors[] = $upload_errors[$file['error']] ?? 'Terjadi kesalahan saat upload file';
        return $errors;
    }

    // Cek tipe file
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        $errors[] = 'Format file tidak didukung. Gunakan JPG, PNG, atau GIF.';
    }

    // Cek ukuran file (max 5MB)
    $max_size = 5 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        $errors[] = 'Ukuran file terlalu besar. Maksimal 5MB.';
    }

    // Cek ekstensi file
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($file_extension, $allowed_extensions)) {
        $errors[] = 'Ekstensi file tidak diizinkan. Gunakan JPG, PNG, atau GIF.';
    }

    return $errors;
}

// Fungsi untuk mendapatkan notifikasi
function getUserNotifications($userid)
{
    $pdo = getDBConnection();

    $sql = "SELECT 'like' as type, l.tanggal_like as tanggal, u.username, f.judul_foto, f.fotoid
            FROM likes l
            JOIN user u ON l.userid = u.userid
            JOIN foto f ON l.fotoid = f.fotoid
            WHERE f.userid = ? AND l.userid != ?
            UNION
            SELECT 'comment' as type, k.tanggal_komen as tanggal, u.username, f.judul_foto, f.fotoid
            FROM komentar k
            JOIN user u ON k.userid = u.userid
            JOIN foto f ON k.fotoid = f.fotoid
            WHERE f.userid = ? AND k.userid != ?
            ORDER BY tanggal DESC
            LIMIT 10";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userid, $userid, $userid, $userid]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fungsi untuk mendapatkan total foto
function getTotalPhotosCount() {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM foto");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'];
}

// Fungsi untuk mendapatkan semua foto dengan pagination
function getAllFotosForGallery($limit = 24, $offset = 0) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT f.*, u.username, u.nama_lengkap, a.nama_album,
            (SELECT COUNT(*) FROM likes l WHERE l.fotoid = f.fotoid) as jumlah_like,
            (SELECT COUNT(*) FROM komentar k WHERE k.fotoid = f.fotoid) as jumlah_komentar
        FROM foto f 
        JOIN user u ON f.userid = u.userid 
        LEFT JOIN album a ON f.albumid = a.albumid 
        ORDER BY f.tanggal_unggah DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fungsi untuk mendapatkan semua user yang punya foto
function getAllUsersWithPhotos() {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.userid, u.username, u.nama_lengkap
        FROM user u 
        JOIN foto f ON u.userid = f.userid 
        ORDER BY u.username
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fungsi untuk mendapatkan navbar
function getNavbar()
{
    ob_start();
    $current_page = basename($_SERVER['PHP_SELF']);
?>
    <header>
        <div class="container">
            <div class="navbar">
                <a href="gf.php" class="logo">
                    <i class="fas fa-camera"></i> GF
                </a>

                <form class="search-form" method="GET" action="search.php">
                    <input type="text" name="keyword" placeholder="Cari album atau foto..."
                        value="<?php echo isset($_GET['keyword']) ? htmlspecialchars($_GET['keyword']) : ''; ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>

                <div class="nav-links">
                    <?php if (isset($_SESSION['userid'])): ?>
                        <?php
                        $userData = getUserData($_SESSION['userid']);
                        $profilePhoto = !empty($userData['foto_profile']) ? $userData['foto_profile'] : null;
                        ?>

                        <a href="gf.php" class="<?php echo $current_page == 'gf.php' ? 'active' : ''; ?>">
                            <i class="fas fa-home"></i> Beranda
                        </a>
                        <a href="gallery.php" class="<?php echo $current_page == 'gallery.php' ? 'active' : ''; ?>">
                            <i class="fas fa-images"></i> Galeri Foto
                        </a>
                        <a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                        <a href="#" onclick="openModal('createAlbumModal')">
                            <i class="fas fa-folder-plus"></i> Buat Album
                        </a>
                        <a href="#" onclick="openModal('uploadFotoModal')">
                            <i class="fas fa-upload"></i> Upload Foto
                        </a>

                        <!-- Profile Dropdown -->
                        <div class="profile-dropdown">
                            <a href="#" class="profile-toggle">
                                <?php if ($profilePhoto): ?>
                                    <img src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Profile" class="profile-img">
                                <?php else: ?>
                                    <div class="profile-placeholder">
                                        <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                                <i class="fas fa-chevron-down"></i>
                            </a>

                            <div class="dropdown-menu">
                                <a href="profile.php">
                                    <i class="fas fa-user"></i> Profile Saya
                                </a>
                                <a href="my_albums.php">
                                    <i class="fas fa-folder"></i> Album Saya
                                </a>
                                <a href="my_photos.php">
                                    <i class="fas fa-images"></i> Foto Saya
                                </a>
                                <div class="dropdown-divider"></div>
                                <a href="gf.php?logout=1" class="logout-btn">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </div>
                        </div>

                    <?php else: ?>
                        <a href="gf.php" class="<?php echo $current_page == 'gf.php' ? 'active' : ''; ?>">
                            <i class="fas fa-home"></i> Beranda
                        </a>
                        <a href="gallery.php" class="<?php echo $current_page == 'gallery.php' ? 'active' : ''; ?>">
                            <i class="fas fa-images"></i> Galeri Foto
                        </a>
                        <a href="#" onclick="openModal('loginModal')">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                        <a href="#" onclick="openModal('registerModal')">
                            <i class="fas fa-user-plus"></i> Daftar
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <script>
        // Dropdown functionality
        document.addEventListener('DOMContentLoaded', function() {
            const profileDropdown = document.querySelector('.profile-dropdown');
            if (profileDropdown) {
                const dropdownBtn = profileDropdown.querySelector('.profile-toggle');
                const dropdownMenu = profileDropdown.querySelector('.dropdown-menu');

                dropdownBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    dropdownMenu.classList.toggle('show');
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!profileDropdown.contains(e.target)) {
                        dropdownMenu.classList.remove('show');
                    }
                });
            }

            // Close dropdown when pressing escape
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const dropdowns = document.querySelectorAll('.dropdown-menu');
                    dropdowns.forEach(dropdown => {
                        dropdown.classList.remove('show');
                    });
                }
            });
        });
    </script>
<?php
    return ob_get_clean();
}

// Fungsi untuk memeriksa apakah user sudah login
function isUserLoggedIn()
{
    startSecureSession();
    return isset($_SESSION['userid']);
}

// Fungsi untuk memeriksa role user
function getUserRole()
{
    return isset($_SESSION['role']) ? $_SESSION['role'] : 'guest';
}

// Fungsi untuk redirect jika tidak login
function redirectIfNotLoggedIn($redirect_url = 'gf.php')
{
    if (!isUserLoggedIn()) {
        header("Location: $redirect_url");
        exit();
    }
}

// Fungsi untuk redirect jika bukan admin
function redirectIfNotAdmin($redirect_url = 'gf.php')
{
    if (getUserRole() !== 'admin') {
        header("Location: $redirect_url");
        exit();
    }
}

// Fungsi untuk sanitize input
function sanitizeInput($data)
{
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Fungsi untuk generate random string
function generateRandomString($length = 10)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

// ========== FUNGSI DEBUGGING TAMBAHAN ==========

// Fungsi untuk test query langsung (debugging)
function testDirectSearch($keyword) {
    $pdo = getDBConnection();
    $searchTerm = "%" . $keyword . "%";
    
    echo "<div style='background: #e8f4f8; padding: 10px; border: 1px solid #b3d4fc; margin: 10px 0;'>";
    echo "<strong>🔧 TEST DIRECT QUERY - Keyword: '$keyword'</strong><br>";
    
    // Test 1: Query album langsung
    $stmt = $pdo->prepare("SELECT a.*, u.username FROM album a JOIN user u ON a.userid = u.userid WHERE a.nama_album LIKE :keyword");
    $stmt->execute([':keyword' => $searchTerm]);
    $albums = $stmt->fetchAll();
    
    echo "📁 Album ditemukan: " . count($albums) . "<br>";
    foreach ($albums as $album) {
        echo "- '" . $album['nama_album'] . "' oleh " . $album['username'] . " (ID: " . $album['albumid'] . ")<br>";
    }
    
    // Test 2: Query foto langsung
    $stmt2 = $pdo->prepare("SELECT f.*, u.username FROM foto f JOIN user u ON f.userid = u.userid WHERE f.judul_foto LIKE :keyword");
    $stmt2->execute([':keyword' => $searchTerm]);
    $fotos = $stmt2->fetchAll();
    
    echo "📸 Foto ditemukan: " . count($fotos) . "<br>";
    foreach ($fotos as $foto) {
        echo "- '" . $foto['judul_foto'] . "' oleh " . $foto['username'] . " (ID: " . $foto['fotoid'] . ")<br>";
    }
    
    echo "</div>";
}

// Fungsi untuk menampilkan semua album di database (debugging)
function debugShowAllAlbums() {
    $pdo = getDBConnection();
    
    echo "<div style='background: #fff3cd; padding: 10px; border: 1px solid #ffeaa7; margin: 10px 0;'>";
    echo "<strong>📋 SEMUA ALBUM DI DATABASE</strong><br>";
    
    $stmt = $pdo->query("SELECT a.albumid, a.nama_album, a.deskripsi, a.tanggal, u.username FROM album a JOIN user u ON a.userid = u.userid ORDER BY a.tanggal DESC");
    $albums = $stmt->fetchAll();
    
    echo "Total: " . count($albums) . " album<br><br>";
    
    foreach ($albums as $album) {
        echo "ID: " . $album['albumid'] . "<br>";
        echo "Nama: <strong>" . $album['nama_album'] . "</strong><br>";
        echo "Deskripsi: " . $album['deskripsi'] . "<br>";
        echo "User: " . $album['username'] . "<br>";
        echo "Tanggal: " . $album['tanggal'] . "<br>";
        echo "<hr style='border-top: 1px dashed #ccc;'>";
    }
    
    echo "</div>";
}

// Fungsi untuk cek struktur tabel (debugging)
function debugTableStructure() {
    $pdo = getDBConnection();
    
    echo "<div style='background: #d1ecf1; padding: 10px; border: 1px solid #bee5eb; margin: 10px 0;'>";
    echo "<strong>📊 STRUKTUR TABEL DATABASE</strong><br>";
    
    $tables = ['album', 'user', 'foto', 'likes', 'komentar'];
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll();
            
            echo "<strong>Tabel: $table</strong><br>";
            echo "<table border='1' cellpadding='5' style='font-size: 12px; margin: 5px 0 10px 0;'>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            foreach ($columns as $col) {
                echo "<tr>";
                echo "<td>" . $col['Field'] . "</td>";
                echo "<td>" . $col['Type'] . "</td>";
                echo "<td>" . $col['Null'] . "</td>";
                echo "<td>" . $col['Key'] . "</td>";
                echo "<td>" . $col['Default'] . "</td>";
                echo "<td>" . $col['Extra'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } catch (Exception $e) {
            echo "Error checking table $table: " . $e->getMessage() . "<br>";
        }
    }
    
    echo "</div>";
}
?>