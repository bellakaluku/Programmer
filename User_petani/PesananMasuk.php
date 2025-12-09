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

// Ambil pesan notifikasi dari session jika ada
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// 3. AMBIL DATA PESANAN UNTUK PETANI INI
$pesanan_list = [];
if (isset($conn) && $conn instanceof mysqli) {
    
    // --- (MODIFIKASI 1) QUERY DIUBAH UNTUK MENGAMBIL PRODUK ---
    $sql = "SELECT 
                ps.id as pesanan_id, 
                ps.tanggal_pesan, 
                ps.total_harga, 
                ps.status_pesanan,
                ps.metode_pembayaran,
                a.nama_penerima, 
                a.alamat_lengkap, 
                a.kota,
                pay.bukti_transfer,
                pay.status_verifikasi,
                
                -- Menggabungkan nama dan jumlah produk, dipisah <br>
                GROUP_CONCAT(pr.nama_produk, ' (', dp.jumlah_kg, ' kg)' SEPARATOR '<br>') AS produk_list_html
                
            FROM pesanan ps
            LEFT JOIN alamat a ON ps.alamat_id = a.id
            LEFT JOIN pembayaran pay ON ps.id = pay.pesanan_id
            LEFT JOIN detail_pesanan dp ON ps.id = dp.pesanan_id
            LEFT JOIN produk pr ON dp.produk_id = pr.id
            WHERE ps.petani_id = ? 
            AND ps.status_pesanan IN ('Menunggu Verifikasi', 'Dikemas', 'Dikirim')
            GROUP BY ps.id -- Grup berdasarkan ID pesanan
            ORDER BY ps.tanggal_pesan DESC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $petani_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $pesanan_list[] = $row;
        }
    }
    $stmt->close();
    // --- AKHIR MODIFIKASI 1 ---
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
                             <span
                                class="absolute inset-y-0 left-0 w-1 bg-green-600 rounded-tr-lg rounded-br-lg"
                                aria-hidden="true"
                            ></span>
                            <a
                                class="inline-flex items-center w-full text-sm font-semibold text-gray-800 transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200 dark:text-gray-100"
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
                             <span
                                class="absolute inset-y-0 left-0 w-1 bg-green-600 rounded-tr-lg rounded-br-lg"
                                aria-hidden="true"
                            ></span>
                            <a
                                class="inline-flex items-center w-full text-sm font-semibold text-gray-800 transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200 dark:text-gray-100"
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
          <div class="container grid px-6 mx-auto">
            
            <h2 class="my-6 text-2xl font-semibold text-gray-700 dark:text-gray-200">
              Pesanan Masuk
            </h2>

            <?php if ($success_message): ?>
                <div class="px-4 py-3 mb-4 text-sm font-medium text-white bg-green-600 rounded-lg" role="alert">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="px-4 py-3 mb-4 text-sm font-medium text-white bg-red-600 rounded-lg" role="alert">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="w-full overflow-hidden rounded-lg shadow-xs">
              <div class="w-full overflow-x-auto">
                <table class="w-full whitespace-no-wrap">
                  
                  <thead>
                    <tr class="text-xs font-semibold tracking-wide text-left text-gray-800 uppercase border-b dark:border-gray-700 bg-gray-50 dark:text-blacke-400 dark:bg-white-800">
                      <th class="px-4 py-3">Tanggal Pesan</th>
                      <th class="px-4 py-3">Pembeli & Alamat</th>
                      <th class="px-4 py-3">Produk Dipesan</th>
                      <th class="px-4 py-3">Total</th>
                      <th class="px-4 py-3">Status</th>
                      <th class="px-4 py-3">Aksi</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y dark:divide-gray-800 dark:bg-gray-800">
                    <?php if (empty($pesanan_list)): ?>
                        <tr>
                            <td colspan="6" class="px-4 py-3 text-center text-gray-500 dark:text-gray-400">
                                Belum ada pesanan masuk.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pesanan_list as $pesanan): ?>
                        <tr class="text-gray-700 dark:text-gray-400">
                          
                          <td class="px-4 py-3 text-sm">
                            <?php echo date('d M Y', strtotime($pesanan['tanggal_pesan'])); ?>
                          </td>
                          
                          <td class="px-4 py-3 text-sm">
                            <p class="font-semibold"><?php echo htmlspecialchars($pesanan['nama_penerima']); ?></p>
                            <p class="text-xs text-gray-600 dark:text-gray-400">
                              <?php echo htmlspecialchars($pesanan['alamat_lengkap']); ?>, <?php echo htmlspecialchars($pesanan['kota']); ?>
                            </p>
                          </td>

                          <td class="px-4 py-3 text-sm">
                            <?php echo $pesanan['produk_list_html'] ?? 'Produk tidak ditemukan'; ?>
                          </td>
                          
                          <td class="px-4 py-3 text-sm">
                            Rp <?php echo number_format($pesanan['total_harga'], 0, ',', '.'); ?>
                          </td>
                          
                          <td class="px-4 py-3 text-xs">
                            <?php if ($pesanan['status_pesanan'] == 'Menunggu Verifikasi'): ?>
                                <span class="px-2 py-1 font-semibold leading-tight text-yellow-700 bg-yellow-100 rounded-full dark:bg-yellow-600 dark:text-black">
                                    Perlu Verifikasi
                                </span>
                            <?php elseif ($pesanan['status_pesanan'] == 'Dikemas'): ?>
                                <span class="px-2 py-1 font-semibold leading-tight text-yellow-700 bg-blue-100 rounded-full dark:bg-blue-600 dark:text-yellow">
                                    Siap Dikemas
                                </span>
                            <?php elseif ($pesanan['status_pesanan'] == 'Dikirim'): ?>
                                <span class="px-2 py-1 font-semibold leading-tight text-gray-700 bg-gray-100 rounded-full dark:bg-gray-600 dark:text-black">
                                    Dikirim
                                </span>
                            <?php endif; ?>
                          </td>
                          
                          <td class="px-4 py-3 text-sm">
                            <?php if ($pesanan['status_pesanan'] == 'Menunggu Verifikasi'): ?>
                                
                                <!-- PERUBAHAN 1: Mengganti <a> dengan <button> untuk modal -->
                                <button 
                                  type="button"
                                  @click.prevent="isModalOpen = true; modalImage = '../public/<?php echo htmlspecialchars(addslashes($pesanan['bukti_transfer'])); ?>'"
                                  class="px-3 py-1 text-xs font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none">
                                  Lihat Bukti
                                </button>
                                <!-- AKHIR PERUBAHAN 1 -->

                                <form action="proses_verifikasi_petani.php" method="POST" style="display:inline-block; margin-left: 5px;">
                                    <input type="hidden" name="pesanan_id" value="<?php echo $pesanan['pesanan_id']; ?>">
                                    <input type="hidden" name="aksi" value="terima">
                                    <button type="submit" class="px-3 py-1 text-xs font-medium text-green-600 bg-green rounded-md hover:bg-red-700">
                                        Terima
                                    </button>
                                </form>
                                <form action="proses_verifikasi_petani.php" method="POST" style="display:inline-block;">
                                    <input type="hidden" name="pesanan_id" value="<?php echo $pesanan['pesanan_id']; ?>">
                                    <input type="hidden" name="aksi" value="tolak">
                                    <button type="submit" class="px-3 py-1 text-xs font-medium text-white bg-red-600 rounded-md hover:bg-red-700">
                                        Tolak
                                    </button>
                                </form>
                                
                            <?php elseif ($pesanan['status_pesanan'] == 'Dikemas'): ?>
                                <form action="proses_kirim_pesanan.php" method="POST">
                                    <input type="hidden" name="pesanan_id" value="<?php echo $pesanan['pesanan_id']; ?>">
                                    <button type="submit" class="px-3 py-1 text-xs font-medium text-white bg-purple-600 rounded-md hover:bg-purple-700">
                                        Tandai Telah Dikirim
                                    </button>
                                </form>
                                
                            <?php elseif ($pesanan['status_pesanan'] == 'Dikirim'): ?>
                                <span class="text-xs text-gray-500">Menunggu konfirmasi pembeli</span>
                            <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

          </div>
        </main>
      </div>
    </div>
    
    <!-- PERUBAHAN 2: HTML UNTUK MODAL BUKTI PEMBAYARAN -->
    <div
      x-show="isModalOpen"
      x-transition:enter="transition ease-out duration-150"
      x-transition:enter-start="opacity-0"
      x-transition:enter-end="opacity-100"
      x-transition:leave="transition ease-in duration-150"
      x-transition:leave-start="opacity-100"
      x-transition:leave-end="opacity-0"
      class="fixed inset-0 z-30 flex items-center justify-center bg-black bg-opacity-75"
      style="display: none;"
    >
      <!-- Modal content -->
      <div 
        @click.away="isModalOpen = false"
        class="relative w-full max-w-2xl p-4 mx-auto bg-white rounded-lg shadow-xl dark:bg-gray-800"
      >
        <!-- Tombol Close (X) -->
        <button
          @click="isModalOpen = false"
          class="absolute -top-3 -right-3 flex items-center justify-center w-8 h-8 text-white bg-red-600 rounded-full focus:outline-none"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
        
        <!-- Judul dan Tombol Cetak -->
        <div class="flex items-center justify-between pb-3 border-b dark:border-gray-700">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100"></h3>
          <button
            @click="printModalImage()"
            class="flex items-center px-3 py-1 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm7-8V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
            </svg>
            Cetak Bukti
          </button>
        </div>
        
        <!-- Gambar Bukti -->
        <div class="mt-4" style="max-height: 70vh; overflow-y: auto;">
          <img :src="modalImage" alt="Bukti Transfer" id="printable-image" class="w-full h-auto object-contain">
        </div>
      </div>
    </div>
    <!-- AKHIR PERUBAHAN 2 -->

    <script>
      function data() {
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
        return {
          dark: getThemeFromLocalStorage(),
          toggleTheme() {
            this.dark = !this.dark
            setThemeToLocalStorage(this.dark)
          },
          isSideMenuOpen: false,
          toggleSideMenu() {
            this.isSideMenuOpen = !this.isSideMenuOpen
          },
          closeSideMenu() {
            this.isSideMenuOpen = false
          },
          isProfileMenuOpen: false,
          toggleProfileMenu() {
            this.isProfileMenuOpen = !this.isProfileMenuOpen
          },
          closeProfileMenu() {
            this.isProfileMenuOpen = false
          },

          // --- PERUBAHAN 3: TAMBAHAN UNTUK MODAL BUKTI ---
          isModalOpen: false,
          modalImage: '',
          
          printModalImage() {
            const printWindow = window.open('', '_blank', 'height=600,width=800');
            const imageSrc = document.getElementById('printable-image').src;
            
            printWindow.document.write('<html><head><title>Cetak Bukti</title>');
            printWindow.document.write('<style>body { margin: 0; padding: 0; text-align: center; } img { max-width: 100%; } @media print { @page { size: auto; margin: 0; } body { margin: 0.5cm; } }</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write('<img src="' + imageSrc + '" onload="window.print(); window.close();">');
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.focus();
          }
          // --- AKHIR PERUBAHAN 3 ---
        }
      }
    </script>
  </body>
</html>