<?php
// 1. MEMULAI SESSION & KONEKSI
session_start();
require_once __DIR__ . '/../public/pages/Connection.php';

// 2. KEAMANAN: Cek jika user sudah login
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'pembeli') {
    header("Location: ../public/pages/Login.php");
    exit;
}
$pembeli_id = (int) $_SESSION['user_id'];

// 3. VALIDASI POST REQUEST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: RiwayatTransaksi.php");
    exit;
}

// 4. AMBIL DATA DARI FORM
$pesanan_id = (int) ($_POST['pesanan_id'] ?? 0);

if ($pesanan_id === 0) {
    $_SESSION['error_message'] = "Permintaan tidak valid. ID Pesanan tidak ditemukan.";
    header("Location: RiwayatTransaksi.php");
    exit;
}

// 5. PROSES KE DATABASE (TRANSACTION)
$conn->begin_transaction();

try {
    // --- Langkah A: Update status pesanan ---
    $sql_update = "UPDATE pesanan SET status_pesanan = 'Selesai' 
                   WHERE id = ? AND pembeli_id = ? AND status_pesanan = 'Dikirim'";
                   
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param('ii', $pesanan_id, $pembeli_id);
    
    if (!$stmt_update->execute()) {
        throw new Exception("Gagal memperbarui status pesanan: " . $stmt_update->error);
    }
    
    // Cek apakah ada baris yang ter-update
    if ($stmt_update->affected_rows === 0) {
        throw new Exception("Pesanan tidak ditemukan atau statusnya bukan 'Dikirim'.");
    }
    $stmt_update->close();

    // --- Langkah B: Catat Pemasukan untuk Petani ---
    // (Ini adalah logika keuangan yang kita bahas di laporan petani)

    // B.1: Ambil semua item di pesanan ini, DAN petani_id-nya
    $sql_get_items = "SELECT dp.subtotal, pr.petani_id, pr.nama_produk
                      FROM detail_pesanan dp
                      JOIN produk pr ON dp.produk_id = pr.id
                      WHERE dp.pesanan_id = ?";
                      
    $stmt_items = $conn->prepare($sql_get_items);
    $stmt_items->bind_param('i', $pesanan_id);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    $items = $result_items->fetch_all(MYSQLI_ASSOC);
    $stmt_items->close();

    if (empty($items)) {
        throw new Exception("Detail pesanan tidak ditemukan.");
    }
    
    // B.2: Siapkan query untuk INSERT ke 'transaksi_keuangan'
    $sql_insert_transaksi = "INSERT INTO transaksi_keuangan 
                                (petani_id, tipe_transaksi, jumlah, deskripsi, referensi_id, tanggal_transaksi) 
                             VALUES (?, 'Pemasukan', ?, ?, ?, NOW())";
    $stmt_transaksi = $conn->prepare($sql_insert_transaksi);
    
    foreach ($items as $item) {
        $petani_id_item = $item['petani_id'];
        $jumlah_pemasukan = $item['subtotal'];
        // Buat deskripsi yang jelas
        $deskripsi = "Pemasukan dari penjualan " . $item['nama_produk'] . " (Pesanan ID: #$pesanan_id)";
        
        // (i)petani_id, (d)jumlah, (s)deskripsi, (i)referensi_id
        $stmt_transaksi->bind_param('idsi', $petani_id_item, $jumlah_pemasukan, $deskripsi, $pesanan_id);
        
        if (!$stmt_transaksi->execute()) {
            throw new Exception("Gagal mencatat transaksi keuangan untuk petani: " . $stmt_transaksi->error);
        }
    }
    $stmt_transaksi->close();
    
    // Jika semua berhasil
    $conn->commit();
    $_SESSION['success_message'] = "Pesanan #$pesanan_id telah diselesaikan! Terima kasih.";
    header("Location: RiwayatTransaksi.php");
    exit;

} catch (Exception $e) {
    // Jika ada yang gagal, batalkan semua
    $conn->rollback();
    
    $_SESSION['error_message'] = "Terjadi kesalahan: " . $e->getMessage();
    header("Location: RiwayatTransaksi.php");
    exit;
}
?>