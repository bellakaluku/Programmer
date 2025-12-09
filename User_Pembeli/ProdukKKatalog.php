<?php
// ==========================================
// 1. INISIALISASI & KONEKSI
// ==========================================
session_start();

// Cek status login untuk menentukan tampilan header & akses fitur
$is_logged_in = (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'pembeli');
$pembeli_id = (int) ($_SESSION['user_id'] ?? 0);

// Hubungkan ke database
require_once __DIR__ . '/../public/pages/Connection.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Koneksi database gagal. Periksa pengaturan file Connection.php.");
}

// ==========================================
// 2. LOGIKA DATA (KATEGORI & PRODUK)
// ==========================================

// A. Ambil Daftar Kategori untuk Filter
$daftar_kategori = [];
$sql_kategori = "SELECT id, nama_kategori, slug FROM kategori ORDER BY nama_kategori ASC";
$result_kategori = $conn->query($sql_kategori);
if ($result_kategori && $result_kategori->num_rows > 0) {
    while ($row_kategori = $result_kategori->fetch_assoc()) {
        $daftar_kategori[] = $row_kategori;
    }
}

// Ambil parameter dari URL
$kategori_aktif_id = (int)($_GET['kategori'] ?? 0);
$search_query = $_GET['q'] ?? '';

// B. Ambil Data Produk
$products = [];
$params = [];
$types = "";

// Query dasar: join dengan user untuk dapat nama petani, filter stok & status
$sql = "SELECT 
            p.id, 
            p.nama_produk, 
            p.keterangan, 
            p.stok_kg, 
            p.harga_kg, 
            p.foto,
            u.nama AS nama_petani
        FROM produk p
        JOIN users u ON p.petani_id = u.id
        WHERE p.stok_kg > 0 AND p.status = 'aktif'";

// Tambahkan filter Kategori jika dipilih
if ($kategori_aktif_id > 0) {
    $sql .= " AND p.kategori_id = ?";
    $types .= "i";
    $params[] = $kategori_aktif_id;
}

// Tambahkan filter Pencarian jika ada input
if (!empty($search_query)) {
    $sql .= " AND p.nama_produk LIKE ?";
    $types .= "s";
    $search_term = "%" . $search_query . "%";
    $params[] = $search_term;
}

$sql .= " ORDER BY p.nama_produk ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
} else {
    die("Error pada Query SQL: " . $conn->error);
}
?>

<!DOCTYPE html>
<html :class="{ 'theme-dark': dark }" x-data="data()" lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Katalog Produk - TaniMaju</title>
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

    <style>
      /* CSS Kustom untuk Grid Produk */
      .product-grid-new {
          display: grid;
          grid-template-columns: repeat(2, 1fr); 
          gap: 0.75rem; 
      }
      @media (min-width: 640px) { .product-grid-new { grid-template-columns: repeat(3, 1fr); gap: 1rem; } }
      @media (min-width: 768px) { .product-grid-new { grid-template-columns: repeat(4, 1fr); } }
      @media (min-width: 1024px) { .product-grid-new { grid-template-columns: repeat(5, 1fr); } }

      /* CSS Kustom untuk Kartu Produk */
      .product-card-new {
          background-color: #ffffff; 
          border-radius: 8px; 
          box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); 
          overflow: hidden; 
          display: flex;
          flex-direction: column;
          transition: all 0.3s ease;
          text-decoration: none; 
          border: 1px solid #e5e7eb; 
          cursor: pointer;
      }
      .product-card-new:hover {
          transform: translateY(-4px); 
          box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.07), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
          border-color: #8b5cf6; 
      }
      .product-image-wrapper {
          width: 100%;
          aspect-ratio: 1 / 1; 
          overflow: hidden;
      }
      .product-image-wrapper img {
          width: 100%;
          height: 100%;
          object-fit: cover; 
      }
      .product-info {
          padding: 0.75rem; 
          display: flex;
          flex-direction: column;
          flex-grow: 1; 
      }
      .product-name-new {
          font-size: 0.875rem; 
          font-weight: 600; 
          color: #1f2937; 
          overflow: hidden;
          text-overflow: ellipsis;
          display: -webkit-box;
          -webkit-line-clamp: 2;
          -webkit-box-orient: vertical;
          line-height: 1.25rem; 
          height: 2.5rem; 
      }
      .product-seller-new {
          font-size: 0.75rem; 
          color: #6b7280; 
          margin-top: 4px;
          margin-bottom: 8px;
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
      }
      .product-price-new {
          font-size: 1.125rem; 
          font-weight: 700; 
          color: #7c3aed; 
          margin-top: auto; 
      }
      .theme-dark .product-card-new {
          background-color: #1f2937; 
          border-color: #4b5563; 
      }
      .theme-dark .product-name-new { color: #f3f4f6; }
      .theme-dark .product-seller-new { color: #9ca3af; }
      .theme-dark .product-price-new { color: #a78bfa; }

      /* CSS untuk Filter Kategori */
      .kategori-filter-link {
          padding-left: 1rem;
          padding-right: 1rem;
          padding-top: 0.5rem;
          padding-bottom: 0.5rem;
          font-size: 0.875rem;
          font-weight: 500;
          border-radius: 9999px;
          transition: all 0.2s;
          text-decoration: none;
      }
      .kategori-aktif {
          background-color: #7c3aed; 
          color: #ffffff; 
      }
      .kategori-nonaktif {
          background-color: #e5e7eb; 
          color: #374151; 
      }
      .kategori-nonaktif:hover { background-color: #d1d5db; }
      .theme-dark .kategori-nonaktif {
          background-color: #374151; 
          color: #d1d5db; 
      }
      .theme-dark .kategori-nonaktif:hover { background-color: #4b5563; }
    </style>
  </head>
  <body>

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
      <div
        x-show="isModalOpen"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 transform translate-y-1/2"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0 transform translate-y-1/2"
        @click.away="isModalOpen = false"
        @keydown.escape="isModalOpen = false"
        class="w-full px-6 py-4 overflow-hidden bg-white rounded-t-lg dark:bg-gray-800 sm:rounded-lg sm:m-4 sm:max-w-xl"
        role="dialog"
      >
        <header class="flex justify-end">
          <button
            class="inline-flex items-center justify-center w-6 h-6 text-gray-400 transition-colors duration-150 rounded dark:hover:text-gray-200 hover: hover:text-gray-700"
            aria-label="close"
            @click="isModalOpen = false"
          >
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20" role="img" aria-hidden="true">
              <path
                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                clip-rule="evenodd"
                fill-rule="evenodd"
              ></path>
            </svg>
          </button>
        </header>
        <div class="mt-4 mb-6">
          <p class="mb-4 text-lg font-semibold text-gray-700 dark:text-gray-300">
            Anda Belum Login
          </p>
          <p class="text-sm text-gray-700 dark:text-gray-400">
            Anda harus login terlebih dahulu untuk melihat detail produk dan melakukan pembelian.
          </p>
        </div>
        <footer
          class="flex flex-col items-center justify-end px-6 py-3 -mx-6 -mb-4 space-y-4 sm:space-y-0 sm:space-x-6 sm:flex-row bg-gray-50 dark:bg-gray-800"
        >
          <button
            @click="isModalOpen = false"
            class="w-full px-5 py-3 text-sm font-medium leading-5 text-gray-700 transition-colors duration-150 border border-gray-300 rounded-lg dark:text-gray-400 sm:px-4 sm:py-2 sm:w-auto active:bg-transparent hover:border-gray-500 focus:border-gray-500 active:text-gray-500 focus:outline-none focus:shadow-outline-gray"
          >
            Batal
          </button>
          <a
            href="../public/pages/Login.php"
            class="w-full px-5 py-3 text-sm font-medium leading-5 text-center text-white transition-colors duration-150 bg-purple-600 border border-transparent rounded-lg sm:w-auto sm:px-4 sm:py-2 active:bg-purple-600 hover:bg-purple-700 focus:outline-none focus:shadow-outline-purple"
          >
            Login Sekarang
          </a>
        </footer>
      </div>
    </div>
    
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
          <ul class="mt-6"></ul>
          <ul>
            <li class="relative px-6 py-3">
              <span
                class="absolute inset-y-0 left-0 w-1 bg-purple-600 rounded-tr-lg rounded-br-lg"
                aria-hidden="true"
              ></span>
              <a
                class="inline-flex items-center w-full text-sm font-semibold text-gray-800 transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200 dark:text-gray-100"
                href="ProdukKKatalog.php"
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
                <span class="ml-4">Produk Katalog</span>
              </a>
            </li>

            <li class="relative px-6 py-3">
              <a
                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
                href="KeranjangProduk.php"
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
                  <path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <span class="ml-4">Keranjang</span>
              </a>
            </li>
            <li class="relative px-6 py-3">
              <a
                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
                href="RiwayatTransaksi.php"
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
              class="ml-6 text-lg font-bold text-gray-800 dark:text-gray-200 flex items-center"
              href="DasboardPetani.php"
            >
              <svg class="w-6 h-6 mr-2 text-green-600" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24">
                  <path d="M4 14V8a2 2 0 012-2h12a2 2 0 012 2v6m-4 0v4m-8-4v4m0-8h8m-8 0V6"></path>
              </svg>
              <span>TaniMaju</span>
          </a>
          <ul class="mt-6"></ul>
          <ul>
            <li class="relative px-6 py-3">
              <span
                class="absolute inset-y-0 left-0 w-1 bg-purple-600 rounded-tr-lg rounded-br-lg"
                aria-hidden="true"
              ></span>
              <a
                class="inline-flex items-center w-full text-sm font-semibold text-gray-800 transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200 dark:text-gray-100"
                href="ProdukKKatalog.php"
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
                <span class="ml-4">Produk Katalog</span>
              </a>
            </li>

            <li class="relative px-6 py-3">
              <a
                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
                href="KeranjangProduk.php"
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
                  <path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <span class="ml-4">Keranjang</span>
              </a>
            </li>
            <li class="relative px-6 py-3">
              <a
                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
                href="RiwayatTransaksi.php"
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
            
            <form method="GET" action="ProdukKKatalog.php" class="flex justify-center flex-1 lg:mr-32">
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
                  name="q"
                  class="w-full pl-8 pr-2 text-sm text-gray-700 placeholder-gray-600 bg-gray-100 border-0 rounded-md dark:placeholder-gray-500 dark:focus:shadow-outline-gray dark:focus:placeholder-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:placeholder-gray-500 focus:bg-white focus:border-purple-300 focus:outline-none focus:shadow-outline-purple form-input"
                  type="text"
                  placeholder="Cari produk di katalog..."
                  aria-label="Search"
                  value="<?php echo htmlspecialchars($search_query); ?>"
                />
              </div>
              <?php if ($kategori_aktif_id > 0): ?>
                <input type="hidden" name="kategori" value="<?php echo $kategori_aktif_id; ?>">
              <?php endif; ?>
            </form>

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
                    <?php if ($is_logged_in): ?>
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
                            <span>Profil Saya</span>
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
                            <span>Keluar</span>
                          </a>
                        </li>

                    <?php else: ?>
                        <li class="flex">
                          <a
                            class="inline-flex items-center w-full px-2 py-1 text-sm font-semibold transition-colors duration-150 rounded-md hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                            href="../public/pages/Login.php"
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
                            <span>Masuk (Login)</span>
                          </a>
                        </li>
                    <?php endif; ?>
                  </ul>
                </template>
              </li>
            </ul>
          </div>
        </header>

        <main class="h-full pb-16 overflow-y-auto">
          <div class="container max-w-6xl mx-auto px-4 sm:px-6">

            <div class="my-6">
                <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-3">Kategori</h3>
                <div class="flex overflow-x-auto gap-3 py-2">
                    <a href="ProdukKKatalog.php<?php echo !empty($search_query) ? '?q='.urlencode($search_query) : ''; ?>" 
                       class="flex-shrink-0 kategori-filter-link <?php echo ($kategori_aktif_id == 0) ? 'kategori-aktif' : 'kategori-nonaktif'; ?>">
                        Semua
                    </a>
                    <?php foreach ($daftar_kategori as $kategori): 
                        $kategori_url = "ProdukKKatalog.php?kategori={$kategori['id']}";
                        if (!empty($search_query)) {
                            $kategori_url .= "&q=" . urlencode($search_query);
                        }
                    ?>
                        <a href="<?php echo $kategori_url; ?>"
                           class="flex-shrink-0 kategori-filter-link <?php echo ($kategori_aktif_id == $kategori['id']) ? 'kategori-aktif' : 'kategori-nonaktif'; ?>">
                            <?php echo htmlspecialchars($kategori['nama_kategori']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
                <div>
                    <?php if ($kategori_aktif_id > 0) {
                        $nama_kat_aktif = '';
                        foreach($daftar_kategori as $kat) {
                            if ($kat['id'] == $kategori_aktif_id) {
                                $nama_kat_aktif = $kat['nama_kategori'];
                                break;
                            }
                        }
                        echo '<span class="text-gray-700 dark:text-gray-300">Menampilkan: ' . htmlspecialchars($nama_kat_aktif) . '</span>';
                    } ?>
                </div>
                
                <div class="flex flex-col sm:flex-row items-center gap-4 w-full sm:w-auto">
                    <input id="search-product" type="hidden" value="<?php echo htmlspecialchars($search_query); ?>">
                    
                    <label class="block w-full text-sm">
                        <span class="text-gray-700 dark:text-gray-400">Urutkan:</span>
                        <select id="sort-product" class="block w-full mt-1 text-sm dark:text-gray-300 dark:border-gray-600 dark:bg-gray-700 form-select focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:focus:shadow-outline-gray">
                            <option value="default" selected>Default</option>
                            <option value="price-low">Harga Terendah</option>
                            <option value="price-high">Harga Tertinggi</option>
                        </select>
                    </label>
                </div>
            </div>

            <div id="product-grid" class="product-grid-new mb-8">
              <?php if (empty($products)): ?>
                <p class="col-span-full text-center text-gray-500 dark:text-gray-400 py-10">
                  <?php if (!empty($search_query) || $kategori_aktif_id > 0): ?>
                    Tidak ada produk yang cocok dengan filter Anda.
                  <?php else: ?>
                    Tidak ada produk yang tersedia saat ini.
                  <?php endif; ?>
                </p>
              <?php else: foreach ($products as $p):
                  $photo_path = '../User_petani/' . $p['foto'];
                  $photo = ($p['foto'] && file_exists($photo_path)) ? $photo_path : 'https://placehold.co/300x300/a9a2f7/ffffff?text=Produk';
                  
                  $price = (int)$p['harga_kg'];

                  $link_href = $is_logged_in ? "DetailProduk.php?id={$p['id']}" : "#";
                  $link_click = $is_logged_in ? "" : "@click.prevent=\"isModalOpen = true\"";
                  ?>
                  
                  <a href="<?= $link_href; ?>" <?= $link_click; ?>
                     class="product-card-new"
                     data-price="<?= $price ?>"> 
                     <div class="product-image-wrapper">
                         <img src="<?= htmlspecialchars($photo) ?>" alt="<?= htmlspecialchars($p['nama_produk']) ?>"
                              onerror="this.src='https://placehold.co/300x300/a9a2f7/ffffff?text=Error'">
                     </div>
                   
                    <div class="product-info">
                      <h3 class="product-name-new"><?= htmlspecialchars($p['nama_produk']) ?></h3>
                      <p class="product-seller-new">Oleh: <?= htmlspecialchars($p['nama_petani']) ?></p>
                      <p class="product-price-new">Rp <?= number_format($price, 0, ',', '.') ?></p>
                    </div>
                  </a>
                  <?php endforeach; endif; ?>
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
            },
            isModalOpen: false
        }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('sort-product').addEventListener('change', function() {
            const sortType = this.value;
            const container = document.getElementById('product-grid'); 
            const cards = Array.from(container.querySelectorAll('.product-card-new'));

            cards.sort((a, b) => {
                const priceA = parseInt(a.dataset.price, 10);
                const priceB = parseInt(b.dataset.price, 10);

                if (sortType === 'price-low') return priceA - priceB;
                if (sortType === 'price-high') return priceB - priceA;
                return 0; 
            });

            cards.forEach(card => container.appendChild(card));
        });
        });
    </script>
  </body>
</html>