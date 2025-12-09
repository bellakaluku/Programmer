<?php
// 1. MEMULAI SESSION & KONEKSI
session_start();
require_once __DIR__ . '/../public/pages/Connection.php';

// 2. KEAMANAN: Cek jika user sudah login dan adalah 'petani'
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'petani') {
    header("Location: ../public/pages/Login.php");
    exit;
}
$petani_id = (int) $_SESSION['user_id'];

// 3. VALIDASI POST REQUEST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: PesananMasuk.php"); // Arahkan pergi jika bukan POST
    exit;
}

// 4. AMBIL DATA DARI FORM
$pesanan_id = (int) ($_POST['pesanan_id'] ?? 0);

if ($pesanan_id === 0) {
    $_SESSION['error_message'] = "Permintaan tidak valid. ID Pesanan tidak ditemukan.";
    header("Location: PesananMasuk.php");
    exit;
}

// (OPSIONAL: Di sinilah Anda akan mengambil $_POST['nomor_resi'] jika Anda menambahkannya)
// $nomor_resi = trim($_POST['nomor_resi'] ?? '');

// 5. PROSES KE DATABASE
if (isset($conn) && $conn instanceof mysqli) {
    
    // Query untuk update status
    // Kita pastikan hanya petani_id yang benar yang bisa mengubah
    // Dan kita pastikan hanya pesanan 'Dikemas' yang bisa diubah menjadi 'Dikirim'
    $sql_update = "UPDATE pesanan 
                   SET status_pesanan = 'Dikirim' 
                   /* (OPSIONAL: , nomor_resi = ? ) */
                   WHERE id = ? 
                   AND petani_id = ? 
                   AND status_pesanan = 'Dikemas'";
                   
    $stmt = $conn->prepare($sql_update);
    
    // PERBAIKAN: Tipe data harus 'ii' (2 integer) agar cocok dengan 2 variabel ($pesanan_id, $petani_id)
    $stmt->bind_param('ii', $pesanan_id, $petani_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Berhasil
            $_SESSION['success_message'] = "Pesanan #$pesanan_id telah ditandai sebagai 'Dikirim'.";
        } else {
            // Gagal (mungkin pesanan tidak ditemukan atau statusnya sudah 'Dikirim')
            $_SESSION['error_message'] = "Gagal memperbarui status. Pesanan mungkin tidak ditemukan atau statusnya bukan 'Dikemas'.";
        }
    } else {
        // Gagal query
        $_SESSION['error_message'] = "Terjadi kesalahan database: " . $stmt->error;
    }
    $stmt->close();
    $conn->close();

} else {
    $_SESSION['error_message'] = "Koneksi database gagal.";
}

// 6. KEMBALIKAN KE HALAMAN PESANAN MASUK
header("Location: PesananMasuk.php");
exit;
?>