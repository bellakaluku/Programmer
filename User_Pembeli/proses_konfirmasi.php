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
    header("Location: RiwayatTransaksi.php"); // Arahkan pergi jika bukan POST
    exit;
}

// 4. AMBIL DATA DARI FORM
$pesanan_id = (int) ($_POST['pesanan_id'] ?? 0);
$metode = trim($_POST['metode'] ?? '');
$jumlah_transfer = (int) ($_POST['jumlah_transfer'] ?? 0); // Sesuai DB Anda (INT)
$tgl_transfer = trim($_POST['tgl_transfer'] ?? '');
$file_bukti = $_FILES['bukti_transfer'] ?? null;

// Halaman kembali jika error
$redirect_error = "KonfirmasiPembayaran.php?id=$pesanan_id";

// 5. VALIDASI DATA
if ($pesanan_id === 0 || empty($metode) || $jumlah_transfer <= 0 || empty($tgl_transfer) || $file_bukti === null) {
    $_SESSION['error_message'] = "Semua field wajib diisi.";
    header("Location: $redirect_error");
    exit;
}

// 6. PROSES UPLOAD GAMBAR
$db_path = '';
$target_file_path = ''; // Definisikan di luar
if ($file_bukti['error'] === UPLOAD_ERR_OK) {
    // Tentukan folder upload (di luar folder User_Pembeli, di 'public/')
    $upload_dir = __DIR__ . '/../public/bukti_pembayaran/';
    
    // Buat folder jika belum ada
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file_ext = strtolower(pathinfo($file_bukti['name'], PATHINFO_EXTENSION));
    $allowed_types = ['jpg', 'jpeg', 'png'];

    if (in_array($file_ext, $allowed_types)) {
        // Buat nama file unik
        $unique_name = uniqid('bukti_', true) . '.' . $file_ext;
        $target_file_path = $upload_dir . $unique_name;

        if (move_uploaded_file($file_bukti['tmp_name'], $target_file_path)) {
            // Simpan path yang bisa diakses dari web
            // (Contoh: 'bukti_pembayaran/bukti_xxxxx.jpg')
            $db_path = 'bukti_pembayaran/' . $unique_name;
        } else {
            $_SESSION['error_message'] = "Gagal memindahkan file yang di-upload.";
            header("Location: $redirect_error");
            exit;
        }
    } else {
        $_SESSION['error_message'] = "Hanya file JPG, JPEG, dan PNG yang diizinkan.";
        header("Location: $redirect_error");
        exit;
    }
} else {
    $_SESSION['error_message'] = "Terjadi error saat meng-upload file: " . $file_bukti['error'];
    header("Location: $redirect_error");
    exit;
}

// 7. PROSES KE DATABASE (TRANSACTION)
if (empty($db_path)) {
    $_SESSION['error_message'] = "Path file bukti tidak valid.";
    header("Location: $redirect_error");
    exit;
}

// Mulai Transaksi
$conn->begin_transaction();

try {
    // Query 1: Masukkan ke tabel 'pembayaran'
    // Tipe data: (i)pesanan_id, (s)metode, (i)jumlah_transfer, (s)db_path, (s)tgl_transfer
    $sql_insert = "INSERT INTO pembayaran (pesanan_id, metode, jumlah_transfer, bukti_transfer, tgl_transfer, status_verifikasi) 
                   VALUES (?, ?, ?, ?, ?, 'menunggu')";
    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bind_param('isiss', $pesanan_id, $metode, $jumlah_transfer, $db_path, $tgl_transfer);
    
    if (!$stmt_insert->execute()) {
        throw new Exception("Gagal menyimpan data pembayaran: " . $stmt_insert->error);
    }
    $stmt_insert->close();

    // Query 2: Update status di tabel 'pesanan'
    $sql_update = "UPDATE pesanan SET status_pesanan = 'Menunggu Verifikasi' 
                   WHERE id = ? AND pembeli_id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param('ii', $pesanan_id, $pembeli_id);
    
    if (!$stmt_update->execute()) {
        throw new Exception("Gagal memperbarui status pesanan: " . $stmt_update->error);
    }
    $stmt_update->close();

    // Jika semua berhasil
    $conn->commit();
    $_SESSION['success_message'] = "Konfirmasi pembayaran berhasil dikirim. Pesanan Anda akan segera diverifikasi.";
    header("Location: RiwayatTransaksi.php");
    exit;

} catch (Exception $e) {
    // Jika ada yang gagal, batalkan semua
    $conn->rollback();
    
    // Hapus file yang sudah terlanjur di-upload
    if (!empty($target_file_path) && file_exists($target_file_path)) {
        unlink($target_file_path);
    }
    
    $_SESSION['error_message'] = "Terjadi kesalahan database: " . $e->getMessage();
    header("Location: $redirect_error");
    exit;
}
?>