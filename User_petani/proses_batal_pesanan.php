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

if ($pesanan_id === 0) {
    $_SESSION['error_message'] = "Permintaan tidak valid. ID Pesanan tidak ditemukan.";
    header("Location: PesananMasuk.php");
    exit;
}

// 5. PROSES KE DATABASE (TRANSACTION)
$conn->begin_transaction();

try {
    
    // --- Langkah A: Dapatkan semua item di pesanan ini ---
    $sql_get_items = "SELECT dp.produk_id, dp.jumlah_kg, p.status_pesanan
                      FROM detail_pesanan dp
                      JOIN produk pr ON dp.produk_id = pr.id
                      JOIN pesanan p ON dp.pesanan_id = p.id
                      WHERE dp.pesanan_id = ? AND pr.petani_id = ?";
                      
    $stmt_get = $conn->prepare($sql_get_items);
    $stmt_get->bind_param('ii', $pesanan_id, $petani_id);
    $stmt_get->execute();
    $result_items = $stmt_get->get_result();
    $items = $result_items->fetch_all(MYSQLI_ASSOC);
    $stmt_get->close();

    if (empty($items)) {
        throw new Exception("Pesanan tidak ditemukan atau bukan milik Anda.");
    }
    
    // Cek status pesanan (hanya 'Dikemas' atau 'Menunggu Verifikasi' yang boleh dibatalkan)
    $status_sekarang = $items[0]['status_pesanan'];
    if (!in_array($status_sekarang, ['Dikemas', 'Menunggu Verifikasi'])) {
         throw new Exception("Pesanan ini tidak dapat dibatalkan (status: $status_sekarang).");
    }

    // --- Langkah B: Kembalikan Stok (Restock) ---
    $sql_restore_stok = "UPDATE produk SET stok_kg = stok_kg + ? WHERE id = ?";
    $stmt_restore = $conn->prepare($sql_restore_stok);
    
    foreach ($items as $item) {
        $stmt_restore->bind_param('ii', $item['jumlah_kg'], $item['produk_id']);
        $stmt_restore->execute();
    }
    $stmt_restore->close();
    
    // --- Langkah C: Ubah Status Pesanan menjadi 'Dibatalkan' ---
    $sql_cancel = "UPDATE pesanan SET status_pesanan = 'Dibatalkan' 
                   WHERE id = ? AND petani_id = ?";
    $stmt_cancel = $conn->prepare($sql_cancel);
    $stmt_cancel->bind_param('ii', $pesanan_id, $petani_id);
    $stmt_cancel->execute();
    $stmt_cancel->close();
    
    // --- Langkah D (Opsional): Ubah status pembayaran jika ada ---
    if ($status_sekarang == 'Menunggu Verifikasi') {
        $sql_pay = "UPDATE pembayaran SET status_verifikasi = 'ditolak' WHERE pesanan_id = ?";
        $stmt_pay = $conn->prepare($sql_pay);
        $stmt_pay->bind_param('i', $pesanan_id);
        $stmt_pay->execute();
        $stmt_pay->close();
    }

    // Jika semua berhasil
    $conn->commit();
    $_SESSION['success_message'] = "Pesanan #$pesanan_id berhasil dibatalkan. Stok telah dikembalikan.";
    header("Location: PesananMasuk.php");
    exit;

} catch (Exception $e) {
    // Jika ada yang gagal, batalkan semua
    $conn->rollback();
    
    $_SESSION['error_message'] = "Gagal membatalkan pesanan: " . $e->getMessage();
    header("Location: PesananMasuk.php");
    exit;
}
?>