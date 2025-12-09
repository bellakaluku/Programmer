<?php
// 1. MEMULAI SESSION & KONEKSI
session_start();
// Path ini: ../ (keluar dari User_petani) -> public/ -> pages/ -> Connection.php
include __DIR__ . '/../public/pages/Connection.php';

// 2. KEAMANAN: Cek jika user sudah login dan adalah 'petani'
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'petani') {
    $petani_id = null;
} else {
    $petani_id = (int) $_SESSION['user_id'];
}

// 3. PROSES AJAX (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    if (!$petani_id) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak. Silakan login kembali.']);
        exit;
    }

    // Menggunakan transaksi database untuk memastikan data konsisten
    mysqli_begin_transaction($conn);

    try {
        if ($action === 'create') {
            $nama = trim($_POST['nama_lahan'] ?? '');
            $jenis = trim($_POST['jenis_tanaman'] ?? '');
            $mulai = $_POST['tgl_mulai'] ?? null;
            $panen = $_POST['tgl_panen'] ?: null;
            
            $biaya_raw = $_POST['biaya_modal'] ?? '0';
            $biaya_cleaned = preg_replace("/[^0-9]/", "", $biaya_raw);
            $biaya = (double) $biaya_cleaned;

            // Langkah 1: Simpan ke tabel 'lahan'
            $stmt = mysqli_prepare($conn, "INSERT INTO lahan (petani_id, nama_lahan, jenis_tanaman, mulai_tanam, tanggal_panen, biaya_modal) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'issssd', $petani_id, $nama, $jenis, $mulai, $panen, $biaya);
            $ok = mysqli_stmt_execute($stmt);
            $newId = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            if (!$ok) {
                throw new Exception("Gagal menyimpan data lahan/penanaman.");
            }

            // Langkah 2: OTOMATIS Simpan ke 'transaksi_keuangan'
            $deskripsi_transaksi = "Biaya modal untuk tanam $jenis di $nama (ID Lahan: $newId)";
            $tanggal_transaksi = $mulai;
            
            $stmt_keuangan = mysqli_prepare($conn, "INSERT INTO transaksi_keuangan (petani_id, tipe_transaksi, jumlah, deskripsi, referensi_id, tanggal_transaksi) VALUES (?, 'Pengeluaran', ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt_keuangan, 'idsis', $petani_id, $biaya, $deskripsi_transaksi, $newId, $tanggal_transaksi);
            $ok_keuangan = mysqli_stmt_execute($stmt_keuangan);
            mysqli_stmt_close($stmt_keuangan);

            if (!$ok_keuangan) {
                throw new Exception("Gagal mencatat transaksi keuangan.");
            }

            // Jika semua berhasil, commit transaksi
            mysqli_commit($conn);
            
            $res = mysqli_query($conn, "SELECT * FROM lahan WHERE id = " . (int)$newId);
            $row = mysqli_fetch_assoc($res);
            echo json_encode(['success' => true, 'row' => $row]);
            exit;
        }

        if ($action === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $nama = trim($_POST['nama_lahan'] ?? '');
            $jenis = trim($_POST['jenis_tanaman'] ?? '');
            $mulai = $_POST['tgl_mulai'] ?? null;
            $panen = $_POST['tgl_panen'] ?: null;

            $biaya_raw = $_POST['biaya_modal'] ?? '0';
            $biaya_cleaned = preg_replace("/[^0-9]/", "", $biaya_raw);
            $biaya = (double) $biaya_cleaned;

            // Langkah 1: Update tabel 'lahan'
            $stmt = mysqli_prepare($conn, "UPDATE lahan SET nama_lahan = ?, jenis_tanaman = ?, mulai_tanam = ?, tanggal_panen = ?, biaya_modal = ? WHERE id = ? AND petani_id = ?");
            mysqli_stmt_bind_param($stmt, 'ssssdii', $nama, $jenis, $mulai, $panen, $biaya, $id, $petani_id);
            $ok = mysqli_stmt_execute($stmt);
            $pesan = (mysqli_stmt_affected_rows($stmt) > 0) ? "Data lahan berhasil diperbarui!" : "Tidak ada data yang diubah.";
            mysqli_stmt_close($stmt);

            if (!$ok) {
                throw new Exception("Gagal memperbarui data lahan/penanaman.");
            }
            
            // Langkah 2: OTOMATIS Update 'transaksi_keuangan'
            // Kita cari transaksi yang terkait (referensi_id)
            $deskripsi_transaksi = "Update biaya modal tanam $jenis di $nama (ID Lahan: $id)";
            $tanggal_transaksi = $mulai;
            
            // Coba update dulu, jika tidak ada, baru insert
            $stmt_keuangan_up = mysqli_prepare($conn, "UPDATE transaksi_keuangan SET jumlah = ?, deskripsi = ?, tanggal_transaksi = ? WHERE referensi_id = ? AND petani_id = ? AND tipe_transaksi = 'Pengeluaran'");
            mysqli_stmt_bind_param($stmt_keuangan_up, 'dssii', $biaya, $deskripsi_transaksi, $tanggal_transaksi, $id, $petani_id);
            mysqli_stmt_execute($stmt_keuangan_up);
            
            // Jika tidak ada baris yang ter-update (karena datanya belum ada), insert baru
            if (mysqli_stmt_affected_rows($stmt_keuangan_up) === 0) {
                $stmt_keuangan_in = mysqli_prepare($conn, "INSERT INTO transaksi_keuangan (petani_id, tipe_transaksi, jumlah, deskripsi, referensi_id, tanggal_transaksi) VALUES (?, 'Pengeluaran', ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt_keuangan_in, 'idsis', $petani_id, $biaya, $deskripsi_transaksi, $id, $tanggal_transaksi);
                mysqli_stmt_execute($stmt_keuangan_in);
                mysqli_stmt_close($stmt_keuangan_in);
            }
            mysqli_stmt_close($stmt_keuangan_up);

            // Jika semua berhasil
            mysqli_commit($conn);

            $res = mysqli_query($conn, "SELECT * FROM lahan WHERE id = " . (int)$id);
            $row = mysqli_fetch_assoc($res);
            echo json_encode(['success' => true, 'row' => $row, 'message' => $pesan]);
            exit;
        }

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            
            // Hapus dari 'lahan'
            $stmt = mysqli_prepare($conn, "DELETE FROM lahan WHERE id = ? AND petani_id = ?");
            mysqli_stmt_bind_param($stmt, 'ii', $id, $petani_id);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            if (!$ok) {
                 throw new Exception("Gagal menghapus data lahan/penanaman.");
            }

            // Hapus juga dari 'transaksi_keuangan'
            $stmt_keuangan_del = mysqli_prepare($conn, "DELETE FROM transaksi_keuangan WHERE referensi_id = ? AND petani_id = ? AND tipe_transaksi = 'Pengeluaran'");
            mysqli_stmt_bind_param($stmt_keuangan_del, 'ii', $id, $petani_id);
            mysqli_stmt_execute($stmt_keuangan_del);
            mysqli_stmt_close($stmt_keuangan_del);
            
            mysqli_commit($conn);
            echo json_encode(['success' => true]);
            exit;
        }

    } catch (Exception $e) {
        // Jika terjadi error, batalkan semua perubahan
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// ==================================================================
// BAGIAN HTML (PAGE LOAD BIASA)
// ==================================================================

// Fetch existing entries for this petani
$entries = [];
if ($petani_id) {
    $stmt = mysqli_prepare($conn, "SELECT id, petani_id, nama_lahan, jenis_tanaman, mulai_tanam, tanggal_panen, biaya_modal FROM lahan WHERE petani_id = ? ORDER BY id DESC");
    mysqli_stmt_bind_param($stmt, 'i', $petani_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        if ($row['tanggal_panen'] === null) $row['tanggal_panen'] = '';
        $entries[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// --- Ambil daftar unik nama lahan dari tabel MASTER (manajemen_lahan) ---
$lahan_options_master = [];
if ($petani_id) {
    // Membaca dari tabel 'manajemen_lahan'
    $stmt_options = mysqli_prepare($conn, "SELECT DISTINCT nama_lahan FROM manajemen_lahan WHERE petani_id = ? ORDER BY nama_lahan ASC");
    mysqli_stmt_bind_param($stmt_options, 'i', $petani_id);
    mysqli_stmt_execute($stmt_options);
    $res_options = mysqli_stmt_get_result($stmt_options);
    while ($row = mysqli_fetch_assoc($res_options)) {
        $lahan_options_master[] = $row['nama_lahan'];
    }
    mysqli_stmt_close($stmt_options);
}
// --- SELESAI ---

?>

<!DOCTYPE html>
<html :class="{ 'theme-dark': dark }" x-data="data()" lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Data Lahan & Penanaman - TaniMaju</title>
        <link
            href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
            rel="stylesheet"
        />
        <link rel="stylesheet" href="./assets/css/tailwind.output.css" />
        <script
            src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js"
            defer
        ></script>
        <script src="./assets/js/init-alpine.js"></script>
        <link
            rel="stylesheet"
            href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.3/Chart.min.css"
        />
        <script
            src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.3/Chart.min.js"
            defer
        ></script>
        <script src="./assets.js/charts-lines.js" defer></script>
        <script src="./assets/js/charts-pie.js" defer></script>
    </head>
    <body>
        <div
            class="flex h-screen bg-gray-50 dark:bg-gray-900"
            :class="{ 'overflow-hidden': isSideMenuOpen }"
        >
              <aside
                class="z-20 hidden w-64 overflow-y-auto bg-white dark:bg-gray-800 md:block flex-shrink-0"
            >
                <div class="py-4 text-gray-500 dark:text-gray-400">
                    <a
                        class="ml-6 text-lg font-bold text-gray-800 dark:text-gray-200 flex items-center"
                        href="#"
                    >
                        <svg class="w-6 h-6 mr-2 text-green-600" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M4 14V8a2 2 0 012-2h12a2 2 0 012 2v6m-4 0v4m-8-4v4m0-8h8m-8 0V6"></path>
                        </svg>
                        <span>TaniMaju</span>
                    </a>
                    <ul class="mt-6">
                        <li class="relative px-6 py-3">
                            <a
                                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
                                href="DasboardPetani.php"
                            >
                                <svg
                                    class="w-5 h-5"
                                    aria-hidden="true"
                                    fill="none"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
                                    ></path>
                                </svg>
                                <span class="ml-4">Dashboard</span>
                            </a>
                        </li>
                    </ul>
                    <ul>

                    <li class="relative px-6 py-3">
                            <a
                                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
                                href="kebun.php"
                            >
                                <svg
                                    class="w-5 h-5"
                                    aria-hidden="true"
                                    fill="none"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path d="M4 14V8a2 2 0 012-2h12a2 2 0 012 2v6m-4 0v4m-8-4v4m0-8h8m-8 0V6"></path>
                                </svg>
                                <span class="ml-4">Kelola Lahan</span>
                            </a>
                        </li>
                        <li class="relative px-6 py-3">
                            <span
                                class="absolute inset-y-0 left-0 w-1 bg-green-600 rounded-tr-lg rounded-br-lg"
                                aria-hidden="true"
                            ></span>
                            <a
                                class="inline-flex items-center w-full text-sm font-semibold text-gray-800 transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200 dark:text-gray-100"
                                href="DataLahan.php"
                            >
                                <svg
                                    class="w-5 h-5"
                                    aria-hidden="true"
                                    fill="none"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"
                                    ></path>
                                </svg>
                                <span class="ml-4">Data Penanaman</span>
                            </a>
                        </li>

                        <li class="relative px-6 py-3">
                            <a
                                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
                                href="KelolaProduk.php"
                            >
                                <svg
                                    class="w-5 h-5"
                                    aria-hidden="true"
                                    fill="none"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"
                                    ></path>
                                    <path d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"></path>
                                </svg>
                                <span class="ml-4">Kelola Produk</span>
                            </a>
                        </li>

                        <li class="relative px-6 py-3">
                            <a
                                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
                                href="PesananMasuk.php"
                            >
                                <svg
                                    class="w-5 h-5"
                                    aria-hidden="true"
                                    fill="none"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                                </svg>
                                <span class="ml-4">Pesanan Masuk</span>
                            </a>
                        </li> <li class="relative px-6 py-3">
                            <a
                                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
                                href="CatatanKeuangan.php"
                            >
                                <svg
                                    class="w-5 h-5"
                                    aria-hidden="true"
                                    fill="none"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0c-1.657 0-3-.895-3-2s1.343-2 3-2 3-.895 3-2 1.343-2 3-2m0 8c1.11 0 2.08-.402 2.599-1M12 16V7m0 9v-1m0-8V7m0 0h.01M6 12h.01M6 12h.01M6 12h.01M18 12h.01M18 12h.01M18 12h.01M6 9h.01M6 15h.01M18 9h.01M18 15h.01"></path>
                                </svg>
                                <span class="ml-4">Catatan Keuangan</span>
                            </a>
                        </li>      
                    </ul>
                </div>
            </aside>
            <div
                x-show="isSideMenuOpen"
                x-transition:enter="transition ease-in-out duration-150"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in-out duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 z-10 flex items-end bg-black bg-opacity-50 sm:items-center sm:justify-center"
            ></div>
            <aside
                class="fixed inset-y-0 z-20 flex-shrink-0 w-64 mt-16 overflow-y-auto bg-white dark:bg-gray-800 md:hidden"
                x-show="isSideMenuOpen"
                x-transition:enter="transition ease-in-out duration-150"
                x-transition:enter-start="opacity-0 transform -translate-x-20"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in-out duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0 transform -translate-x-20"
                @click.away="closeSideMenu"
                @keydown.escape="closeSideMenu"
            >
                <div class="py-4 text-gray-500 dark:text-gray-400">
                    <a
                        class="ml-6 text-lg font-bold text-gray-800 dark:text-gray-200 flex items-center"
                        href="DasboardPetani.php"
                    >
                        <svg class="w-6 h-6 mr-2 text-green-600" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M4 14V8a2 2 0 012-2h12a2 2 0 012 2v6m-4 0v4m-8-4v4m0-8h8m-8 0V6"></path>
                        </svg>
                        <span>TaniMaju</span>
                    </a>
                    <ul class="mt-6">
                        <li class="relative px-6 py-3">
                            <a
                                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
                                href="DasboardPetani.php"
                            >
                                <svg
                                    class="w-5 h-5"
                                    aria-hidden="true"
                                    fill="none"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
                                    ></path>
                                </svg>
                                <span class="ml-4">Dashboard</span>
                            </a>
                        </li>
                    </ul>
                    <ul>
                    <li class="relative px-6 py-3">
                            <a
                                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
                                href="kebun.php"
                            >
                                <svg
                                    class="w-5 h-5"
                                    aria-hidden="true"
                                    fill="none"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path d="M4 14V8a2 2 0 012-2h12a2 2 0 012 2v6m-4 0v4m-8-4v4m0-8h8m-8 0V6"></path>
                                </svg>
                                <span class="ml-4">Kelola Lahan</span>
                            </a>
                        </li>
                        <li class="relative px-6 py-3">
                            <span
                                class="absolute inset-y-0 left-0 w-1 bg-green-600 rounded-tr-lg rounded-br-lg"
                                aria-hidden="true"
                            ></span>
                            <a
                                class="inline-flex items-center w-full text-sm font-semibold text-gray-800 transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200 dark:text-gray-100"
                                href="DataLahan.php"
                            >
                                <svg
                                    class="w-5 h-5"
                                    aria-hidden="true"
                                    fill="none"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"
                                    ></path>
                                </svg>
                                <span class="ml-4">Data Penanaman</span>
                            </a>
                        </li>

                        <li class="relative px-6 py-3">
                            <a
                                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
                                href="KelolaProduk.php"
                            >
                                <svg
                                    class="w-5 h-5"
                                    aria-hidden="true"
                                    fill="none"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"
                                    ></path>
                                    <path d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"></path>
                                </svg>
                                <span class="ml-4">Kelola Produk</span>
                            </a>
                        </li>

                        <li class="relative px-6 py-3">
                            <a
                                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
                                href="PesananMasuk.php"
                            >
                                <svg
                                    class="w-5 h-5"
                                    aria-hidden="true"
                                    fill="none"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                                </svg>
                                <span class="ml-4">Pesanan Masuk</span>
                            </a>
                        </li> 
                        <li class="relative px-6 py-3">
                            <a
                                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
                                href="CatatanKeuangan.php"
                            >
                                <svg
                                    class="w-5 h-5"
                                    aria-hidden="true"
                                    fill="none"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0c-1.657 0-3-.895-3-2s1.343-2 3-2 3-.895 3-2 1.343-2 3-2m0 8c1.11 0 2.08-.402 2.599-1M12 16V7m0 9v-1m0-8V7m0 0h.01M6 12h.01M6 12h.01M6 12h.01M18 12h.01M18 12h.01M18 12h.01M6 9h.01M6 15h.01M18 9h.01M18 15h.01"></path>
                                </svg>
                                <span class="ml-4">Catatan Keuangan</span>
                            </a>
                        </li>      
                    </ul>
                </div>
            </aside>
            <div class="flex flex-col flex-1 w-full">
                <header class="z-10 py-4 bg-white shadow-md dark:bg-gray-800">
                    <div
                        class="container flex items-center justify-between h-full px-6 mx-auto text-green-600 dark:text-green-300"
                    >
                        <button
                            class="p-1 mr-5 -ml-1 rounded-md md:hidden focus:outline-none focus:shadow-outline-green"
                            @click="toggleSideMenu"
                            aria-label="Menu"
                        >
                            <svg
                                class="w-6 h-6"
                                aria-hidden="true"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"
                                    clip-rule="evenodd"
                                ></path>
                            </svg>
                        </button>
                        <div class="flex justify-center flex-1 lg:mr-32">
                            <div
                                class="relative w-full max-w-xl mr-6 focus-within:text-green-500"
                            >
                                <div class="absolute inset-y-0 flex items-center pl-2">
                                    <svg
                                        class="w-4 h-4"
                                        aria-hidden="true"
                                        fill="currentColor"
                                        viewBox="0 0 20 20"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                                            clip-rule="evenodd"
                                        ></path>
                                    </svg>
                                </div>
                                <input
                                    class="w-full pl-8 pr-2 text-sm text-gray-700 placeholder-gray-600 bg-gray-100 border-0 rounded-md dark:placeholder-gray-500 dark:focus:shadow-outline-gray dark:focus:placeholder-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:placeholder-gray-500 focus:bg-white focus:border-green-300 focus:outline-none focus:shadow-outline-green form-input"
                                    type="text"
                                    placeholder="Search for projects"
                                    aria-label="Search"
                                />
                            </div>
                        </div>
                        <ul class="flex items-center flex-shrink-0 space-x-6">
                            <li class="flex">
                                <button
                                    class="rounded-md focus:outline-none focus:shadow-outline-green"
                                    @click="toggleTheme"
                                    aria-label="Toggle color mode"
                                >
                                    <template x-if="!dark">
                                        <svg
                                            class="w-5 h-5"
                                            aria-hidden="true"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                        >
                                            <path
                                                d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"
                                            ></path>
                                        </svg>
                                    </template>
                                    <template x-if="dark">
                                        <svg
                                            class="w-5 h-5"
                                            aria-hidden="true"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                        >
                                            <path
                                                fill-rule="evenodd"
                                                d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"
                                                clip-rule="evenodd"
                                            ></path>
                                        </svg>
                                    </template>
                                </button>
                            </li>
                            <li class="relative">
                                <button
                                    class="align-middle rounded-full focus:shadow-outline-green focus:outline-none"
                                    @click="toggleProfileMenu"
                                    @keydown.escape="closeProfileMenu"
                                    aria-label="Account"
                                    aria-haspopup="true"
                                >
                                    <img
                                        class="object-cover w-8 h-8 rounded-full"
                                        src="https://images.unsplash.com/photo-1502378735452-bc7d86632805?ixlib=rb-0.3.5&q=80&fm=jpg&crop=entropy&cs=tinysrgb&w=200&fit=max&s=aa3a807e1bbdfd4364d1f449eaa96d82"
                                        alt=""
                                        aria-hidden="true"
                                    />
                                </button>
                                <template x-if="isProfileMenuOpen">
                                    <ul
                                        x-transition:leave="transition ease-in duration-150"
                                        x-transition:leave-start="opacity-100"
                                        x-transition:leave-end="opacity-0"
                                        @click.away="closeProfileMenu"
                                        @keydown.escape="closeProfileMenu"
                                        class="absolute right-0 w-56 p-2 mt-2 space-y-2 text-gray-600 bg-white border border-gray-100 rounded-md shadow-md dark:border-gray-700 dark:text-gray-300 dark:bg-gray-700"
                                        aria-label="submenu"
                                    >
                                        <li class="flex">
                                            <a
                                                class="inline-flex items-center w-full px-2 py-1 text-sm font-semibold transition-colors duration-150 rounded-md hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                                                href="profil.php"
                                            >
                                                <svg
                                                    class="w-4 h-4 mr-3"
                                                    aria-hidden="true"
                                                    fill="none"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                >
                                                    <path
                                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                                    ></path>
                                                </svg>
                                                <span>Profile</span>
                                            </a>
                                        </li>
                                        
                                        <li class="flex">
                                            <a
                                                class="inline-flex items-center w-full px-2 py-1 text-sm font-semibold transition-colors duration-150 rounded-md hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                                                href="../public/pages/LogOut.php"
                                            >
                                                <svg
                                                    class="w-4 h-4 mr-3"
                                                    aria-hidden="true"
                                                    fill="none"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                >
                                                    <path
                                                        d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"
                                                    ></path>
                                                </svg>
                                                <span>Log out</span>
                                            </a>
                                        </li>
                                    </ul>
                                </template>
                            </li>
                        </ul>
                    </div>
                </header>

                <main class="h-full pb-16 overflow-y-auto">
                    <div
                        class="container px-6 mx-auto grid"
                        x-data="dataLahan()"
                    >

                        <div
                            x-show="isModalOpen"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-transition:leave="transition ease-in duration-110"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            class="fixed inset-0 z-30 flex items-end bg-black bg-opacity-50 sm:items-center sm:justify-center"
                            style="display: none;"
                        >
                            <div
                                x-show="isModalOpen"
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 transform translate-y-1/2"
                                x-transition:enter-end="opacity-100"
                                x-transition:leave="transition ease-in duration-110"
                                x-transition:leave-start="opacity-100"
                                x-transition:leave-end="opacity-0 transform translate-y-1/2"
                                @click.away="resetForm()"
                                @keydown.escape.window="resetForm()"
                                class="w-full max-w-2xl px-6 py-4 overflow-hidden bg-white rounded-t-lg dark:bg-gray-800 sm:rounded-lg sm:m-4"
                                role="dialog"
                            >
                                <header class="flex justify-between items-center">
                                    <h4 class="mb-4 text-lg font-semibold text-gray-600 dark:text-gray-300"
                                            x-text="editingIndex === null ? 'Tambah Penanaman Baru' : 'Edit Penanaman'">
                                    </h4>
                                    <button
                                        class="inline-flex items-center justify-center w-6 h-6 text-gray-400 transition-colors duration-150 rounded dark:hover:text-gray-200 hover:text-gray-700"
                                        aria-label="close"
                                        @click="resetForm()"
                                    >
                                        <svg
                                        class="w-4 h-4"
                                        fill="currentColor"
                                        viewBox="0 0 20 20"
                                        role="img"
                                        aria-hidden="true"
                                        >
                                        <path
                                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                            clip-rule="evenodd"
                                            fill-rule="evenodd"
                                        ></path>
                                        </svg>
                                    </button>
                                </header>

                                <div class="mt-4 mb-6">
                                    <form x-ref="form" @submit.prevent="save()">
                                        
                                        <div class="grid gap-4 mt-4 md:grid-cols-2">
                                            <label class="block text-sm">
                                                <span class="text-gray-700 dark:text-gray-400">
                                                    Nama Lahan
                                                </span>
                                                <select
                                                    x-ref="nama"
                                                    name="nama_lahan"
                                                    class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-green-400 focus:outline-none focus:shadow-outline-green form-select dark:text-gray-300"
                                                    required
                                                >
                                                    <option value="">-- Pilih Nama Lahan --</option>
                                                    <template x-for="lahan in lahanOptionsMaster" :key="lahan">
                                                    <option :value="lahan" x-text="lahan"></option>
                                                    </template>
                                                </select>
                                            </label>
                                            
                                            <label class="block text-sm">
                                                <span class="text-gray-700 dark:text-gray-400">
                                                    Jenis Tanaman
                                                </span>
                                                <input
                                                    x-ref="jenis"
                                                    type="text"
                                                    name="jenis_tanaman"
                                                    class="block w-full mt-1 text-sm dark:text-gray-300 dark:border-gray-600 dark:bg-gray-700 form-input focus:border-green-400 focus:outline-none focus:shadow-outline-green"
                                                    placeholder="Contoh: Padi, Cabai Rawit, dll."
                                                    required
                                                />
                                            </label>
                                        </div>


                                        <div class="grid gap-4 mt-4 md:grid-cols-2">
                                            <label class="block text-sm">
                                                <span class="text-gray-700 dark:text-gray-400">
                                                    Tanggal Mulai Tanam
                                                </span>
                                                <input
                                                    x-ref="tgl_mulai"
                                                    type="date"
                                                    name="tgl_mulai"
                                                    class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-green-400 focus:outline-none focus:shadow-outline-green form-input dark:text-gray-300"
                                                    required
                                                />
                                            </label>

                                            <label class="block text-sm">
                                                <span class="text-gray-700 dark:text-gray-400">
                                                    Perkiraan Tanggal Panen (Opsional)
                                                </span>
                                                <input
                                                    x-ref="tgl_panen"
                                                    type="date"
                                                    name="tgl_panen"
                                                    class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-green-400 focus:outline-none focus:shadow-outline-green form-input dark:text-gray-300"
                                                />
                                            </label>
                                        </div>

                                        <label class="block mt-4 text-sm">
                                            <span class="text-gray-700 dark:text-gray-400">
                                                Biaya Modal (Rp)
                                            </span>
                                            <input
                                                x-ref="biaya"
                                                type="text"
                                                inputmode="numeric"
                                                name="biaya_modal"
                                                @input="$event.target.value = formatRupiah($event.target.value, 'Rp. ')"
                                                min="0"
                                                class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-green-400 focus:outline-none focus:shadow-outline-green form-input dark:text-gray-300"
                                                placeholder="Rp. 0"
                                                required
                                            />
                                        </label>

                                        <div class="flex items-center justify-end mt-6">
                                            <button
                                                type="reset"
                                                @click="resetForm()"
                                                class="px-4 py-2 mr-3 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 focus:outline-none"
                                            >
                                                Reset
                                            </button>
                                            <button
                                                type="submit"
                                                class="px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-md hover:bg-purple-700 focus:outline-none focus:shadow-outline-purple"
                                                x-text="editingIndex === null ? 'Simpan' : 'Perbarui'"
                                            ></button>
                                        </div>
                                    </form>
                                </div> 
                                </div>
                        </div>
                        <div class="flex items-center justify-between my-6">
                            <h2
                                class="text-2xl font-semibold text-gray-700 dark:text-gray-200"
                            >
                                Data Penanaman Kamu
                            </h2>
                            <button
                                @click="openAdd()"
                                class="flex items-center px-3 py-2 text-sm font-bold text-white bg-purple-600 rounded-md hover:bg-purple-700 focus:outline-none focus:shadow-outline-purple"
                                type="button"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                <span x-text="isModalOpen && editingIndex === null ? 'Batal' : 'Tambah Penanaman'"></span>
                            </button>
                        </div>

                        <div x-show="message.text"
                            :class="{ 'bg-green-600': message.type === 'success', 'bg-red-600': message.type === 'error' }"
                            class="px-4 py-3 mb-4 text-sm font-medium text-white rounded-lg"
                            role="alert"
                            x-text="message.text"
                            x-transition>
                        </div>

                        <div
                            class="bg-white rounded-lg shadow-md dark:bg-gray-800 overflow-x-auto"
                        >
                            <table class="w-full text-sm text-left">
                                <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                                    <tr>
                                        <th class="px-4 py-2">ID</th>
                                        <th class="px-4 py-2">Nama Lahan</th>
                                        <th class="px-4 py-2">Jenis Tanaman</th>
                                        <th class="px-4 py-2">Mulai Tanam</th>
                                        <th class="px-4 py-2">Perk. Panen</th>
                                        <th class="px-4 py-2">Biaya Modal (Rp)</th>
                                        <th class="px-4 py-2">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-if="entries.length === 0">
                                        <tr>
                                            <td class="px-4 py-3 text-center text-gray-500 dark:text-gray-400" colspan="7">
                                                Belum ada data penanaman.
                                            </td>
                                        </tr>
                                    </template>

                                    <template x-for="(e, i) in entries" :key="e.id">
                                        <tr class="text-gray-700 dark:text-gray-400 border-t dark:border-gray-700 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-4 py-3" x-text="e.id"></td>
                                            <td class="px-4 py-3" x-text="e.nama_lahan"></td>
                                            <td class="px-4 py-3" x-text="e.jenis_tanaman"></td>
                                            <td class="px-4 py-3" x-text="e.mulai_tanam"></td>
                                            <td class="px-4 py-3" x-text="e.tanggal_panen || '-'"></td>
                                            <td class="px-4 py-3" x-text="formatPrice(e.biaya_modal)"></td>
                                            <td class="px-4 py-3 space-x-2">
                                                <button
                                                    @click="editEntry(i)"
                                                    class="px-2 py-1 text-xs text-white bg-blue-600 rounded hover:bg-blue-700 focus:outline-none"
                                                    type="button"
                                                >
                                                    Edit
                                                </button>
                                                <button
                                                    @click="deleteEntry(i)"
                                                    class="px-2 py-1 text-xs text-white bg-red-600 rounded hover:bg-red-700 focus:outline-none"
                                                    type="button"
                                                >
                                                    Hapus
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </main>
            </div>
        </div>
        
        <script>
            function dataLahan() {
                return {
                    // ▼▼▼ PERUBAHAN: 'showForm' diganti 'isModalOpen' ▼▼▼
                    isModalOpen: false,
                    entries: <?php echo json_encode($entries, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
                    // OPSI DROPDOWN (dari tabel manajemen_lahan)
                    lahanOptionsMaster: <?php echo json_encode($lahan_options_master, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
                    editingIndex: null,
                    message: { type: '', text: '' }, 

                    // --- Helper Format Rupiah ---
                    formatRupiah(angka, prefix) {
                        let number_string = (angka || '').toString().replace(/[^0-9]/g, '');
                        if (number_string.length === 0) return ''; 
                        let rupiah = number_string.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                        return (prefix || '') + rupiah;
                    },
                    formatPrice(value) {
                        if (isNaN(value) || value === null) return 'Rp 0';
                        return 'Rp ' + Number(value).toLocaleString('id-ID');
                    },
                    
                    // --- Fungsi Tampil Pesan ---
                    showMessage(type, text) {
                        this.message.type = type;
                        this.message.text = text;
                        setTimeout(() => {
                            this.message.text = '';
                        }, 3000);
                    },

                    // --- CRUD Functions ---
                    editEntry(i) {
                        const e = this.entries[i];
                        this.editingIndex = i;
                        // ▼▼▼ PERUBAHAN: 'showForm' diganti 'isModalOpen' ▼▼▼
                        this.isModalOpen = true;
                        
                        // Isi form dengan data yang ada
                        this.$nextTick(() => {
                            if (this.$refs.nama) this.$refs.nama.value = e.nama_lahan || ''; 
                            if (this.$refs.jenis) this.$refs.jenis.value = e.jenis_tanaman || '';
                            if (this.$refs.tgl_mulai) this.$refs.tgl_mulai.value = e.mulai_tanam || '';
                            if (this.$refs.tgl_panen) this.$refs.tgl_panen.value = e.tanggal_panen || '';
                            if (this.$refs.biaya) this.$refs.biaya.value = this.formatRupiah(e.biaya_modal.toString(), 'Rp. ');
                        });
                    },

                    async save() {
                        const form = new FormData();
                        form.append('nama_lahan', this.$refs.nama.value); // Ambil nilai dari SELECT
                        form.append('jenis_tanaman', this.$refs.jenis.value);
                        form.append('tgl_mulai', this.$refs.tgl_mulai.value);
                        form.append('tgl_panen', this.$refs.tgl_panen.value);
                        
                        let biayaClean = this.$refs.biaya.value.replace(/[^0-9]/g, '');
                        form.append('biaya_modal', biayaClean || 0);

                        if (this.editingIndex === null) {
                            form.append('action', 'create');
                        } else {
                            form.append('action', 'update');
                            form.append('id', this.entries[this.editingIndex].id);
                        }

                        try {
                            const res = await fetch(window.location.pathname, {
                                method: 'POST',
                                body: form,
                            });
                            const data = await res.json();
                            
                            if (data.success) {
                                if (data.row) {
                                    if (this.editingIndex === null) {
                                        this.entries.unshift(data.row);
                                        this.showMessage('success', 'Data penanaman baru berhasil disimpan!');
                                    } else {
                                        this.entries.splice(this.editingIndex, 1, data.row);
                                        this.showMessage('success', data.message || 'Data penanaman berhasil diperbarui!');
                                    }
                                }
                                this.resetForm();
                            } else {
                                this.showMessage('error', data.message || 'Gagal menyimpan');
                            }
                        } catch (err) {
                            console.error(err);
                            this.showMessage('error', 'Terjadi kesalahan jaringan');
                        }
                    },

                    async deleteEntry(i) {
                        if (!confirm('Hapus data penanaman ini? Ini juga akan menghapus catatan keuangan terkait.')) return;
                        
                        const id = this.entries[i].id;
                        const form = new FormData();
                        form.append('action', 'delete');
                        form.append('id', id);

                        try {
                            const res = await fetch(window.location.pathname, { method: 'POST', body: form });
                            const data = await res.json();
                            
                            if (data.success) {
                                this.entries.splice(i, 1);
                                this.showMessage('success', 'Data berhasil dihapus.');
                            } else {
                                this.showMessage('error', data.message || 'Gagal menghapus');
                            }
                        } catch (err) {
                            console.error(err);
                            this.showMessage('error', 'Terjadi kesalahan jaringan saat menghapus');
                        }
                    },

                    resetForm() {
                        // ▼▼▼ PERUBAHAN: 'showForm' diganti 'isModalOpen' ▼▼▼
                        this.isModalOpen = false;
                        this.editingIndex = null;
                        if (this.$refs && this.$refs.form) this.$refs.form.reset();
                    },
                    
                    openAdd() {
                        // ▼▼▼ PERUBAHAN: 'showForm' diganti 'isModalOpen' ▼▼▼
                        if (this.isModalOpen && this.editingIndex === null) {
                            this.resetForm(); // Jika form tambah sudah terbuka, tutup
                        } else {
                            this.resetForm(); // Reset dulu (ini juga menutup form edit)
                            this.isModalOpen = true; // Buka form
                        }
                    }
                }
            }
        </script>
    </body>
</html>