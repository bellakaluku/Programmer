<?php
// 1. MEMULAI SESSION & KONEKSI
session_start();
// PERBAIKAN: Path dari User_petani/ naik 1 level ke windmill-dashboard/
require_once __DIR__ . '/../public/pages/Connection.php'; 

// 2. KEAMANAN: Cek jika user sudah login dan adalah 'petani'
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'petani') {
    // Path: naik 1 level, lalu masuk ke public/pages/
    header("Location: ../public/pages/Login.php");
    exit;
}

// Ambil ID Petani yang sedang login
$petani_id = (int) $_SESSION['user_id'];
$pesan_sukses = ""; // Pesan untuk non-AJAX
$pesan_error = ""; // Pesan untuk non-AJAX

// 3. PROSES AJAX (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    if (!$petani_id) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    $action = $_POST['action'];

    try {
        // --- Aksi Create atau Update ---
        if ($action === 'create' || $action === 'update') {
            // Ambil data form
            $id = (int)($_POST['id'] ?? 0);
            $nama_lahan = trim($_POST['nama_lahan'] ?? '');
            $luas = (float)($_POST['luas'] ?? 0);
            $satuan_luas = trim($_POST['satuan_luas'] ?? 'Hektar');
            $alamat_lokasi = trim($_POST['alamat_lokasi'] ?? '');
            $status_kepemilikan = trim($_POST['status_kepemilikan'] ?? 'Milik Sendiri');
            $tanggal_dibuat = trim($_POST['tanggal_dibuat'] ?? date('Y-m-d'));
            
            // Validasi sederhana
            if (empty($nama_lahan) || empty($luas) || empty($alamat_lokasi)) {
                 throw new Exception("Nama, Luas, dan Alamat wajib diisi.");
            }

            if ($action === 'create') {
                $sql = "INSERT INTO manajemen_lahan (petani_id, nama_lahan, luas, satuan_luas, alamat_lokasi, status_kepemilikan, tanggal_dibuat) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('isdssss', $petani_id, $nama_lahan, $luas, $satuan_luas, $alamat_lokasi, $status_kepemilikan, $tanggal_dibuat);
                $ok = $stmt->execute();
                $newId = $conn->insert_id;
                $stmt->close();
            } else { // $action === 'update'
                $sql = "UPDATE manajemen_lahan SET nama_lahan = ?, luas = ?, satuan_luas = ?, alamat_lokasi = ?, status_kepemilikan = ?, tanggal_dibuat = ? WHERE id = ? AND petani_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('sdssssii', $nama_lahan, $luas, $satuan_luas, $alamat_lokasi, $status_kepemilikan, $tanggal_dibuat, $id, $petani_id);
                $ok = $stmt->execute();
                $newId = $id; // Gunakan ID yang sama untuk fetch
                $stmt->close();
            }

            if ($ok) {
                // Ambil data yang baru disimpan/diupdate (termasuk join nama petani)
                $query_fetch = "SELECT k.*, u.nama AS nama_petani 
                                FROM manajemen_lahan k
                                JOIN users u ON k.petani_id = u.id
                                WHERE k.id = ? AND k.petani_id = ?";
                $stmt_fetch = $conn->prepare($query_fetch);
                $stmt_fetch->bind_param("ii", $newId, $petani_id);
                $stmt_fetch->execute();
                $row = $stmt_fetch->get_result()->fetch_assoc();
                $stmt_fetch->close();
                echo json_encode(['success' => true, 'row' => $row, 'message' => 'Data berhasil disimpan!']);
            } else {
                throw new Exception('Gagal menyimpan data ke database.');
            }
        
        // --- Aksi Delete ---
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id === 0) {
                 throw new Exception("ID Lahan tidak valid.");
            }
            $stmt = $conn->prepare("DELETE FROM manajemen_lahan WHERE id = ? AND petani_id = ?");
            $stmt->bind_param('ii', $id, $petani_id);
            $ok = $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => (bool)$ok, 'message' => $ok ? 'Lahan berhasil dihapus.' : 'Gagal menghapus data.']);
        
        } else {
             throw new Exception("Aksi tidak diketahui.");
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit; // Wajib exit setelah merespon AJAX
}

// 4. AMBIL DATA LAHAN HANYA UNTUK PETANI INI (GET Request)
$data_lahan = [];
if (isset($conn) && $conn instanceof mysqli) {
    
    // Menggunakan tabel "manajemen_lahan"
    // Menghapus kolom "jenis_tanaman"
    // Menambahkan "WHERE k.petani_id = ?"
    $sql = "SELECT 
                k.id, 
                k.nama_lahan, 
                k.luas, 
                k.satuan_luas, 
                k.alamat_lokasi, 
                k.status_kepemilikan, 
                k.tanggal_dibuat,
                u.nama AS nama_petani 
            FROM manajemen_lahan k
            JOIN users u ON k.petani_id = u.id
            WHERE k.petani_id = ? 
            ORDER BY k.tanggal_dibuat DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $petani_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
         while($row = $result->fetch_assoc()) {
            $data_lahan[] = $row;
         }
    } else {
        die("Error pada Query SQL: " . $conn->error);
    }
    $stmt->close();
    
} else {
    die("Koneksi database gagal. Periksa pengaturan file Connection.php.");
}
$conn->close();
?>
<!DOCTYPE html>
<!-- Hapus x-data="data()" dari sini -->
<html :class="{ 'theme-dark': dark }" x-data="pageData()" lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manajemen Lahan - Windmill Dashboard</title>
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="./assets/css/tailwind.output.css" />
    <script
      src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js"
      defer
    ></script>
    <!-- Kita tidak pakai init-alpine.js, kita gabung di bawah -->
  </head>
  <!-- Pindahkan x-data="pageData()" ke tag body -->
  <body>
    <div
      class="flex h-screen bg-gray-50 dark:bg-gray-900"
      :class="{ 'overflow-hidden': isSideMenuOpen }"
    >
      <!-- Desktop sidebar -->
        <aside
                class="z-20 hidden w-64 overflow-y-auto bg-white dark:bg-gray-800 md:block flex-shrink-0"
            >
                <div class="py-4 text-gray-500 dark:text-gray-400">
                    <!-- PERUBAHAN: Logo dan Nama Website -->
                    <a
                        class="ml-6 text-lg font-bold text-gray-800 dark:text-gray-200 flex items-center"
                        href="DasboardPetani.php"
                    >
                        <svg class="w-6 h-6 mr-2 text-green-600" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M4 14V8a2 2 0 012-2h12a2 2 0 012 2v6m-4 0v4m-8-4v4m0-8h8m-8 0V6"></path>
                        </svg>
                        <span>TaniMaju</span>
                    </a>
                    <!-- AKHIR PERUBAHAN -->
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
                        <!-- PERBAIKAN: Menjadikan link ini Aktif -->
                       
                        
                        <!-- PERBAIKAN: Memindahkan Manajemen Lahan ke bawah Data Lahan -->
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
                                    <!-- Ikon Profesional (Grid Lahan) -->
                                    <path d="M4 14V8a2 2 0 012-2h12a2 2 0 012 2v6m-4 0v4m-8-4v4m0-8h8m-8 0V6"></path>
                                </svg>
                                <span class="ml-4">Kelola Lahan</span>
                            </a>
                        </li>

                         <li class="relative px-6 py-3">
                             <!-- PERUBAHAN: Tema dari purple ke green -->
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
                                    <!-- IKON BARU: Catatan Keuangan (Dolar) -->
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
                    <!-- PERUBAHAN: Logo dan Nama Website (Mobile) -->
                    <a
                        class="ml-6 text-lg font-bold text-gray-800 dark:text-gray-200 flex items-center"
                        href="#"
                    >
                        <svg class="w-6 h-6 mr-2 text-green-600" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M4 14V8a2 2 0 012-2h12a2 2 0 012 2v6m-4 0v4m-8-4v4m0-8h8m-8 0V6"></path>
                        </svg>
                        <span>TaniMaju</span>
                    </a>
                    <!-- AKHIR PERUBAHAN -->
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
                            <!-- PERUBAHAN: Tema dari purple ke green (Mobile) -->
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
                                    <!-- IKON BARU: Catatan Keuangan (Dolar) -->
                                    <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0c-1.657 0-3-.895-3-2s1.343-2 3-2 3-.895 3-2 1.343-2 3-2m0 8c1.11 0 2.08-.402 2.599-1M12 16V7m0 9v-1m0-8V7m0 0h.01M6 12h.01M6 12h.01M6 12h.01M18 12h.01M18 12h.01M18 12h.01M6 9h.01M6 15h.01M18 9h.01M18 15h.01"></path>
                                </svg>
                                <span class="ml-4">Catatan Keuangan</span>
                            </a>
                        </li>      
                    </ul>
                </div>
            </aside>
      
      <!-- Mobile sidebar -->
      <!-- ... (Kode mobile sidebar Anda) ... -->
      
      <div class="flex flex-col flex-1 w-full">
         <header class="z-10 py-4 bg-white shadow-md dark:bg-gray-800">
                    <!-- PERUBAHAN: Tema Header dari purple ke green -->
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
        <main class="h-full overflow-y-auto">
          <!-- PERBAIKAN: Hapus x-data dari sini -->
          <div class="container px-6 mx-auto grid">
            <h2
              class="my-6 text-2xl font-semibold text-gray-700 dark:text-gray-200"
            >
              Data Lahan Petani
            </h2>

            <!-- Pesan Notifikasi (untuk AJAX) -->
            <div 
                x-show="pesanNotifikasi.teks" 
                x-transition
                :class="{ 'bg-green-600': pesanNotifikasi.tipe === 'success', 'bg-red-600': pesanNotifikasi.tipe === 'error' }"
                class="px-4 py-3 mb-4 text-sm font-medium text-white rounded-lg" 
                role="alert"
                x-text="pesanNotifikasi.teks"
                style="display: none;">
            </div>
            
            <div class="mb-4">
              <!-- PERBAIKAN: Tombol ini sekarang membuka modal -->
              <button
                @click="bukaModalTambah()"
                class="flex items-center justify-between px-4 py-2 text-sm font-semibold leading-5 text-white transition-colors duration-150 bg-purple-600 border border-transparent rounded-lg active:bg-purple-600 hover:bg-purple-700 focus:outline-none focus:shadow-outline-purple"
              >
                <svg
                  class="w-4 h-4 mr-2"
                  fill="currentColor"
                  viewBox="0 0 20 20"
                  aria-hidden="true"
                >
                  <path
                    fill-rule="evenodd"
                    d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"
                    clip-rule="evenodd"
                  ></path>
                </svg>
                <span>Tambah Lahan</span>
              </button>
            </div>

            <!-- Tabel Data Lahan -->
            <div class="w-full overflow-hidden rounded-lg shadow-xs">
              <div class="w-full overflow-x-auto">
                <table class="w-full whitespace-no-wrap">
                  <thead>
                    <tr
                      class="text-xs font-semibold tracking-wide text-left text-gray-500 uppercase border-b dark:border-gray-700 bg-gray-50 dark:text-gray-400 dark:bg-gray-800"
                    >
                      <th class="px-4 py-3">No</th>
                      <th class="px-4 py-3">Nama Lahan/Kebun</th>
                      <th class="px-4 py-3">Luas</th>
                      <th class="px-4 py-3">Alamat</th>
                      <th class="px-4 py-3">Status Kepemilikan</th>
                      <th class="px-4 py-3">Tanggal Dibuat</th>
                      <th class="px-4 py-3">Aksi</th>
                    </tr>
                  </thead>
                  <tbody
                    class="bg-white divide-y dark:divide-gray-700 dark:bg-gray-800"
                  >
                    <!-- PERBAIKAN: Gunakan template Alpine.js untuk loop data -->
                    <template x-if="lahanList.length === 0">
                        <tr class="text-gray-700 dark:text-gray-400">
                            <td class="px-4 py-3 text-center" colspan="7">Anda belum menambahkan data lahan.</td>
                        </tr>
                    </template>
                    
                    <template x-for="(lahan, index) in lahanList" :key="lahan.id">
                        <!-- PERBAIKAN: Tambahkan class dark:text-gray-400 -->
                        <tr class="text-gray-700 dark:text-gray-400">
                            <td class="px-4 py-3" x-text="index + 1"></td>
                            <td class="px-4 py-3" x-text="lahan.nama_lahan"></td>
                            <td class="px-4 py-3" x-text="lahan.luas + ' ' + lahan.satuan_luas"></td>
                            <td class="px-4 py-3 max-w-xs overflow-hidden truncate" x-text="lahan.alamat_lokasi"></td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 font-semibold leading-tight rounded-full text-xs"
                                      :class="{
                                          'bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-100': lahan.status_kepemilikan == 'Milik Sendiri',
                                          'bg-yellow-100 text-yellow-800 dark:bg-yellow-700 dark:text-yellow-100': lahan.status_kepemilikan == 'Sewa'
                                      }"
                                      x-text="lahan.status_kepemilikan">
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm" x-text="new Date(lahan.tanggal_dibuat).toLocaleDateString('id-ID')"></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center space-x-4 text-sm">
                                    <button @click="bukaModalEdit(lahan)" class="flex items-center justify-between px-2 py-2 text-sm font-medium leading-5 text-purple-600 rounded-lg dark:text-gray-400 focus:outline-none focus:shadow-outline-gray" aria-label="Edit">
                                        <svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zm-7.536 7.536l3.89 3.89L2.83 17.5 5.5 14.83l4.036-4.036z"></path></svg>
                                    </button>
                                    <button @click="hapusLahan(lahan.id, index)" class="flex items-center justify-between px-2 py-2 text-sm font-medium leading-5 text-red-600 rounded-lg dark:text-gray-400 focus:outline-none focus:shadow-outline-gray" aria-label="Delete">
                                        <svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm6 0a1 1 0 10-2 0v6a1 1 0 102 0V8z" clip-rule="evenodd"></path></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          
            <!-- Modal Tambah/Edit Lahan -->
            <div
                x-show="isModalOpen"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 z-30 flex items-end bg-black bg-opacity-50 sm:items-center sm:justify-center"
                style="display: none;"
            >
                <!-- Modal -->
                <div
                    x-show="isModalOpen"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 transform translate-y-1/2"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0 transform translate-y-1/2"
                    @click.away="tutupModal"
                    @keydown.escape.window="tutupModal"
                    class="w-full px-6 py-4 overflow-hidden bg-white rounded-t-lg dark:bg-gray-800 sm:rounded-lg sm:m-4 sm:max-w-xl"
                    role="dialog"
                    id="modal-lahan"
                >
                    <header class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200" x-text="isEditing ? 'Edit Lahan' : 'Tambah Lahan Baru'"></h3>
                        <button class="inline-flex items-center justify-center w-6 h-6 text-gray-400 transition-colors duration-150 rounded dark:hover:text-gray-200 hover:text-gray-700" aria-label="Close" @click="tutupModal">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20" role="img" aria-hidden="true"><path d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" fill-rule="evenodd"></path></svg>
                        </button>
                    </header>
                    <div class="mt-4 mb-6">
                        <form @submit.prevent="simpanLahan">
                            <label class="block text-sm">
                                <span class="text-gray-700 dark:text-gray-400">Nama Lahan</span>
                                <input x-model="formLahan.nama_lahan" name="nama_lahan" class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300 form-input" required />
                            </label>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                <label class="block text-sm">
                                    <span class="text-gray-700 dark:text-gray-400">Luas</span>
                                    <input x-model="formLahan.luas" name="luas" type="number" step="0.01" class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300 form-input" required />
                                </label>
                                <label class="block text-sm">
                                    <span class="text-gray-700 dark:text-gray-400">Satuan Luas</span>
                                    <select x-model="formLahan.satuan_luas" name="satuan_luas" class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300 form-select">
                                        <option value="Hektar">Hektar</option>
                                        <option value="m²">m²</option>
                                    </select>
                                </label>
                            </div>

                            <label class="block mt-4 text-sm">
                                <span class="text-gray-700 dark:text-gray-400">Alamat Lokasi</span>
                                <textarea x-model="formLahan.alamat_lokasi" name="alamat_lokasi" class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300 form-textarea" rows="3" required></textarea>
                            </label>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                <label class="block text-sm">
                                    <span class="text-gray-700 dark:text-gray-400">Status Kepemilikan</span>
                                    <select x-model="formLahan.status_kepemilikan" name="status_kepemilikan" class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300 form-select">
                                        <option value="Milik Sendiri">Milik Sendiri</option>
                                        <option value="Sewa">Sewa</option>
                                    </select>
                                </label>
                                <label class="block text-sm">
                                    <span class="text-gray-700 dark:text-gray-400">Tanggal Dibuat/Mulai</span>
                                    <input x-model="formLahan.tanggal_dibuat" name="tanggal_dibuat" type="date" class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300 form-input" required />
                                </label>
                            </div>
                            
                            <footer class="flex items-center justify-end px-6 py-3 -mx-6 -mb-4 space-x-4 sm:px-6 bg-gray-50 dark:bg-gray-800 mt-6">
                                <!-- PERBAIKAN: Tambahkan kelas dark mode pada tombol Batal -->
                                <button @click="tutupModal" type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 focus:outline-none dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                                    Batal
                                </button>
                                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-md hover:bg-purple-700 focus:outline-none">
                                    Simpan Lahan
                                </button>
                            </footer>
                        </form>
                    </div>
                </div>
            </div>
            <!-- Akhir Modal -->
        </main>
      </div>
    </div>
    
    <!-- PERBAIKAN: Menggabungkan fungsi data() dan dataLahan() -->
    <script>
      function pageData() {
        // --- Bagian 1: Logika Sidebar/Header (dari data()) ---
        function getThemeFromLocalStorage() {
          if (window.localStorage.getItem('dark')) {
            return JSON.parse(window.localStorage.getItem('dark'))
          }
          return (
            !!window.matchMedia &&
            window.matchMedia('(prefers-color-scheme: dark)').matches
          )
        }
        function setThemeToLocalStorage(value) {
          window.localStorage.setItem('dark', value)
        }
        
        // --- Bagian 2: Logika Halaman Lahan ---
        const lahanListPHP = <?php echo json_encode($data_lahan); ?>;
        const formLahanDefault = {
            id: null,
            nama_lahan: '',
            luas: 0,
            satuan_luas: 'Hektar',
            alamat_lokasi: '',
            status_kepemilikan: 'Milik Sendiri',
            tanggal_dibuat: new Date().toISOString().split('T')[0]
        };

        // --- Bagian 3: Mengembalikan Objek Gabungan ---
        return {
          // Properti dari data()
          dark: getThemeFromLocalStorage(),
          isSideMenuOpen: false,
          isNotificationsMenuOpen: false,
          isProfileMenuOpen: false,
          
          // Properti dari dataLahan()
          lahanList: lahanListPHP,
          isModalOpen: false,
          isEditing: false,
          pesanNotifikasi: { teks: '', tipe: 'success' },
          formLahan: { ...formLahanDefault },

          // Metode dari data()
          toggleTheme() {
            this.dark = !this.dark;
            setThemeToLocalStorage(this.dark);
          },
          toggleSideMenu() { this.isSideMenuOpen = !this.isSideMenuOpen; },
          closeSideMenu() { this.isSideMenuOpen = false; },
          toggleNotificationsMenu() { this.isNotificationsMenuOpen = !this.isNotificationsMenuOpen; },
          closeNotificationsMenu() { this.isNotificationsMenuOpen = false; },
          toggleProfileMenu() { this.isProfileMenuOpen = !this.isProfileMenuOpen; },
          closeProfileMenu() { this.isProfileMenuOpen = false; },

          // Metode dari dataLahan()
          tampilNotifikasi(pesan, tipe) {
              this.pesanNotifikasi.teks = pesan;
              this.pesanNotifikasi.tipe = tipe;
              setTimeout(() => {
                  this.pesanNotifikasi.teks = '';
              }, 3000);
          },
          resetFormLahan() {
            this.formLahan = { ...formLahanDefault };
            this.formLahan.tanggal_dibuat = new Date().toISOString().split('T')[0];
          },
          bukaModalTambah() {
            this.resetFormLahan();
            this.isEditing = false;
            this.isModalOpen = true;
          },
          bukaModalEdit(lahan) {
            // Salin data lahan ke form
            this.formLahan = Object.assign({}, lahan);
            this.isEditing = true;
            this.isModalOpen = true;
          },
          tutupModal() {
            this.isModalOpen = false;
            this.isEditing = false;
          },
          async simpanLahan() {
            const formData = new FormData();
            formData.append('action', this.isEditing ? 'update' : 'create');
            
            // Tambahkan semua data dari formLahan ke formData
            for (const key in this.formLahan) {
                // Jangan kirim data yang di-join (nama_petani) atau 'id' jika 'create'
                if (key !== 'nama_petani' && key !== 'id') {
                    formData.append(key, this.formLahan[key]);
                }
            }
            // Kirim 'id' hanya saat update
            if (this.isEditing) {
                formData.append('id', this.formLahan.id);
            }
            
            try {
                const response = await fetch('kebun.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    if (this.isEditing) {
                        // Update: cari dan ganti item di list
                        const index = this.lahanList.findIndex(l => l.id == this.formLahan.id);
                        if (index !== -1) {
                            this.lahanList.splice(index, 1, data.row);
                        }
                    } else {
                        // Tambah: tambahkan item baru di awal list
                        this.lahanList.unshift(data.row);
                    }
                    this.tampilNotifikasi(data.message, 'success');
                    this.tutupModal();
                } else {
                    this.tampilNotifikasi(data.message || 'Gagal menyimpan lahan.', 'error');
                }
            } catch (err) {
                console.error(err);
                this.tampilNotifikasi('Terjadi kesalahan jaringan.', 'error');
            }
          },
          async hapusLahan(id, index) {
            if (!confirm('Apakah Anda yakin ingin menghapus lahan ini? Semua produk terkait mungkin akan terpengaruh.')) return;
            
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);
            
            try {
                const response = await fetch('kebun.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    this.lahanList.splice(index, 1);
                    this.tampilNotifikasi(data.message, 'success');
                } else {
                    this.tampilNotifikasi(data.message || 'Gagal menghapus lahan.', 'error');
                }
            } catch (err) {
                 console.error(err);
                this.tampilNotifikasi('Terjadi kesalahan jaringan.', 'error');
            }
          }
        }
      }
    </script>
  </body>
</html>