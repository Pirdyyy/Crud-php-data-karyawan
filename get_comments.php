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

// Get comments with user data
$sql = "SELECT k.*, u.username, u.foto_profile,
               (u.userid = ? OR ? IN ('admin', 'karyawan')) as can_delete
        FROM komentar k 
        JOIN user u ON k.userid = u.userid 
        WHERE k.fotoid = ? 
        ORDER BY k.tanggal_komen ASC";
$stmt = $pdo->prepare($sql);
$userid = $_SESSION['userid'] ?? 0;
$role = $_SESSION['role'] ?? '';
$stmt->execute([$userid, $role, $fotoid]);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format comments for JSON response
$formattedComments = [];
foreach ($comments as $comment) {
    $formattedComments[] = [
        'komentarid' => $comment['komentarid'],
        'userid' => $comment['userid'],
        'username' => $comment['username'],
        'foto_profile' => $comment['foto_profile'],
        'isi_komen' => $comment['isi_komen'],
        'tanggal_komen' => time_elapsed_string($comment['tanggal_komen']),
        'can_delete' => (bool)$comment['can_delete']
    ];
}

echo json_encode($formattedComments);

// Helper function untuk format waktu
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
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