<?php
// 1. MEMULAI SESSION & KONEKSI
session_start();
require_once __DIR__ . '/../public/pages/Connection.php';

// 2. KEAMANAN: Cek jika user sudah login
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'pembeli') {
    header("Location: ../public/pages/Login.php");
    exit;
}

// 3. VALIDASI POST REQUEST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: RiwayatTransaksi.php"); // Arahkan pergi jika bukan POST
    exit;
}

// 4. AMBIL DATA DARI FORM
$pembeli_id_session = (int) $_SESSION['user_id'];
$pembeli_id_form = (int) ($_POST['pembeli_id'] ?? 0);
$produk_id = (int) ($_POST['produk_id'] ?? 0);
$detail_id = (int) ($_POST['detail_id'] ?? 0);
$rating = (int) ($_POST['rating'] ?? 0);
$komentar = trim($_POST['komentar'] ?? '');

// 5. VALIDASI DATA
// Pastikan data penting ada
if ($detail_id === 0 || $produk_id === 0 || $pembeli_id_form === 0 || $rating === 0) {
    die("Error: Data tidak lengkap.");
}

// Validasi keamanan terpenting: Pastikan user yang submit adalah user yang login
if ($pembeli_id_session !== $pembeli_id_form) {
    die("Error: Aksi tidak diizinkan.");
}

// 6. PROSES KE DATABASE
if (isset($conn) && $conn instanceof mysqli) {
    
    // Cek sekali lagi agar tidak double (meskipun di form sudah dicek)
    $stmt_cek = $conn->prepare("SELECT id FROM ulasan WHERE detail_pesanan_id = ?");
    $stmt_cek->bind_param('i', $detail_id);
    $stmt_cek->execute();
    $result_cek = $stmt_cek->get_result();
    
    if ($result_cek->num_rows > 0) {
        $stmt_cek->close();
        echo "<script>alert('Anda sudah pernah memberi ulasan untuk produk ini.'); window.location='RiwayatTransaksi.php';</script>";
        exit;
    }
    $stmt_cek->close();

    // Jika semua aman, masukkan data ulasan baru
    $sql_insert = "INSERT INTO ulasan (produk_id, pembeli_id, detail_pesanan_id, rating, komentar) 
                   VALUES (?, ?, ?, ?, ?)";
                   
    $stmt_insert = $conn->prepare($sql_insert);
    // 'iiiss' = integer, integer, integer, integer, string
    $stmt_insert->bind_param('iiiis', $produk_id, $pembeli_id_session, $detail_id, $rating, $komentar);
    
    if ($stmt_insert->execute()) {
        // Berhasil!
        $stmt_insert->close();
        $conn->close();
        
        // Kirim notifikasi sukses (opsional)
        $_SESSION['success_message'] = "Ulasan Anda berhasil dikirim! Terima kasih.";
        header("Location: RiwayatTransaksi.php");
        exit;
    } else {
        // Gagal
        die("Error saat menyimpan ulasan: " . $stmt_insert->error);
    }

} else {
    die("Koneksi database gagal.");
}
?>