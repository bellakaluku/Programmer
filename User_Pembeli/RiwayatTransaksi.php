<?php
// 1. MEMULAI SESSION & KONEKSI
session_start();
// Path: ../ (keluar dari User_Pembeli) -> public/pages/Connection.php
require_once __DIR__ . '/../public/pages/Connection.php';

// 2. KEAMANAN: Cek jika user sudah login dan adalah 'pembeli'
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'pembeli') {
    // Jika tidak, tendang ke halaman login
    header("Location: ../public/pages/Login.php");
    exit;
}

$pembeli_id = (int) $_SESSION['user_id'];

// 3. AMBIL DATA RIWAYAT PESANAN
// Kita akan mengambil semua pesanan dan produk di dalamnya sekaligus
$semua_pesanan = [];

// Query ini menggabungkan pesanan, detailnya, produk, dan status ulasan
$sql = "SELECT 
            p.id AS pesanan_id,
            p.tanggal_pesan,
            p.status_pesanan,
            p.total_harga, 
            p.metode_pembayaran, -- Ditambahkan untuk ditampilkan
            dp.id AS detail_id,
            dp.jumlah_kg,
            dp.subtotal,
            pr.nama_produk,
            pr.foto,
            u.id AS ulasan_id 
        FROM pesanan p
        JOIN detail_pesanan dp ON dp.pesanan_id = p.id
        JOIN produk pr ON dp.produk_id = pr.id
        LEFT JOIN ulasan u ON u.detail_pesanan_id = dp.id 
        WHERE p.pembeli_id = ?
        ORDER BY p.tanggal_pesan DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $pembeli_id);
$stmt->execute();
$result = $stmt->get_result();

// 4. Kelompokkan produk berdasarkan ID pesanan
$pesanan_grup = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $pesanan_id = $row['pesanan_id'];
        
        // Jika ini pertama kali kita melihat ID pesanan ini, simpan info utamanya
        if (!isset($pesanan_grup[$pesanan_id])) {
            $pesanan_grup[$pesanan_id] = [
                'tanggal' => $row['tanggal_pesan'],
                'status' => $row['status_pesanan'],
                'total_harga_pesanan' => $row['total_harga'], // Menggunakan 'total_harga' dari DB Anda
                'metode_bayar' => $row['metode_pembayaran'], // Ditambahkan
                'items' => [] // Siapkan array untuk produk-produknya
            ];
        }
        
        // Tambahkan produk (item) ini ke dalam grup pesanannya
        $pesanan_grup[$pesanan_id]['items'][] = $row;
    }
}
$stmt->close();
$conn->close();

// Ambil pesan notifikasi dari session jika ada
$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);
?>
<!DOCTYPE html>
<html :class="{ 'theme-dark': dark }" x-data="data()" lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Riwayat Transaksi - TaniMaju</title>
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
  </head>
  <body>
    <div
      class="flex h-screen bg-gray-50 dark:bg-gray-900"
      :class="{ 'overflow-hidden': isSideMenuOpen}"
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
                href="ProdukKKatalog.php"
              >
                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor">
                    <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                </svg>
                <span class="ml-4">Produk Katalog</span>
              </a>
            </li>
            <li class="relative px-6 py-3">
              <a
                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
                href="KeranjangProduk.php"
              >
                 <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                <span class="ml-4">Keranjang</span>
              </a>
            </li>
            <li class="relative px-6 py-3">
             <span
                class="absolute inset-y-0 left-0 w-1 bg-purple-600 rounded-tr-lg rounded-br-lg"
                aria-hidden="true"
              ></span>
              <a
                class="inline-flex items-center w-full text-sm font-semibold text-gray-800 transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200 dark:text-gray-100"
                href="RiwayatTransaksi.php"
              >
                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor"><path d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                <span class="ml-4">Riwayat Transaksi</span>
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
            class="ml-6 text-lg font-bold text-gray-800 dark:text-gray-200"
            href="ProdukKKatalog.php"
          >
            TaniMaju (Pembeli)
          </a>
          <ul class="mt-6">
             <li class="relative px-6 py-3">
              <a
                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
                href="ProdukKKatalog.php"
              >
                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor">
                    <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                </svg>
                <span class="ml-4">Produk Katalog</span>
              </a>
            </li>
            <li class="relative px-6 py-3">
              <a
                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
                href="KeranjangProduk.php"
              >
                 <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                <span class="ml-4">Keranjang</span>
              </a>
            </li>
            <li class="relative px-6 py-3">
             <span
                class="absolute inset-y-0 left-0 w-1 bg-purple-600 rounded-tr-lg rounded-br-lg"
                aria-hidden="true"
              ></span>
              <a
                class="inline-flex items-center w-full text-sm font-semibold text-gray-800 transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200 dark:text-gray-100"
                href="RiwayatTransaksi.php"
              >
                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor"><path d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                <span class="ml-4">Riwayat Transaksi</span>
              </a>
            </li>
          </ul>
        </div>
      </aside>
      
      <div class="flex flex-col flex-1 w-full overflow-hidden">
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
                  placeholder="Cari transaksi..."
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
        
        <main class="h-full pb-16 overflow-y-auto">
          <div class="container max-w-4xl mx-auto px-6 py-12">
            
            <h2 class="my-6 text-2xl font-semibold text-gray-700 dark:text-gray-200">
              Riwayat Transaksi
            </h2>

            <?php if ($success_message): ?>
                <div class="px-4 py-3 mb-4 text-sm font-medium text-white bg-green-600 rounded-lg" role="alert">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <div class="space-y-8">
              
                <?php if (empty($pesanan_grup)): ?>
                    <p class="text-center text-gray-500 dark:text-gray-400">
                      Anda belum memiliki riwayat transaksi.
                    </p>
                <?php else: ?>
                    <?php foreach ($pesanan_grup as $pesanan_id => $pesanan): ?>
                        
                        <div class="p-4 rounded-lg shadow-md overflow-hidden text-gray-800 dark:text-gray-200
                            <?php 
                                switch($pesanan['status']) {
                                    case 'Selesai': echo 'border-l-8 border-green-500'; break;
                                    case 'Dikirim': echo 'border-l-8 border-blue-500'; break;
                                    case 'Dikemas': echo 'border-l-8 border-purple-500'; break;
                                    case 'Menunggu Verifikasi': echo 'border-l-8 border-yellow-500'; break;
                                    case 'Menunggu Pembayaran': echo 'border-l-8 border-gray-500'; break;
                                    case 'Dibatalkan': echo 'border-l-8 border-red-500'; break;
                                    default: echo 'border-l-8 border-gray-400';
                                }
                            ?>
                        ">
                            <div class="p-4 bg-gray-50 dark:bg-gray-700 border-b dark:border-gray-600 flex flex-col sm:flex-row justify-between items-start sm:items-center">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Pesanan #<?php echo $pesanan_id; ?></h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Tanggal: <?php echo date('d M Y, H:i', strtotime($pesanan['tanggal'])); ?>
                                    </p>
                                </div>
                                <div class="mt-2 sm:mt-0 text-right">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Total Pesanan:</span>
                                    <span class="block text-lg font-bold text-purple-600 dark:text-purple-400">
                                        Rp <?php echo number_format($pesanan['total_harga_pesanan'], 0, ',', '.'); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="w-full overflow-x-auto">
                                <table class="w-full whitespace-no-wrap">
                                    <tbody class="bg-white divide-y dark:divide-gray-700 dark:bg-gray-800">
                                        <?php foreach ($pesanan['items'] as $item): ?>
                                            <tr class="text-gray-700 dark:text-gray-400">
                                                <td class="px-4 py-3">
                                                    <div class="flex items-center text-sm">
                                                        <div class="relative hidden w-16 h-16 mr-3 rounded md:block" style="width: 64px; height: 64px; flex-shrink: 0;">
                                                            <img 
                                                                class="object-cover w-full h-full rounded" 
                                                                style="width: 100%; height: 100%; object-fit: cover;"
                                                                src="../User_petani/<?php echo htmlspecialchars($item['foto'] ?: 'assets/img/profil/default.png'); ?>" 
                                                                alt="<?php echo htmlspecialchars($item['nama_produk']); ?>" 
                                                                loading="lazy"
                                                                onerror="this.src='https://placehold.co/100x100/a9a2f7/ffffff?text=Produk'"
                                                            >
                                                        </div>
                                                        <div>
                                                            <p class="font-semibold"><?php echo htmlspecialchars($item['nama_produk']); ?></p>
                                                            <p class="text-xs text-gray-600 dark:text-gray-400">
                                                                <?php echo $item['jumlah_kg']; ?> Kg x Rp <?php echo number_format($item['subtotal'] / $item['jumlah_kg'], 0, ',', '.'); ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-sm font-semibold">
                                                    Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-right">
                                                    <?php if ($pesanan['status'] === 'Selesai'): ?>
                                                        <?php if ($item['ulasan_id'] === NULL): ?>
                                                            <a href="BeriUlasan.php?detail_id=<?php echo $item['detail_id']; ?>" 
                                                               class="px-3 py-1 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none">
                                                                Beri Ulasan
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="px-3 py-1 text-sm font-medium text-gray-500 dark:text-gray-400">
                                                                Sudah Diulas
                                                            </span>
                                                        <?php endif; ?>
                                                    
                                                    <?php elseif ($pesanan['status'] !== 'Menunggu Pembayaran' && $pesanan['status'] !== 'Dibatalkan'): ?>
                                                        <span class="px-2 py-1 text-xs font-semibold leading-tight rounded-full
                                                            <?php 
                                                                switch($pesanan['status']) {
                                                                    case 'Dikirim': echo 'text-blue-700 bg-blue-100 dark:bg-blue-700 dark:text-blue-100'; break;
                                                                    case 'Dikemas': echo 'text-purple-700 bg-purple-100 dark:bg-purple-700 dark:text-purple-100'; break;
                                                                    case 'Menunggu Verifikasi': echo 'text-yellow-700 bg-yellow-100 dark:bg-yellow-600 dark:text-white'; break;
                                                                    default: echo 'text-gray-700 bg-gray-100 dark:bg-gray-700 dark:text-gray-100';
                                                                }
                                                            ?>
                                                        ">
                                                            <?php echo htmlspecialchars($pesanan['status']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <footer class="p-4 bg-gray-50 dark:bg-gray-800 border-t dark:border-gray-700 flex justify-between items-center">
                                <div>
                                    <span class="text-sm font-semibold text-gray-600 dark:text-gray-300">
                                        Metode Bayar: <?php echo htmlspecialchars($pesanan['metode_bayar']); ?>
                                    </span>
                                </div>

                                <div class="flex items-center space-x-2">
                                    
                                    <?php if ($pesanan['status'] == 'Menunggu Pembayaran'): ?>
                                      <a href="KonfirmasiPembayaran.php?id=<?php echo $pesanan_id; ?>" 
                                        class="px-4 py-2 text-sm font-medium leading-5 text-gray-800 transition-colors duration-150 bg-white border border-gray-300 rounded-lg shadow-md hover:bg-gray-100 hover:text-gray-900 hover:shadow-lg focus:outline-none focus:shadow-outline-gray active:bg-white">
                                          Konfirmasi Pembayaran
                                      </a>

                                    <?php elseif ($pesanan['status'] == 'Dikirim'): ?>
                                        <form action="proses_selesai_pesanan.php" method="POST" onsubmit="return confirm('Anda yakin pesanan ini sudah diterima?');">
                                            <input type="hidden" name="pesanan_id" value="<?php echo $pesanan_id; ?>">
                                            <button type="submit"
                                                class="px-4 py-2 text-sm font-medium leading-5 text-white transition-colors duration-150 bg-blue-600 border border-transparent rounded-lg active:bg-blue-600 hover:bg-blue-700 focus:outline-none focus:shadow-outline-blue">
                                                Pesanan Diterima
                                            </button>
                                        </form>

                                    <?php elseif ($pesanan['status'] == 'Menunggu Verifikasi'): ?>
                                         <span class="px-4 py-2 text-sm font-medium leading-5 text-yellow-800 bg-yellow-100 rounded-full dark:bg-yellow-600 dark:text-white">
                                            Menunggu Verifikasi
                                         </span>
                                    
                                    <?php else: // 'Dikemas', 'Selesai', 'Dibatalkan' ?>
                                         <span class="px-4 py-2 text-sm font-medium leading-5 rounded-full
                                            <?php 
                                                switch($pesanan['status']) {
                                                    case 'Selesai': echo 'text-green-700 bg-green-100 dark:bg-green-700 dark:text-green-100'; break;
                                                    case 'Dikemas': echo 'text-purple-700 bg-purple-100 dark:bg-purple-700 dark:text-purple-100'; break;
                                                    case 'Dibatalkan': echo 'text-red-700 bg-red-100 dark:bg-red-700 dark:text-red-100'; break;
                                                    default: echo 'text-gray-700 bg-gray-100 dark:bg-gray-700 dark:text-gray-100';
                                                }
                                            ?>
                                         ">
                                            <?php echo htmlspecialchars($pesanan['status']); ?>
                                         </span>
                                    <?php endif; ?>

                                </div>
                            </footer>
                            </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
          </div>
        </main>
      </div>
    </div>
    
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
          isNotificationsMenuOpen: false,
          toggleNotificationsMenu() {
            this.isNotificationsMenuOpen = !this.isNotificationsMenuOpen
          },
          closeNotificationsMenu() {
            this.isNotificationsMenuOpen = false
          },
          isProfileMenuOpen: false,
          toggleProfileMenu() {
            this.isProfileMenuOpen = !this.isProfileMenuOpen
          },
          closeProfileMenu() {
            this.isProfileMenuOpen = false
          }
        }
      }
    </script>
  </body>
</html>