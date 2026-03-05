<?php
session_start();
require_once 'functions.php';

if (!isset($_GET['fotoid'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Foto ID required']);
    exit;
}

$fotoid = $_GET['fotoid'];
$pdo = getDBConnection();

// Get foto data with user info
$sql = "SELECT f.*, u.username, u.foto_profile,
               (SELECT COUNT(*) FROM likes l WHERE l.fotoid = f.fotoid) as jumlah_like,
               (SELECT COUNT(*) FROM komentar k WHERE k.fotoid = f.fotoid) as jumlah_komentar
        FROM foto f 
        LEFT JOIN user u ON f.userid = u.userid 
        WHERE f.fotoid = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$fotoid]);
$foto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$foto) {
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['error' => 'Foto not found']);
    exit;
}

// Check if user liked this foto
$is_liked = false;
if (isset($_SESSION['userid'])) {
    $is_liked = isLiked($fotoid, $_SESSION['userid']);
}

// Format time
$time_ago = time_elapsed_string($foto['tanggal_unggah']);

echo json_encode([
    'fotoid' => $foto['fotoid'],
    'judul_foto' => $foto['judul_foto'],
    'deskripsi' => $foto['deskripsi'],
    'lokasifile' => $foto['lokasifile'],
    'username' => $foto['username'],
    'foto_profile' => $foto['foto_profile'],
    'jumlah_like' => $foto['jumlah_like'],
    'jumlah_komentar' => $foto['jumlah_komentar'],
    'is_liked' => $is_liked,
    'tanggal_unggah' => $time_ago
]);

// Helper function untuk format waktu
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>