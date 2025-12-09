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
    header("Location: PesananMasuk.php");
    exit;
}

// 4. AMBIL DATA DARI FORM
$pesanan_id = (int) ($_POST['pesanan_id'] ?? 0);
$aksi = trim($_POST['aksi'] ?? ''); // 'terima' atau 'tolak'

if ($pesanan_id === 0 || empty($aksi)) {
    $_SESSION['error_message'] = "Permintaan tidak valid.";
    header("Location: PesananMasuk.php");
    exit;
}

// 5. PROSES KE DATABASE (TRANSACTION)
$conn->begin_transaction();

try {
    $status_pesanan_baru = '';
    $status_pembayaran_baru = '';

    if ($aksi === 'terima') {
        $status_pesanan_baru = 'Dikemas';
        $status_pembayaran_baru = 'valid';
        $_SESSION['success_message'] = "Pesanan #$pesanan_id disetujui dan siap dikemas.";
    
    } elseif ($aksi === 'tolak') {
        $status_pesanan_baru = 'Dibatalkan'; 
        $status_pembayaran_baru = 'ditolak';
        $_SESSION['error_message'] = "Pembayaran untuk Pesanan #$pesanan_id ditolak.";
        
        // TODO: Kembalikan stok produk jika ditolak
    
    } else {
        throw new Exception("Aksi tidak dikenal.");
    }

    // Query 1: Update status di tabel 'pesanan'
    // PERBAIKAN: Hapus "AND status_pesanan = 'Menunggu Verifikasi'"
    // karena status itu tidak ada di database Anda.
    $sql_update_pesanan = "UPDATE pesanan SET status_pesanan = ? 
                           WHERE id = ? AND petani_id = ?";
    $stmt_update = $conn->prepare($sql_update_pesanan);
    $stmt_update->bind_param('sii', $status_pesanan_baru, $pesanan_id, $petani_id);
    
    if (!$stmt_update->execute()) {
        throw new Exception("Gagal memperbarui status pesanan: " . $stmt_update->error);
    }
    $rows_affected = $stmt_update->affected_rows;
    $stmt_update->close();
    
    if ($rows_affected == 0) {
         throw new Exception("Gagal memperbarui status pesanan. Pesanan mungkin tidak ditemukan atau bukan milik Anda.");
    }

    // Query 2: Update status di tabel 'pembayaran'
    // PERBAIKAN: Kita harus mengecek apakah ada data di tabel pembayaran
    // Jika tidak ada, kita INSERT, jangan UPDATE
    
    // Cek dulu
    $stmt_cek = $conn->prepare("SELECT id FROM pembayaran WHERE pesanan_id = ?");
    $stmt_cek->bind_param('i', $pesanan_id);
    $stmt_cek->execute();
    $result_cek = $stmt_cek->get_result();
    $stmt_cek->close();
    
    if ($result_cek->num_rows > 0) {
        // Data sudah ada, UPDATE
        $sql_update_pembayaran = "UPDATE pembayaran SET status_verifikasi = ? WHERE pesanan_id = ?";
        $stmt_pembayaran = $conn->prepare($sql_update_pembayaran);
        $stmt_pembayaran->bind_param('si', $status_pembayaran_baru, $pesanan_id);
    } else {
        // Data belum ada, INSERT (ini mungkin terjadi jika Anda skip langkah)
        // Kita isi data dummy karena kita tidak punya info lengkap
        $sql_update_pembayaran = "INSERT INTO pembayaran (pesanan_id, metode, jumlah_transfer, bukti_transfer, tgl_transfer, status_verifikasi)
                                  VALUES (?, 'manual', 0, 'verified_by_petani', NOW(), ?)";
        $stmt_pembayaran = $conn->prepare($sql_update_pembayaran);
        $stmt_pembayaran->bind_param('is', $pesanan_id, $status_pembayaran_baru);
    }
    
    if (!$stmt_pembayaran->execute()) {
        throw new Exception("Gagal memperbarui status pembayaran: " . $stmt_pembayaran->error);
    }
    $stmt_pembayaran->close();
    

    // Jika semua berhasil
    $conn->commit();
    header("Location: PesananMasuk.php");
    exit;

} catch (Exception $e) {
    // Jika ada yang gagal, batalkan semua
    $conn->rollback();
    
    $_SESSION['error_message'] = "Terjadi kesalahan database: " . $e->getMessage();
    header("Location: PesananMasuk.php");
    exit;
}
?>