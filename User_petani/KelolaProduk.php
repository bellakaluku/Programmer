<?php
// 1. MEMULAI SESSION & KONEKSI
session_start();
include __DIR__ . '/../public/pages/Connection.php'; // Path ke Connection.php

// 2. KEAMANAN: Cek jika user sudah login dan adalah 'petani'
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'petani') {
    $petani_id = null;
} else {
    $petani_id = (int) $_SESSION['user_id'];
}


$sql_kategori = "SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori ASC";
$result_kategori = $conn->query($sql_kategori);

$daftar_kategori = [];
if ($result_kategori && $result_kategori->num_rows > 0) {
    while ($row = $result_kategori->fetch_assoc()) {
        $daftar_kategori[] = $row;
    }
}

// 3. PROSES AJAX (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    if (!$petani_id) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    // --- Aksi Create atau Update ---
    if ($action === 'create' || $action === 'update') {
        // Ambil data form
        $nama_produk = trim($_POST['nama_produk'] ?? '');
        $keterangan = trim($_POST['keterangan'] ?? '');
        $stok_kg = (int) ($_POST['stok_kg'] ?? 0);
        $harga_kg = (int) (preg_replace("/[^0-9]/", "", $_POST['harga_kg'] ?? '0'));
        
        // --- PERBAIKAN 1: Ambil kategori_id ---
        $kategori_id = (int)($_POST['kategori_id'] ?? 0);
        if ($kategori_id === 0) {
            $kategori_id = null;
        }
        // --- SELESAI PERBAIKAN 1 ---
        
        // --- LOGIKA UPLOAD FOTO DIMULAI ---
        
        // Tentukan path upload
        // __DIR__ adalah folder saat ini (User_petani)
        $upload_dir_path = __DIR__ . '/assets/img/produk/';
        // Path web yang akan disimpan ke DB
        $web_path_default = 'assets/img/produk/default.png'; // Foto default

        // Pastikan folder ada
        if (!is_dir($upload_dir_path)) {
            mkdir($upload_dir_path, 0755, true);
        }

        $foto_path = ''; // Variabel untuk menyimpan path DB

        if ($action === 'create') {
            $foto_path = $web_path_default; // Set default dulu
            
            // Cek jika ada file baru di-upload
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $file_ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                $unique_name = uniqid('produk_', true) . '.' . $file_ext;
                
                if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir_path . $unique_name)) {
                    $foto_path = 'assets/img/produk/' . $unique_name; // Path web relatif baru
                }
            }

            // --- PERBAIKAN 2: Query INSERT ---
            $stmt = mysqli_prepare($conn, "INSERT INTO produk (petani_id, nama_produk, keterangan, stok_kg, harga_kg, foto, kategori_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'issiisi', $petani_id, $nama_produk, $keterangan, $stok_kg, $harga_kg, $foto_path, $kategori_id);
            // --- SELESAI PERBAIKAN 2 ---
            
            $ok = mysqli_stmt_execute($stmt);
            $newId = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

        } else { // $action === 'update'
            $id = (int) ($_POST['id'] ?? 0);

            // 1. Ambil foto lama dulu dari database
            $stmt_get_foto = mysqli_prepare($conn, "SELECT foto FROM produk WHERE id = ? AND petani_id = ?");
            mysqli_stmt_bind_param($stmt_get_foto, 'ii', $id, $petani_id);
            mysqli_stmt_execute($stmt_get_foto);
            $foto_path = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get_foto))['foto'] ?? $web_path_default;
            mysqli_stmt_close($stmt_get_foto);

            // 2. Cek jika ada file BARU di-upload
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $file_ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                $unique_name = uniqid('produk_', true) . '.' . $file_ext;
                
                if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir_path . $unique_name)) {
                    // TODO: Hapus file foto lama ($foto_path) dari server jika bukan 'default.png'
                    $foto_path = 'assets/img/produk/' . $unique_name; // Path web baru
                }
            }
            // Jika tidak ada file baru, $foto_path akan tetap berisi path foto lama

            // --- PERBAIKAN 3: Query UPDATE ---
            $stmt = mysqli_prepare($conn, "UPDATE produk SET nama_produk = ?, keterangan = ?, stok_kg = ?, harga_kg = ?, foto = ?, kategori_id = ? WHERE id = ? AND petani_id = ?");
            mysqli_stmt_bind_param($stmt, 'ssiisiii', $nama_produk, $keterangan, $stok_kg, $harga_kg, $foto_path, $kategori_id, $id, $petani_id);
            // --- SELESAI PERBAIKAN 3 ---

            $ok = mysqli_stmt_execute($stmt);
            $newId = $id; // Gunakan ID yang sama untuk fetch
            mysqli_stmt_close($stmt);
        }
        // --- LOGIKA UPLOAD FOTO SELESAI ---

        if ($ok) {
            // Ambil data yang baru disimpan/diupdate
            // --- PERBAIKAN 4A: Query Fetch (setelah update/create) ---
            $query_fetch = "SELECT p.*, k.nama_kategori 
                            FROM produk p
                            LEFT JOIN kategori k ON p.kategori_id = k.id
                            WHERE p.id = ? AND p.petani_id = ?";
            // --- SELESAI PERBAIKAN 4A ---
            
            $stmt_fetch = mysqli_prepare($conn, $query_fetch);
            mysqli_stmt_bind_param($stmt_fetch, "ii", $newId, $petani_id);
            mysqli_stmt_execute($stmt_fetch);
            $res = mysqli_stmt_get_result($stmt_fetch);
            $row = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt_fetch);
            
            echo json_encode(['success' => true, 'row' => $row]);
            exit;
        }
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data ke database.']);
        exit;
    }

    // --- Aksi Delete ---
    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        // TODO: Hapus file foto dari server sebelum menghapus data DB
        // --- PERBAIKAN: Ganti ke Soft Delete ---
        $stmt = mysqli_prepare($conn, "UPDATE produk SET status = 'nonaktif' WHERE id = ? AND petani_id = ?");
        // $stmt = mysqli_prepare($conn, "DELETE FROM produk WHERE id = ? AND petani_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $petani_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        // Kirim 'soft_deleted' agar JS tahu ini bukan error
        echo json_encode(['success' => (bool)$ok, 'soft_deleted' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// 4. AMBIL DATA AWAL UNTUK TABEL
$entries = [];
if ($petani_id) {
    // --- PERBAIKAN 4B: Query JOIN untuk data awal tabel ---
    $query = "SELECT p.*, k.nama_kategori 
              FROM produk p
              LEFT JOIN kategori k ON p.kategori_id = k.id
              WHERE p.petani_id = ? AND p.status = 'aktif'
              ORDER BY p.id DESC";
    // --- SELESAI PERBAIKAN 4B ---
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $petani_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $entries[] = $row;
    }
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html :class="{ 'theme-dark': dark }" x-data="data()" lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Kelola Produk - TaniMaju</title>
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
        <script src="./assets/js/charts-lines.js" defer></script>
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
                            <a
                                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
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
                            <span
                                class="absolute inset-y-0 left-0 w-1 bg-green-600 rounded-tr-lg rounded-br-lg"
                                aria-hidden="true"
                            ></span>
                            <a
                                class="inline-flex items-center w-full text-sm font-semibold text-gray-800 transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200 dark:text-gray-100"
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
                            <span
                                class="absolute inset-y-0 left-0 w-1 bg-green-600 rounded-tr-lg rounded-br-lg"
                                aria-hidden="true"
                            ></span>
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
            x-data="dataProduk()"
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
                          x-text="editingIndex === null ? 'Tambah Produk Baru' : 'Edit Produk'">
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
                    
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block text-sm">
                          <span class="text-gray-700 dark:text-gray-400">Nama Produk</span>
                          <input
                            x-ref="nama_produk"
                            type="text"
                            name="nama_produk"
                            class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300 form-input"
                            placeholder="Contoh: Bawang Merah"
                            required
                          />
                        </label>

                        <label class="block text-sm">
                            <span class="text-gray-700 dark:text-gray-400">Kategori Produk</span>
                            <select 
                            name="kategori_id" 
                            id="kategori_id" 
                            class="block w-full mt-1 text-sm dark:text-gray-300 dark:border-gray-600 dark:bg-gray-700 form-select focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:focus:shadow-outline-gray" 
                            required
                            >
                            <option value="">-- Pilih Kategori --</option>
                    
                            <?php foreach ($daftar_kategori as $kategori): ?>
                                <option value="<?php echo $kategori['id']; ?>">
                                    <?php echo htmlspecialchars($kategori['nama_kategori']); ?>
                                </option>
                            <?php endforeach; ?> 
                        </select>
                        </label>

                    </div>

                      <label class="block text-sm mt-4">
                        <span class="text-gray-700 dark:text-gray-400">Keterangan</span>
                        <textarea
                          x-ref="keterangan"
                          name="keterangan"
                          rows="3"
                          class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300 form-input"
                          placeholder="Deskripsi singkat produk"
                        ></textarea>
                    </label>

                    <div class="grid gap-4 mt-4 md:grid-cols-2">
                      <label class="block text-sm">
                        <span class="text-gray-700 dark:text-gray-400">Stok Tersedia (Kg)</span>
                        <input
                          x-ref="stok_kg"
                          type="number"
                          name="stok_kg"
                          min="0"
                          class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300 form-input"
                          required
                        />
                      </label>

                      <label class="block text-sm">
                        <span class="text-gray-700 dark:text-gray-400">Harga / Kg (Rp)</span>
                        <input
                          x-ref="harga_kg"
                          type="text"
                          name="harga_kg"
                          @input="$event.target.value = formatRupiah($event.target.value, 'Rp. ')"
                          class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300 form-input"
                          required
                        />
                      </label>
                    </div>

                    <label class="block mt-4 text-sm">
                      <span class="text-gray-700 dark:text-gray-400">Foto Produk</span>
                        <input
                        x-ref="foto"
                        type="file"
                        name="foto"
                        accept="image/*"
                        class="block w-full mt-1 text-sm dark:text-gray-300"
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
                Kelola Produk
              </h2>
              <button
                @click="openAdd()"
                class="flex items-center px-3 py-2 text-sm font-medium text-white bg-purple-600 rounded-md hover:bg-purple-700 focus:outline-none"
                type="button"
              >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <span x-text="isModalOpen && editingIndex === null ? 'Batal' : 'Tambah Produk'"></span>
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
                    <th class="px-4 py-2">#</th>
                    <th class="px-4 py-2">Foto</th>
                    <th class="px-4 py-2">Nama Produk</th>
                    <th class="px-4 py-2">Keterangan</th> 
                    <th class="px-4 py-2">Kategori</th>
                    <th class="px-4 py-2">Stok (Kg)</th>
                    <th class="px-4 py-2">Harga / Kg</th>
                    <th class="px-4 py-2">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <template x-if="entries.length === 0">
                    <tr>
                      <td class="px-4 py-3 text-center text-gray-500 dark:text-gray-400" colspan="8"> Belum ada data produk.
                      </td>
                    </tr>
                  </template>

                  <template x-for="(e, i) in entries" :key="e.id">
                    <tr class="text-gray-700 dark:text-gray-400 border-t dark:border-gray-700">
                      <td class="px-4 py-3" x-text="i+1"></td>
                      <td class="px-4 py-3">
                        <img :src="e.foto.startsWith('assets') ? e.foto : 'assets/img/produk/default.png'" alt="Foto Produk" class="w-12 h-12 object-cover rounded">
                      </td>
                      <td class="px-4 py-3" x-text="e.nama_produk"></td>
                      <td class="px-4 py-3 truncate" style="max-width: 150px;" x-text="e.keterangan || '-'"></td>
                      <td class="px-4 py-3" x-text="e.nama_kategori || '-'"></td>
                      <td class="px-4 py-3" x-text="e.stok_kg"></td>
                      <td class="px-4 py-3" x-text="formatPrice(e.harga_kg)"></td>
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
      function dataProduk() {
        return {
          // ▼▼▼ PERUBAHAN: 'showForm' diganti menjadi 'isModalOpen' ▼▼▼
          isModalOpen: false,
          // ▲▲▲ SELESAI PERUBAHAN ▲▲▲
          
          entries: <?php echo json_encode($entries, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
          editingIndex: null,
          message: { type: '', text: '' },

          // --- Helper Format Rupiah ---
          formatRupiah(angka, prefix) {
            let number_string = (angka || '').toString().replace(/[^,\d]/g, '');
            let split = number_string.split(',');
            let sisa = split[0].length % 3;
            let rupiah = split[0].substr(0, sisa);
            let ribuan = split[0].substr(sisa).match(/\d{3}/gi);
            if (ribuan) {
                let separator = sisa ? '.' : '';
                rupiah += separator + ribuan.join('.');
            }
            rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
            return prefix == undefined ? rupiah : (rupiah ? 'Rp. ' + rupiah : '');
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
            const formEl = this.$refs.form; // Ambil form element
            this.editingIndex = i;
            
            // ▼▼▼ PERUBAHAN: 'showForm' diganti menjadi 'isModalOpen' ▼▼▼
            this.isModalOpen = true;
            // ▲▲▲ SELESAI PERUBAHAN ▲▲▲
            
            // Populate form
            this.$nextTick(() => {
              if (this.$refs.nama_produk) this.$refs.nama_produk.value = e.nama_produk || '';
              if (this.$refs.keterangan) this.$refs.keterangan.value = e.keterangan || '';
              if (this.$refs.stok_kg) this.$refs.stok_kg.value = e.stok_kg || 0;
              if (this.$refs.harga_kg) this.$refs.harga_kg.value = this.formatRupiah(e.harga_kg.toString(), 'Rp. ');
              
              // --- PERBAIKAN 6: Set nilai dropdown saat edit ---
              if (formEl.kategori_id) {
                  formEl.kategori_id.value = e.kategori_id || '';
              }
              // --- SELESAI PERBAIKAN 6 ---
            });
          },
          
          async save() {
            const formEl = this.$refs.form;
            const formData = new FormData(formEl);
            
            let hargaClean = this.$refs.harga_kg.value.replace(/[^0-9]/g, '');
            formData.set('harga_kg', hargaClean || 0);
            formData.set('keterangan', this.$refs.keterangan.value); 

            if (this.editingIndex === null) {
              formData.append('action', 'create');
            } else {
              formData.append('action', 'update');
              formData.append('id', this.entries[this.editingIndex].id);
            }

            try {
              const res = await fetch(window.location.pathname, {
                method: 'POST',
                body: formData,
              });
              const data = await res.json();
              if (data.success) {
                if (data.row) {
                  if (this.editingIndex === null) {
                    this.entries.unshift(data.row);
                    this.showMessage('success', 'Produk baru berhasil ditambahkan!');
                  } else {
                    this.entries.splice(this.editingIndex, 1, data.row);
                    this.showMessage('success', 'Produk berhasil diperbarui!');
                  }
                }
                this.resetForm();
              } else {
                this.showMessage('error', data.message || 'Gagal menyimpan data.');
              }
            } catch (err) {
              console.error(err);
              this.showMessage('error', 'Terjadi kesalahan jaringan.');
            }
          },

          async deleteEntry(i) {
            if (!confirm('Apakah Anda yakin ingin menghapus produk ini?')) return;
            const id = this.entries[i].id;
            const form = new FormData();
            form.append('action', 'delete');
            form.append('id', id);
            
            try {
              const res = await fetch(window.location.pathname, { method: 'POST', body: form });
              const data = await res.json();
              
              // Perbaikan untuk Soft Delete
              if (data.success && data.soft_deleted) { 
                this.entries.splice(i, 1);
                this.showMessage('success', 'Produk berhasil dinonaktifkan.');
              } else if (data.success) {
                 this.entries.splice(i, 1);
                 this.showMessage('success', 'Produk berhasil dihapus.');
              } else {
                this.showMessage('error', data.message || 'Gagal menghapus data.');
              }
            } catch (err) {
              console.error(err);
              this.showMessage('error', 'Terjadi kesalahan jaringan saat menghapus.');
            }
          },
          
          resetForm() {
            // ▼▼▼ PERUBAHAN: 'showForm' diganti menjadi 'isModalOpen' ▼▼▼
            this.isModalOpen = false;
            // ▲▲▲ SELESAI PERUBAHAN ▲▲▲
            
            this.editingIndex = null;
            if (this.$refs && this.$refs.form) this.$refs.form.reset();
          },
          
          openAdd() {
            // ▼▼▼ PERUBAHAN: 'showForm' diganti menjadi 'isModalOpen' ▼▼▼
            if (this.isModalOpen && this.editingIndex === null) {
                this.resetForm(); // Jika form tambah sudah terbuka, tutup
            } else {
                this.resetForm(); // Reset dulu (ini juga akan menutup modal jika sedang edit)
                this.isModalOpen = true; // Buka form
            }
            // ▲▲▲ SELESAI PERUBAHAN ▲▲▲
          }
        }
      }
    </script>
  </body>
</html>