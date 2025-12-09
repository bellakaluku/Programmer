<?php
// --- DEBUGGING ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. MEMULAI SESSION & KONEKSI
session_start();
require_once __DIR__ . '/../public/pages/Connection.php'; 

// 2. KEAMANAN
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'petani') {
    header("Location: ../public/pages/Login.php");
    exit;
}
$petani_id = (int) $_SESSION['user_id'];

// 3. AMBIL DATA (TAMPILAN WEB TETAP MENAMPILKAN SEMUA/TERBARU)
$pemasukan_list = [];
$pengeluaran_list = [];

if ($petani_id) {
    // --- A. PEMASUKAN (Join Alamat & Produk) ---
    $sql_sales = "SELECT 
                    p.id, 
                    p.total_harga, 
                    p.tanggal_pesan, 
                    p.status_pesanan,
                    a.nama_penerima,
                    CONCAT(a.alamat_lengkap, ', ', a.kota) AS alamat_lengkap,
                    GROUP_CONCAT(pr.nama_produk, ' (', dp.jumlah_kg, ' kg)' SEPARATOR '<br>') AS produk_list
                  FROM pesanan p
                  LEFT JOIN alamat a ON p.alamat_id = a.id
                  LEFT JOIN detail_pesanan dp ON p.id = dp.pesanan_id
                  LEFT JOIN produk pr ON dp.produk_id = pr.id
                  WHERE p.petani_id = ? 
                  AND (p.status_pesanan = 'Dikirim' OR p.status_pesanan = 'Selesai')
                  GROUP BY p.id
                  ORDER BY p.tanggal_pesan DESC LIMIT 50"; // Limit 50 biar loading gak berat di preview
    
    $stmt_sales = mysqli_prepare($conn, $sql_sales);
    if ($stmt_sales) {
        mysqli_stmt_bind_param($stmt_sales, 'i', $petani_id);
        mysqli_stmt_execute($stmt_sales);
        $res_sales = mysqli_stmt_get_result($stmt_sales);
        if ($res_sales) {
            while ($row = $res_sales->fetch_assoc()) {
                $pemasukan_list[] = $row;
            }
        }
        mysqli_stmt_close($stmt_sales);
    }

    // --- B. PENGELUARAN (Tabel Lahan + Tanggal Panen) ---
    $sql_expenses = "SELECT 
                        id, 
                        biaya_modal, 
                        mulai_tanam, 
                        tanggal_panen, 
                        nama_lahan, 
                        jenis_tanaman
                    FROM lahan 
                    WHERE petani_id = ? AND biaya_modal > 0
                    ORDER BY mulai_tanam DESC";
    
    $stmt_expenses = mysqli_prepare($conn, $sql_expenses);
    if ($stmt_expenses) {
        mysqli_stmt_bind_param($stmt_expenses, 'i', $petani_id);
        mysqli_stmt_execute($stmt_expenses);
        $res_expenses = mysqli_stmt_get_result($stmt_expenses);
        if ($res_expenses) {
            while ($row = $res_expenses->fetch_assoc()) {
                $pengeluaran_list[] = $row;
            }
        }
        mysqli_stmt_close($stmt_expenses);
    }
}
?>
<!DOCTYPE html>
<html :class="{ 'theme-dark': dark }" x-data="pageData()" lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Laporan Keuangan - TaniMaju</title>
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="./assets/css/tailwind.output.css" />
    <script
      src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js"
      defer
    ></script>
    <!-- Chart JS jika diperlukan nanti -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.3/Chart.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.3/Chart.min.js" defer></script>
  </head>
  <body>
    <div class="flex h-screen bg-gray-50 dark:bg-gray-900" :class="{ 'overflow-hidden': isSideMenuOpen }">
      
      <!-- Desktop Sidebar -->
      <aside class="z-20 hidden w-64 overflow-y-auto bg-white dark:bg-gray-800 md:block flex-shrink-0">
        <div class="py-4 text-gray-500 dark:text-gray-400">
          <a class="ml-6 text-lg font-bold text-gray-800 dark:text-gray-200 flex items-center" href="DasboardPetani.php">
            <svg class="w-6 h-6 mr-2 text-green-600" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24">
                <path d="M4 14V8a2 2 0 012-2h12a2 2 0 012 2v6m-4 0v4m-8-4v4m0-8h8m-8 0V6"></path>
            </svg>
            <span>TaniMaju</span>
          </a>
          <!-- Menu Items -->
          <ul class="mt-6">
            <li class="relative px-6 py-3"><a class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200" href="DasboardPetani.php">
                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                <span class="ml-4">Dashboard</span></a>
            </li>
            <li class="relative px-6 py-3"><a class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200" href="kebun.php">
                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor"><path d="M4 14V8a2 2 0 012-2h12a2 2 0 012 2v6m-4 0v4m-8-4v4m0-8h8m-8 0V6"></path></svg>
                <span class="ml-4">Kelola Lahan</span></a>
            </li>
            <li class="relative px-6 py-3"><a class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200" href="DataLahan.php">
                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                <span class="ml-4">Data Penanaman</span></a>
            </li>
            <li class="relative px-6 py-3"><a class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200" href="KelolaProduk.php">
                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor"><path d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"></path><path d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"></path></svg>
                <span class="ml-4">Kelola Produk</span></a>
            </li>
            <li class="relative px-6 py-3"><a class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200" href="PesananMasuk.php">
                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor"><path d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                <span class="ml-4">Pesanan Masuk</span></a>
            </li>
            <li class="relative px-6 py-3">
                <span class="absolute inset-y-0 left-0 w-1 bg-green-600 rounded-tr-lg rounded-br-lg" aria-hidden="true"></span>
                <a class="inline-flex items-center w-full text-sm font-semibold text-gray-800 transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200 dark:text-gray-100" href="CatatanKeuangan.php">
                    <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0c-1.657 0-3-.895-3-2s1.343-2 3-2 3-.895 3-2 1.343-2 3-2m0 8c1.11 0 2.08-.402 2.599-1M12 16V7m0 9v-1m0-8V7m0 0h.01M6 12h.01M6 12h.01M6 12h.01M18 12h.01M18 12h.01M18 12h.01M6 9h.01M6 15h.01M18 9h.01M18 15h.01"></path></svg>
                    <span class="ml-4">Catatan Keuangan</span>
                </a>
            </li>       
          </ul>
        </div>
      </aside>

      <!-- Mobile Sidebar -->
      <div x-show="isSideMenuOpen" x-transition:enter="transition ease-in-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in-out duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-10 flex items-end bg-black bg-opacity-50 sm:items-center sm:justify-center"></div>
      <aside class="fixed inset-y-0 z-20 flex-shrink-0 w-64 mt-16 overflow-y-auto bg-white dark:bg-gray-800 md:hidden" x-show="isSideMenuOpen" x-transition:enter="transition ease-in-out duration-150" x-transition:enter-start="opacity-0 transform -translate-x-20" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in-out duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 transform -translate-x-20" @click.away="closeSideMenu" @keydown.escape="closeSideMenu">
        <div class="py-4 text-gray-500 dark:text-gray-400">
            <a class="ml-6 text-lg font-bold text-gray-800 dark:text-gray-200 flex items-center" href="#">
                <svg class="w-6 h-6 mr-2 text-green-600" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M4 14V8a2 2 0 012-2h12a2 2 0 012 2v6m-4 0v4m-8-4v4m0-8h8m-8 0V6"></path>
                </svg>
                <span>TaniMaju</span>
            </a>
            <ul class="mt-6">
                <li class="relative px-6 py-3"><a class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200" href="DasboardPetani.php"><span class="ml-4">Dashboard</span></a></li>
                <li class="relative px-6 py-3"><a class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200" href="kebun.php"><span class="ml-4">Kelola Lahan</span></a></li>
                <li class="relative px-6 py-3"><a class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200" href="DataLahan.php"><span class="ml-4">Data Penanaman</span></a></li>
                <li class="relative px-6 py-3"><a class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200" href="KelolaProduk.php"><span class="ml-4">Kelola Produk</span></a></li>
                <li class="relative px-6 py-3"><a class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200" href="PesananMasuk.php"><span class="ml-4">Pesanan Masuk</span></a></li>
                <li class="relative px-6 py-3">
                    <span class="absolute inset-y-0 left-0 w-1 bg-purple-600 rounded-tr-lg rounded-br-lg" aria-hidden="true"></span>
                    <a class="inline-flex items-center w-full text-sm font-semibold text-gray-800 transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200 dark:text-gray-100" href="CatatanKeuangan.php"><span class="ml-4">Catatan Keuangan</span></a>
                </li> 
            </ul>
        </div>
      </aside>

      <div class="flex flex-col flex-1 w-full">
        <!-- Header -->
        <header class="z-10 py-4 bg-white shadow-md dark:bg-gray-800">
            <div class="container flex items-center justify-between h-full px-6 mx-auto text-green-600 dark:text-green-300">
                <button class="p-1 mr-5 -ml-1 rounded-md md:hidden focus:outline-none focus:shadow-outline-green" @click="toggleSideMenu" aria-label="Menu">
                    <svg class="w-6 h-6" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path></svg>
                </button>
                
                <!-- Search input placeholder -->
                 <div class="flex justify-center flex-1 lg:mr-32">
                    <div class="relative w-full max-w-xl mr-6 focus-within:text-green-500">
                        <input class="w-full pl-8 pr-2 text-sm text-gray-700 placeholder-gray-600 bg-gray-100 border-0 rounded-md dark:placeholder-gray-500 dark:focus:shadow-outline-gray dark:focus:placeholder-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:placeholder-gray-500 focus:bg-white focus:border-green-300 focus:outline-none focus:shadow-outline-green form-input" type="text" placeholder="Laporan Keuangan" aria-label="Search" disabled />
                    </div>
                </div>

                <ul class="flex items-center flex-shrink-0 space-x-6">
                    <li class="flex">
                        <button class="rounded-md focus:outline-none focus:shadow-outline-green" @click="toggleTheme" aria-label="Toggle color mode">
                            <template x-if="!dark"><svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg></template>
                            <template x-if="dark"><svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"></path></svg></template>
                        </button>
                    </li>
                    <li class="relative">
                        <button class="align-middle rounded-full focus:shadow-outline-green focus:outline-none" @click="toggleProfileMenu" @keydown.escape="closeProfileMenu" aria-label="Account" aria-haspopup="true">
                            <img class="object-cover w-8 h-8 rounded-full" src="https://images.unsplash.com/photo-1502378735452-bc7d86632805?ixlib=rb-0.3.5&q=80&fm=jpg&crop=entropy&cs=tinysrgb&w=200&fit=max&s=aa3a807e1bbdfd4364d1f449eaa96d82" alt="" aria-hidden="true" />
                        </button>
                        <template x-if="isProfileMenuOpen">
                            <ul x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" @click.away="closeProfileMenu" @keydown.escape="closeProfileMenu" class="absolute right-0 w-56 p-2 mt-2 space-y-2 text-gray-600 bg-white border border-gray-100 rounded-md shadow-md dark:border-gray-700 dark:text-gray-300 dark:bg-gray-700" aria-label="submenu">
                                <li class="flex"><a class="inline-flex items-center w-full px-2 py-1 text-sm font-semibold transition-colors duration-150 rounded-md hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-800 dark:hover:text-gray-200" href="profil.php"><span>Profile</span></a></li>
                                <li class="flex"><a class="inline-flex items-center w-full px-2 py-1 text-sm font-semibold transition-colors duration-150 rounded-md hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-800 dark:hover:text-gray-200" href="../public/pages/LogOut.php"><span>Log out</span></a></li>
                            </ul>
                        </template>
                    </li>
                </ul>
            </div>
        </header>

        <main class="h-full pb-16 overflow-y-auto">
            <div class="container px-6 mx-auto grid">
                <h2 class="my-6 text-2xl font-semibold text-gray-700 dark:text-gray-200">
                    Laporan Keuangan
                </h2>

                <!-- KONTROL UTAMA (DROPDOWN & BUTTONS) -->
                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-end mb-6 gap-4">
                    
                    <div class="w-full lg:w-2/3 flex flex-col sm:flex-row gap-4">
                        <!-- 1. Pilih Jenis Laporan -->
                        <div class="w-full sm:w-1/2">
                            <label class="block text-sm">
                                <span class="text-gray-700 dark:text-gray-400 font-semibold">Jenis Laporan</span>
                                <select 
                                    x-model="activeTab" 
                                    class="block w-full mt-1 text-sm dark:text-gray-300 dark:border-gray-600 dark:bg-gray-700 form-select focus:border-purple-400 focus:outline-none focus:shadow-outline-purple p-2 rounded-md border-gray-300 shadow-sm"
                                >
                                    <option value="pemasukan">Laporan Pemasukan (Penjualan)</option>
                                    <option value="pengeluaran">Laporan Pengeluaran (Biaya Modal)</option>
                                </select>
                            </label>
                        </div>

                        <!-- 2. Pilih Tahun (BARU) -->
                        <div class="w-full sm:w-1/2">
                            <label class="block text-sm">
                                <span class="text-gray-700 dark:text-gray-400 font-semibold">Tahun Laporan</span>
                                <select 
                                    x-model="selectedYear"
                                    class="block w-full mt-1 text-sm dark:text-gray-300 dark:border-gray-600 dark:bg-gray-700 form-select focus:border-purple-400 focus:outline-none focus:shadow-outline-purple p-2 rounded-md border-gray-300 shadow-sm"
                                >
                                    <template x-for="year in yearOptions" :key="year">
                                        <option :value="year" x-text="year"></option>
                                    </template>
                                </select>
                            </label>
                        </div>
                    </div>

                    <!-- 3. Tombol Cetak -->
                    <div class="w-full lg:w-auto">
                        <button 
                            @click="printPDF()"
                            class="flex items-center justify-center w-full lg:w-auto px-4 py-2 text-sm font-medium text-white transition-colors duration-150 bg-purple-600 border border-transparent rounded-lg active:bg-purple-600 hover:bg-purple-700 focus:outline-none focus:shadow-outline-purple shadow-md h-10"
                        >
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm7-8V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                            <span>Cetak PDF</span>
                        </button>
                    </div>
                </div>

                <!-- (Konten Tabel Desktop & Mobile tetap sama seperti sebelumnya...) -->
                <!-- ========================================== -->
                <!-- 1. TAMPILAN DESKTOP (TABEL) - Hidden on Mobile -->
                <!-- ========================================== -->
                <div class="hidden md:block">
                    <!-- TABEL 1: PEMASUKAN -->
                    <div x-show="activeTab === 'pemasukan'" x-transition.opacity>
                        <div class="w-full overflow-hidden rounded-lg shadow-xs">
                            <div class="w-full overflow-x-auto">
                                <table class="w-full whitespace-no-wrap">
                                    <thead>
                                        <tr class="text-xs font-semibold tracking-wide text-left text-gray-500 uppercase border-b dark:border-gray-700 bg-gray-50 dark:text-gray-400 dark:bg-gray-800">
                                            <th class="px-4 py-3">Tanggal</th>
                                            <th class="px-4 py-3">Pembeli & Alamat</th>
                                            <th class="px-4 py-3">Produk Dibeli</th>
                                            <th class="px-4 py-3">Total</th>
                                            <th class="px-4 py-3">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y dark:divide-gray-700 dark:bg-gray-800">
                                        <template x-if="pemasukanList.length === 0">
                                            <tr><td colspan="5" class="px-4 py-3 text-center text-gray-500">Belum ada data pemasukan.</td></tr>
                                        </template>
                                        <template x-for="row in pemasukanList" :key="'in-' + row.id">
                                            <tr class="text-gray-700 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="px-4 py-3 align-top text-sm" x-text="formatDate(row.tanggal_pesan)"></td>
                                                <td class="px-4 py-3 align-top">
                                                    <p class="font-semibold text-sm" x-text="row.nama_penerima"></p>
                                                    <p class="text-xs text-gray-500" x-text="row.alamat_lengkap"></p>
                                                </td>
                                                <td class="px-4 py-3 align-top">
                                                    <div class="text-sm" x-html="row.produk_list"></div>
                                                </td>
                                                <td class="px-4 py-3 font-bold text-green-600 align-top text-sm" x-text="formatPrice(row.total_harga)"></td>
                                                <td class="px-4 py-3 align-top">
                                                    <span class="px-2 py-1 text-xs font-semibold text-green-700 bg-green-100 rounded-full dark:bg-green-700 dark:text-green-100" x-text="row.status_pesanan"></span>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- TABEL 2: PENGELUARAN -->
                    <div x-show="activeTab === 'pengeluaran'" x-transition.opacity style="display: none;">
                        <div class="w-full overflow-hidden rounded-lg shadow-xs">
                            <div class="w-full overflow-x-auto">
                                <table class="w-full whitespace-no-wrap">
                                    <thead>
                                        <tr class="text-xs font-semibold tracking-wide text-left text-gray-500 uppercase border-b dark:border-gray-700 bg-gray-50 dark:text-gray-400 dark:bg-gray-800">
                                            <th class="px-4 py-3">Tanggal Tanam</th>
                                            <th class="px-4 py-3">Nama Lahan</th>
                                            <th class="px-4 py-3">Jenis Tanaman</th>
                                            <th class="px-4 py-3">Est. Panen</th>
                                            <th class="px-4 py-3">Biaya Modal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y dark:divide-gray-700 dark:bg-gray-800">
                                        <template x-if="pengeluaranList.length === 0">
                                            <tr><td colspan="5" class="px-4 py-3 text-center text-gray-500">Belum ada data pengeluaran.</td></tr>
                                        </template>
                                        <template x-for="row in pengeluaranList" :key="'out-' + row.id">
                                            <tr class="text-gray-700 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="px-4 py-3 align-top text-sm" x-text="formatDate(row.mulai_tanam)"></td>
                                                <td class="px-4 py-3 align-top text-sm" x-text="row.nama_lahan"></td>
                                                <td class="px-4 py-3 align-top">
                                                     <span class="px-2 py-1 text-xs font-semibold text-orange-700 bg-orange-100 rounded-full dark:bg-orange-600 dark:text-white" x-text="row.jenis_tanaman"></span>
                                                </td>
                                                <td class="px-4 py-3 align-top text-sm" x-text="formatDate(row.tanggal_panen)"></td>
                                                <td class="px-4 py-3 font-bold text-red-600 align-top text-sm" x-text="formatPrice(row.biaya_modal)"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- 2. TAMPILAN MOBILE (CARD) -->
                <!-- ========================================== -->
                <div class="block md:hidden space-y-4">
                    <template x-if="activeTab === 'pemasukan'">
                        <div class="space-y-4">
                            <template x-if="pemasukanList.length === 0">
                                <div class="text-center text-gray-500 dark:text-gray-400 py-4">Belum ada data pemasukan.</div>
                            </template>
                            <template x-for="row in pemasukanList" :key="'m-in-' + row.id">
                                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow-md border border-gray-100 dark:border-gray-700">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400" x-text="formatDate(row.tanggal_pesan)"></p>
                                            <h4 class="font-bold text-gray-800 dark:text-gray-200 text-sm mt-1" x-text="row.nama_penerima"></h4>
                                        </div>
                                        <span class="px-2 py-1 text-[10px] font-semibold text-green-700 bg-green-100 rounded-full dark:bg-green-700 dark:text-green-100" x-text="row.status_pesanan"></span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3 italic" x-text="row.alamat_lengkap"></p>
                                    <div class="bg-gray-50 dark:bg-gray-700 p-2 rounded-md mb-3">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1 font-semibold">Produk:</p>
                                        <div class="text-xs text-gray-700 dark:text-gray-300" x-html="row.produk_list"></div>
                                    </div>
                                    <div class="flex justify-between items-center pt-2 border-t dark:border-gray-700">
                                        <span class="text-xs font-semibold text-gray-600 dark:text-gray-400">Total:</span>
                                        <span class="text-sm font-bold text-green-600" x-text="formatPrice(row.total_harga)"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <template x-if="activeTab === 'pengeluaran'">
                        <div class="space-y-4">
                            <template x-if="pengeluaranList.length === 0">
                                <div class="text-center text-gray-500 dark:text-gray-400 py-4">Belum ada data pengeluaran.</div>
                            </template>
                            <template x-for="row in pengeluaranList" :key="'m-out-' + row.id">
                                <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow-md border border-gray-100 dark:border-gray-700">
                                    <div class="flex justify-between items-center mb-3">
                                        <span class="text-xs text-gray-500 dark:text-gray-400" x-text="'Tanam: ' + formatDate(row.mulai_tanam)"></span>
                                        <span class="px-2 py-1 text-[10px] font-semibold text-orange-700 bg-orange-100 rounded-full dark:bg-orange-600 dark:text-white" x-text="row.jenis_tanaman"></span>
                                    </div>
                                    <h4 class="font-bold text-gray-800 dark:text-gray-200 text-sm mb-1" x-text="row.nama_lahan"></h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3" x-text="'Est. Panen: ' + formatDate(row.tanggal_panen)"></p>
                                    <div class="flex justify-between items-center pt-2 border-t dark:border-gray-700">
                                        <span class="text-xs font-semibold text-gray-600 dark:text-gray-400">Biaya Modal:</span>
                                        <span class="text-sm font-bold text-red-600" x-text="formatPrice(row.biaya_modal)"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

            </div>
        </main>
    </div>
  </div>
  
  <script>
    function pageData() {
      // Helper untuk Theme
      function getThemeFromLocalStorage() {
        if (window.localStorage.getItem('dark')) {
          return JSON.parse(window.localStorage.getItem('dark'))
        }
        return (!!window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)
      }
      function setThemeToLocalStorage(value) {
        window.localStorage.setItem('dark', value)
      }
      
      // Data dari PHP
      const pemasukanPHP = <?php echo json_encode($pemasukan_list); ?>;
      const pengeluaranPHP = <?php echo json_encode($pengeluaran_list); ?>;

      // Membuat Daftar Tahun (Tahun Ini mundur 5 tahun)
      const currentYear = new Date().getFullYear();
      const years = [];
      for(let i=0; i<5; i++){
        years.push(currentYear - i);
      }
      
      return {
        dark: getThemeFromLocalStorage(),
        isSideMenuOpen: false,
        toggleSideMenu() { this.isSideMenuOpen = !this.isSideMenuOpen; },
        closeSideMenu() { this.isSideMenuOpen = false; },
        toggleTheme() { this.dark = !this.dark; setThemeToLocalStorage(this.dark); },

        // Notifikasi & Profile
        isNotificationsMenuOpen: false,
        toggleNotificationsMenu() { this.isNotificationsMenuOpen = !this.isNotificationsMenuOpen; },
        closeNotificationsMenu() { this.isNotificationsMenuOpen = false; },
        isProfileMenuOpen: false,
        toggleProfileMenu() { this.isProfileMenuOpen = !this.isProfileMenuOpen; },
        closeProfileMenu() { this.isProfileMenuOpen = false; },
        
        // State Utama
        activeTab: 'pemasukan', 
        pemasukanList: pemasukanPHP,
        pengeluaranList: pengeluaranPHP,
        
        // State Tahun
        selectedYear: currentYear,
        yearOptions: years,

        formatPrice(value) {
          if (isNaN(value) || value === null) return 'Rp 0';
          return 'Rp ' + Number(value).toLocaleString('id-ID');
        },
        formatDate(dateString) {
            if (!dateString || dateString === '0000-00-00') return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
        },

        // Fungsi Cetak PDF dengan Filter Tahun
        printPDF() {
            // Mengirim parameter type dan year ke CetakLaporan.php
            const url = `CetakLaporan.php?type=${this.activeTab}&year=${this.selectedYear}`;
            window.open(url, '_blank');
        }
      }
    }
  </script>
  </body>
</html>