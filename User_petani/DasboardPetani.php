<?php
// 1. MEMULAI SESSION & KONEKSI
session_start();
// Path ini: ../ (keluar dari User_petani) -> public/ -> pages/ -> Connection.php
include __DIR__ . '/../public/pages/Connection.php';

// 2. KEAMANAN: Cek jika user sudah login dan adalah 'petani'
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'petani') {
    $petani_id = null;
    header("Location: ../public/pages/Login.php");
    exit;
} else {
    $petani_id = (int) $_SESSION['user_id'];
}

// ==================================================================
// 4. AMBIL SEMUA DATA STATISTIK UNTUK DASHBOARD
// ==================================================================
$total_pemasukan = 0;
$total_pengeluaran = 0;
$saldo_akhir = 0;
$pesanan_baru_count = 0;
$total_lahan_count = 0; 
$pesanan_terbaru_list = [];
$produk_stok_rendah_list = [];
$line_chart_data = []; 

if ($petani_id) {
    
    // --- 1. STATISTIK KEUANGAN (SAMA PERSIS DENGAN CATATAN KEUANGAN) ---
    
    // 1A. Total Pemasukan (Hanya dari Pesanan: Dikirim/Selesai)
    $sql_pemasukan = "SELECT SUM(total_harga) AS total 
                      FROM pesanan 
                      WHERE petani_id = ? AND (status_pesanan = 'Dikirim' OR status_pesanan = 'Selesai')";
    $stmt_in = mysqli_prepare($conn, $sql_pemasukan);
    mysqli_stmt_bind_param($stmt_in, 'i', $petani_id);
    mysqli_stmt_execute($stmt_in);
    $total_pemasukan = (double) (mysqli_stmt_get_result($stmt_in)->fetch_assoc()['total'] ?? 0);
    mysqli_stmt_close($stmt_in);

    // 1B. Total Pengeluaran (Hanya dari Lahan: Biaya Modal)
    $sql_pengeluaran = "SELECT SUM(biaya_modal) AS total 
                        FROM lahan 
                        WHERE petani_id = ? AND biaya_modal > 0";
    $stmt_out = mysqli_prepare($conn, $sql_pengeluaran);
    mysqli_stmt_bind_param($stmt_out, 'i', $petani_id);
    mysqli_stmt_execute($stmt_out);
    $total_pengeluaran = (double) (mysqli_stmt_get_result($stmt_out)->fetch_assoc()['total'] ?? 0);
    mysqli_stmt_close($stmt_out);

    // Hitung Saldo Akhir
    $saldo_akhir = $total_pemasukan - $total_pengeluaran;


    // --- 2. DASHBOARD WIDGET LAINNYA ---

    // 2A. Jumlah Pesanan Baru
    $stmt_pesanan_count = mysqli_prepare($conn, "SELECT COUNT(id) AS pesanan_baru 
                                                 FROM pesanan 
                                                 WHERE petani_id = ? AND (status_pesanan = 'Menunggu Pembayaran' OR status_pesanan = 'Menunggu Konfirmasi')");
    mysqli_stmt_bind_param($stmt_pesanan_count, 'i', $petani_id);
    mysqli_stmt_execute($stmt_pesanan_count);
    $pesanan_baru_count = (int) (mysqli_stmt_get_result($stmt_pesanan_count)->fetch_assoc()['pesanan_baru'] ?? 0);
    mysqli_stmt_close($stmt_pesanan_count);

    // 2B. 5 Pesanan Masuk Terbaru
    $sql_pesanan_terbaru = "SELECT p.*, a.nama_penerima 
                            FROM pesanan p
                            LEFT JOIN alamat a ON p.alamat_id = a.id
                            WHERE p.petani_id = ? 
                            ORDER BY p.tanggal_pesan DESC
                            LIMIT 5";
    $stmt_pesanan_terbaru = mysqli_prepare($conn, $sql_pesanan_terbaru);
    mysqli_stmt_bind_param($stmt_pesanan_terbaru, 'i', $petani_id);
    mysqli_stmt_execute($stmt_pesanan_terbaru);
    $res_pesanan_terbaru = mysqli_stmt_get_result($stmt_pesanan_terbaru);
    while ($row = $res_pesanan_terbaru->fetch_assoc()) {
        $pesanan_terbaru_list[] = $row;
    }
    mysqli_stmt_close($stmt_pesanan_terbaru);

    // 2C. 5 Produk Stok Rendah (<= 10 kg)
    $stmt_stok = mysqli_prepare($conn, "SELECT id, nama_produk, stok_kg 
                                        FROM produk 
                                        WHERE petani_id = ? AND stok_kg <= 10 
                                        ORDER BY stok_kg ASC 
                                        LIMIT 5");
    mysqli_stmt_bind_param($stmt_stok, 'i', $petani_id);
    mysqli_stmt_execute($stmt_stok);
    $res_stok = mysqli_stmt_get_result($stmt_stok);
    while ($row = $res_stok->fetch_assoc()) {
        $produk_stok_rendah_list[] = $row;
    }
    mysqli_stmt_close($stmt_stok);

    // 2D. Hitung Total Lahan (Aset)
    $stmt_lahan_count = mysqli_prepare($conn, "SELECT COUNT(id) AS total_lahan FROM manajemen_lahan WHERE petani_id = ?");
    mysqli_stmt_bind_param($stmt_lahan_count, 'i', $petani_id);
    mysqli_stmt_execute($stmt_lahan_count);
    $total_lahan_count = (int) (mysqli_stmt_get_result($stmt_lahan_count)->fetch_assoc()['total_lahan'] ?? 0);
    mysqli_stmt_close($stmt_lahan_count);
    
    // --- 3. DATA GRAFIK GARIS (6 Bulan Terakhir) ---
    // Menggabungkan data Pemasukan (Pesanan) dan Pengeluaran (Lahan)
    $sql_line_chart = "SELECT
                            bulan,
                            SUM(pemasukan_per_bulan) AS pemasukan_per_bulan,
                            SUM(pengeluaran_per_bulan) AS pengeluaran_per_bulan
                        FROM (
                            -- Pemasukan (Pesanan Dikirim/Selesai)
                            (SELECT 
                                DATE_FORMAT(tanggal_pesan, '%Y-%m') AS bulan,
                                SUM(total_harga) AS pemasukan_per_bulan,
                                0 AS pengeluaran_per_bulan
                            FROM pesanan
                            WHERE petani_id = ? 
                              AND (status_pesanan = 'Dikirim' OR status_pesanan = 'Selesai')
                              AND tanggal_pesan >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                            GROUP BY bulan)
                            
                            UNION ALL
                            
                            -- Pengeluaran (Biaya Modal Lahan)
                            (SELECT 
                                DATE_FORMAT(mulai_tanam, '%Y-%m') AS bulan,
                                0 AS pemasukan_per_bulan,
                                SUM(biaya_modal) AS pengeluaran_per_bulan
                            FROM lahan
                            WHERE petani_id = ? 
                              AND biaya_modal > 0
                              AND mulai_tanam >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                            GROUP BY bulan)
                        ) AS combined_transactions
                        WHERE bulan IS NOT NULL
                        GROUP BY bulan
                        ORDER BY bulan ASC";
    
    $stmt_line = mysqli_prepare($conn, $sql_line_chart);
    mysqli_stmt_bind_param($stmt_line, 'ii', $petani_id, $petani_id);
    mysqli_stmt_execute($stmt_line);
    $res_line = mysqli_stmt_get_result($stmt_line);
    while ($row = $res_line->fetch_assoc()) {
        $line_chart_data[] = $row;
    }
    mysqli_stmt_close($stmt_line);

    // Siapkan array untuk Chart.js
    $line_labels = [];
    $line_data_pemasukan = [];
    $line_data_pengeluaran = [];
    foreach ($line_chart_data as $data) {
        // Ubah format bulan (misal: 2023-11 -> Nov 23)
        $dateObj   = DateTime::createFromFormat('!Y-m', $data['bulan']);
        $monthName = $dateObj->format('M y'); // Jan 24
        
        $line_labels[] = $monthName;
        $line_data_pemasukan[] = (double)$data['pemasukan_per_bulan'];
        $line_data_pengeluaran[] = (double)$data['pengeluaran_per_bulan'];
    }
}
?>

<!DOCTYPE html>
<html :class="{ 'theme-dark': dark }" x-data="data()" lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Dashboard - TaniMaju</title>
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
                        href="DasboardPetani.php"
                    >
                        <svg class="w-6 h-6 mr-2 text-green-600" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M4 14V8a2 2 0 012-2h12a2 2 0 012 2v6m-4 0v4m-8-4v4m0-8h8m-8 0V6"></path>
                        </svg>
                        <span>TaniMaju</span>
                    </a>
                    <ul class="mt-6">
                        <li class="relative px-6 py-3">
                             <span
                                class="absolute inset-y-0 left-0 w-1 bg-purple-600 rounded-tr-lg rounded-br-lg"
                                aria-hidden="true"
                              ></span>
                            <a
                                class="inline-flex items-center w-full text-sm font-semibold text-gray-800 transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200 dark:text-gray-100"
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
                            <span
                                class="absolute inset-y-0 left-0 w-1 bg-purple-600 rounded-tr-lg rounded-br-lg"
                                aria-hidden="true"
                            ></span>
                            <a
                                class="inline-flex items-center w-full text-sm font-semibold text-gray-800 transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200 dark:text-gray-100"
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
                        class="container flex items-center justify-between h-full px-6 mx-auto text-purple-600 dark:text-purple-300"
                    >
                        <button
                            class="p-1 mr-5 -ml-1 rounded-md md:hidden focus:outline-none focus:shadow-outline-purple"
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
                                class="relative w-full max-w-xl mr-6 focus-within:text-purple-500"
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
                                    class="w-full pl-8 pr-2 text-sm text-gray-700 placeholder-gray-600 bg-gray-100 border-0 rounded-md dark:placeholder-gray-500 dark:focus:shadow-outline-gray dark:focus:placeholder-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:placeholder-gray-500 focus:bg-white focus:border-purple-300 focus:outline-none focus:shadow-outline-purple form-input"
                                    type="text"
                                    placeholder="Search for projects"
                                    aria-label="Search"
                                />
                            </div>
                        </div>
                        <ul class="flex items-center flex-shrink-0 space-x-6">
                            <li class="flex">
                                <button
                                    class="rounded-md focus:outline-none focus:shadow-outline-purple"
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
                                    class="align-middle rounded-full focus:shadow-outline-purple focus:outline-none"
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
                    <div class="container px-6 mx-auto grid">
                        <h2
                            class="my-6 text-2xl font-semibold text-gray-700 dark:text-gray-200"
                        >
                            Dashboard
                        </h2>
                        
                        <div class="grid gap-6 mb-8 md:grid-cols-2 xl:grid-cols-4">
                            <div class="flex items-center p-4 bg-white rounded-lg shadow-xs dark:bg-gray-800">
                                <div class="p-3 mr-4 text-blue-500 bg-blue-100 rounded-full dark:text-blue-100 dark:bg-blue-500">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                       <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.5 2.5 0 00-1.133.069V7.418zM10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5zM10 1.5a.75.75 0 01.743.648L11.563 5h3.982a.75.75 0 01.62.116l.044.051c.364.404.59 1.01.59 1.684v4.5c0 .674-.226 1.28-.59 1.684l-.044.051a.75.75 0 01-.62.116h-3.982l-.82 2.852A.75.75 0 0110 18.5a.75.75 0 01-.743-.648L8.437 15H4.455a.75.75 0 01-.62-.116l-.044-.051C3.426 14.43 3.2 13.824 3.2 13.15V8.65c0-.674.226-1.28.59-1.684l.044-.051a.75.75 0 01.62-.116h3.982l.82-2.852A.75.75 0 0110 1.5zM11.563 15L12.3 12.2a.75.75 0 01.743-.648h2.362c.105-.118.155-.283.155-.4v-2.304c0-.117-.05-.282-.155-.4H13.044a.75.75 0 01-.743-.648L11.563 5h-.874l-.82 2.852a.75.75 0 01-.743.648H6.764c-.105.118-.155.283-.155.4v2.304c0 .117.05.282.155.4h2.362a.75.75 0 01.743.648L9.563 15h2z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="mb-2 text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Total Penghasilan
                                    </p>
                                    <p class="text-lg font-semibold text-gray-700 dark:text-gray-200">
                                        Rp <?php echo number_format($total_pemasukan, 0, ',', '.'); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="flex items-center p-4 bg-white rounded-lg shadow-xs dark:bg-gray-800">
                                <div class="p-3 mr-4 text-purple-500 bg-purple-100 rounded-full dark:text-purple-100 dark:bg-purple-500">
                                    <svg class="w-5 h-5" fill="none"color="green" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24">
                                         <path d="M4 14V8a2 2 0 012-2h12a2 2 0 012 2v6m-4 0v4m-8-4v4m0-8h8m-8 0V6"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="mb-2 text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Total Lahan Yang Dikelola
                                    </p>
                                    <p class="text-lg font-semibold text-gray-700 dark:text-gray-200">
                                        <?php echo $total_lahan_count; ?> Lahan
                                    </p>
                                </div>
                            </div>
                            
                            <div class="flex items-center p-4 bg-white rounded-lg shadow-xs dark:bg-gray-800">
                                <div class="p-3 mr-4 text-orange-500 bg-orange-100 rounded-full dark:text-orange-100 dark:bg-orange-500">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM7 9a1 1 0 000 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="mb-2 text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Total Pengeluaran
                                    </p>
                                    <p class="text-lg font-semibold text-gray-700 dark:text-gray-200">
                                        Rp <?php echo number_format($total_pengeluaran, 0, ',', '.'); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center p-4 bg-white rounded-lg shadow-xs dark:bg-gray-800">
                                <div class="p-3 mr-4 text-teal-500 bg-teal-100 rounded-full dark:text-teal-100 dark:bg-teal-500">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1H14a1 1 0 00.894-.553l3-6A1 1 0 0017 3H6.28l-.31-1.243A1 1 0 005 1H3zM16 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM6.5 18a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="mb-2 text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Pesanan Baru
                                    </p>
                                    <p class="text-lg font-semibold text-gray-700 dark:text-gray-200">
                                        <?php echo $pesanan_baru_count; ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-6 mb-8 md:grid-cols-2">
                            <div class="min-w-0 p-4 bg-white rounded-lg shadow-xs dark:bg-gray-800">
                                <h4 class="mb-4 font-semibold text-gray-800 dark:text-gray-300">
                                    Laporan Keuangan (6 Bulan Terakhir)
                                </h4>
                                <canvas id="line"></canvas>
                                <div class="flex justify-center mt-4 space-x-3 text-sm text-gray-600 dark:text-gray-400">
                                    <div class="flex items-center">
                                        <span class="inline-block w-3 h-3 mr-1 bg-green-600 rounded-full"></span>
                                        <span>Pemasukan</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="inline-block w-3 h-3 mr-1 bg-red-500 rounded-full"></span>
                                        <span>Pengeluaran</span>
                                    </div>
                                </div>
                            </div>
                            <div class="min-w-0 p-4 bg-white rounded-lg shadow-xs dark:bg-gray-800">
                                <h4 class="mb-4 font-semibold text-gray-800 dark:text-gray-300">
                                    Tipe Transaksi (Keseluruhan)
                                </h4>
                                <canvas id="pie"></canvas>
                                <div class="flex justify-center mt-4 space-x-3 text-sm text-gray-600 dark:text-gray-400">
                                    <div class="flex items-center">
                                        <span class="inline-block w-3 h-3 mr-1 bg-green-600 rounded-full"></span>
                                        <span>Pemasukan</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="inline-block w-3 h-3 mr-1 bg-red-500 rounded-full"></span>
                                        <span>Pengeluaran</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        
                        <div class="grid gap-6 mb-8 md:grid-cols-1 xl:grid-cols-2">
                            <div class="w-full overflow-hidden rounded-lg shadow-xs">
                                <h4 class="px-4 py-3 font-semibold text-gray-800 dark:text-gray-300 bg-white dark:bg-gray-800 border-b dark:border-gray-700">
                                    Pesanan Masuk Terbaru
                                </h4>
                                <div class="w-full overflow-x-auto">
                                    <table class="w-full whitespace-no-wrap">
                                        <thead class="text-xs font-semibold tracking-wide text-left text-gray-500 uppercase border-b dark:border-gray-700 bg-gray-50 dark:text-gray-400 dark:bg-gray-800">
                                            <tr>
                                                <th class="px-4 py-3">ID</th>
                                                <th class="px-4 py-3">Pelanggan</th>
                                                <th class="px-4 py-3">Total</th>
                                                <th class="px-4 py-3">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y dark:divide-gray-700 dark:bg-gray-800">
                                            <?php if (empty($pesanan_terbaru_list)): ?>
                                                <tr><td colspan="4" class="px-4 py-3 text-center text-sm text-gray-500">Tidak ada pesanan baru.</td></tr>
                                            <?php else: foreach ($pesanan_terbaru_list as $pesanan): ?>
                                                <tr class="text-gray-700 dark:text-gray-400">
                                                    <td class="px-4 py-3 text-sm">#<?php echo $pesanan['id']; ?></td>
                                                    <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($pesanan['nama_penerima'] ?? '(ID: '.$pesanan['pembeli_id'].')'); ?></td>
                                                    <td class="px-4 py-3 text-sm">Rp <?php echo number_format($pesanan['total_harga'], 0, ',', '.'); ?></td>
                                                    <td class="px-4 py-3 text-xs">
                                                        <span class="px-2 py-1 font-semibold leading-tight rounded-full 
                                                            <?php if ($pesanan['status_pesanan'] === 'Menunggu Pembayaran' || $pesanan['status_pesanan'] === 'Menunggu Konfirmasi') echo 'text-orange-700 bg-orange-100 dark:bg-orange-600 dark:text-white';
                                                                  elseif ($pesanan['status_pesanan'] === 'Selesai') echo 'text-green-700 bg-green-100 dark:bg-green-700 dark:text-green-100';
                                                                  else echo 'text-gray-700 bg-gray-100 dark:bg-gray-700 dark:text-gray-100'; ?>">
                                                            <?php echo htmlspecialchars($pesanan['status_pesanan']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="px-4 py-3 text-xs font-semibold tracking-wide text-gray-500 uppercase border-t dark:border-gray-700 bg-gray-50 dark:text-gray-400 dark:bg-gray-800">
                                    <a href="PesananMasuk.php" class="text-green-600 hover:underline">Lihat Semua Pesanan &rarr;</a>
                                </div>
                            </div>

                            <div class="w-full overflow-hidden rounded-lg shadow-xs">
                                <h4 class="px-4 py-3 font-semibold text-gray-800 dark:text-gray-300 bg-white dark:bg-gray-800 border-b dark:border-gray-700">
                                    Produk Stok Rendah (<= 10 Kg)
                                </h4>
                                <div class="w-full overflow-x-auto">
                                    <table class="w-full whitespace-no-wrap">
                                        <thead class="text-xs font-semibold tracking-wide text-left text-gray-500 uppercase border-b dark:border-gray-700 bg-gray-50 dark:text-gray-400 dark:bg-gray-800">
                                            <tr>
                                                <th class="px-4 py-3">ID Produk</th>
                                                <th class="px-4 py-3">Nama Produk</th>
                                                <th class="px-4 py-3">Sisa Stok (Kg)</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y dark:divide-gray-700 dark:bg-gray-800">
                                            <?php if (empty($produk_stok_rendah_list)): ?>
                                                <tr><td colspan="3" class="px-4 py-3 text-center text-sm text-gray-500">Semua stok produk aman.</td></tr>
                                            <?php else: foreach ($produk_stok_rendah_list as $produk): ?>
                                                <tr class="text-gray-700 dark:text-gray-400">
                                                    <td class="px-4 py-3 text-sm"><?php echo $produk['id']; ?></td>
                                                    <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($produk['nama_produk']); ?></td>
                                                    <td class="px-4 py-3 text-sm font-semibold text-red-600">
                                                        <?php echo $produk['stok_kg']; ?> Kg
                                                    </td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="px-4 py-3 text-xs font-semibold tracking-wide text-gray-500 uppercase border-t dark:border-gray-700 bg-gray-50 dark:text-gray-400 dark:bg-gray-800">
                                    <a href="KelolaProduk.php" class="text-green-600 hover:underline">Kelola Semua Produk &rarr;</a>
                                </div>
                            </div>
                        </div>
                        
                        
                    </div>
                </main>
            </div>
        </div>
        
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                
                // === Data untuk Pie Chart (Tipe Transaksi) ===
                const pieChartData = {
                    labels: ['Pemasukan', 'Pengeluaran'],
                    datasets: [
                        {
                            data: [<?php echo $total_pemasukan; ?>, <?php echo $total_pengeluaran; ?>],
                            backgroundColor: ['#059669', '#EF4444'], // Sesuai permintaan: Hijau (Green-600) dan Merah (Red-500)
                            label: 'Tipe Transaksi',
                        },
                    ],
                }

                const pieCtx = document.getElementById('pie')
                if (pieCtx) {
                    window.myPie = new Chart(pieCtx, {
                        type: 'doughnut',
                        data: pieChartData,
                        options: {
                            responsive: true,
                            cutoutPercentage: 80,
                            legend: {
                                display: false, // Label di bawah sudah ada
                            },
                        },
                    })
                }

                // === Data untuk Line Chart (Laporan Bulanan) ===
                const lineChartLabels = <?php echo json_encode($line_labels); ?>;
                const lineDataPemasukan = <?php echo json_encode($line_data_pemasukan); ?>;
                const lineDataPengeluaran = <?php echo json_encode($line_data_pengeluaran); ?>;

                const lineConfig = {
                    type: 'line',
                    data: {
                        labels: lineChartLabels,
                        datasets: [
                            {
                                label: 'Pemasukan',
                                backgroundColor: '#059669', // Hijau
                                borderColor: '#059669',
                                data: lineDataPemasukan,
                                fill: false,
                            },
                            {
                                label: 'Pengeluaran',
                                fill: false,
                                backgroundColor: '#EF4444', // Merah
                                borderColor: '#EF4444',
                                data: lineDataPengeluaran,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        tooltips: {
                            mode: 'index',
                            intersect: false,
                        },
                        hover: {
                            mode: 'nearest',
                            intersect: true,
                        },
                        scales: {
                            x: {
                                display: true,
                                scaleLabel: {
                                    display: true,
                                    labelString: 'Bulan',
                                },
                            },
                            y: {
                                display: true,
                                scaleLabel: {
                                    display: true,
                                    labelString: 'Jumlah (Rp)',
                                },
                            },
                        },
                    },
                }

                const lineCtx = document.getElementById('line')
                if (lineCtx) {
                    window.myLine = new Chart(lineCtx, lineConfig)
                }
            })
        </script>
    </body>
</html>