<?php
// 1. MEMULAI SESSION & KONEKSI
session_start();

// --- PERBAIKAN 1: Cek Status Login ---
// Ini akan bernilai true jika user sudah login, false jika belum
$is_logged_in = (isset($_SESSION['user_id']) && !empty($_SESSION['role']));
// --- SELESAI PERBAIKAN 1 ---

// Path ke Connection.php (di dalam folder 'pages')
require_once 'pages/Connection.php';

// 2. AMBIL DATA PRODUK, KATEGORI, DAN FILTER
$products = [];
$daftar_kategori = [];

// Ambil filter aktif dari URL
$search_query = $_GET['q'] ?? '';
$kategori_aktif_id = (int)($_GET['kategori'] ?? 0);

if (isset($conn) && $conn instanceof mysqli) {
    
    // --- AMBIL KATEGORI UNTUK FILTER ---
    $sql_kategori = "SELECT id, nama_kategori, slug FROM kategori ORDER BY nama_kategori ASC";
    $result_kategori = $conn->query($sql_kategori);
    if ($result_kategori && $result_kategori->num_rows > 0) {
        while ($row_kategori = $result_kategori->fetch_assoc()) {
            $daftar_kategori[] = $row_kategori;
        }
    }
    // --- SELESAI AMBIL KATEGORI ---

    // --- AMBIL PRODUK DENGAN FILTER ---
    $params = []; // Array untuk menyimpan parameter bind
    $types = "";   // String untuk tipe data bind

    // Query dasar: HANYA ambil produk yang 'aktif' dan stoknya ada
    $sql = "SELECT id, nama_produk, harga_kg, foto, stok_kg 
            FROM produk 
            WHERE stok_kg > 0 AND status = 'aktif'";
    
    // Tambahkan filter KATEGORI jika ada
    if ($kategori_aktif_id > 0) {
        $sql .= " AND kategori_id = ?";
        $types .= "i"; // i untuk integer
        $params[] = $kategori_aktif_id;
    }

    // Tambahkan kondisi LIKE jika ada query pencarian
    if (!empty($search_query)) {
        $sql .= " AND nama_produk LIKE ?";
        $types .= "s"; // s untuk string
        $search_term = "%" . $search_query . "%";
        $params[] = $search_term;
    }
    
    $sql .= " ORDER BY id DESC";
            
    $stmt = $conn->prepare($sql);
    
    // Bind semua parameter sekaligus
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Path foto produk (naik satu level ke root, lalu ke User_petani)
            $row['foto_path'] = '../User_petani/' . ($row['foto'] ?: 'assets/img/profil/default.png');
            $products[] = $row;
        }
    }
    $stmt->close();
    $conn->close();
} else {
    die("Koneksi database gagal.");
}
?>

<html :class="{ 'theme-dark': dark }" x-data="pageData()" lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Selamat Datang di TaniMaju</title>
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="../User_Pembeli/assets/css/tailwind.output.css" />
    <script
      src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js"
      defer
    ></script>

    <!-- ==== CSS MANUAL UNTUK PRODUK & KATEGORI ==== -->
    <style>
        .catalog-wrapper {
            background-color: #ffffff;
            padding: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }
        .theme-dark .catalog-wrapper {
             background-color: #1a202c;
             box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        .product-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem; 
        }
        .product-card {
            background-color: #ffffff; 
            border: 1px solid #f0f0f0;  
            border-radius: 0.25rem;     
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); 
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100%;
            text-decoration: none; 
            transition: box-shadow 0.2s ease-in-out, transform 0.2s ease-in-out;
            cursor: pointer;
        }
        .product-card:hover {
            transform: translateY(-4px); 
            box-shadow: 0 6px 16px 0 rgba(0, 0, 0, 0.12); 
        }
        .theme-dark .product-card {
            background-color: #1f2937; 
            border-color: #2d3748; 
        }
        .product-image-wrapper {
            position: relative;
            width: 100%;
            padding-top: 100%; 
            height: 0;
            background-color: #f7fafc;
        }
        .theme-dark .product-image-wrapper {
            background-color: #2d3748;
        }
        .product-card-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover; 
        }
        .product-text-container {
            padding: 0.75rem; 
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .product-name {
            font-size: 0.875rem; 
            font-weight: 500;   
            color: #1f2937; 
            margin-bottom: 0.5rem; 
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            min-height: 2.5em; 
        }
        .theme-dark .product-name { color: #edf2f7; }
        .product-price {
            font-size: 1.125rem; 
            font-weight: 700; 
            color: #7c3aed; 
        }
        .theme-dark .product-price { color: #9f7aea; }
        .product-price span {
            font-size: 0.75rem; 
            font-weight: 400;
            color: #4b5563; 
        }
        .theme-dark .product-price span { color: #a0aec0; }
        .product-stock {
            font-size: 0.75rem; 
            color: #6b7280; 
            margin-top: 0.25rem; 
            text-align: right; 
        }
        .theme-dark .product-stock { color: #a0aec0; }
        @media (min-width: 768px) { .product-grid { grid-template-columns: repeat(3, 1fr); gap: 1rem; } }
        @media (min-width: 1024px) { .product-grid { grid-template-columns: repeat(4, 1fr); } }
        @media (min-width: 1280px) { .product-grid { grid-template-columns: repeat(5, 1fr); gap: 1.25rem; } }

        /* CSS untuk Filter Kategori */
      .kategori-filter-link {
          padding-left: 1rem; padding-right: 1rem;
          padding-top: 0.5rem; padding-bottom: 0.5rem;
          font-size: 0.875rem; font-weight: 500;
          border-radius: 9999px; transition: all 0.2s;
          text-decoration: none;
      }
      
      .kategori-aktif { background-color: #7c3aed; color: #ffffff; }
      .kategori-nonaktif { background-color: #e5e7eb; color: #374151; }
      .kategori-nonaktif:hover { background-color: #d1d5db; }
      .theme-dark .kategori-nonaktif { background-color: #374151; color: #d1d5db; }
      .theme-dark .kategori-nonaktif:hover { background-color: #4b5563; }
    </style>
</head>
<body>

    <!-- ==== AWAL MODAL POPUP (PERUBAHAN 2) ==== -->
    <!-- Modal backdrop -->
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
        x-transition:leave-end="opacity-0  transform translate-y-1/2"
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
        <!-- Modal body -->
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
            href="pages/Login.php"
            class="w-full px-5 py-3 text-sm font-medium leading-5 text-center text-white transition-colors duration-150 bg-purple-600 border border-transparent rounded-lg sm:w-auto sm:px-4 sm:py-2 active:bg-purple-600 hover:bg-purple-700 focus:outline-none focus:shadow-outline-purple"
          >
            Login Sekarang
          </a>
        </footer>
      </div>
    </div>
    <!-- ==== AKHIR MODAL POPUP ==== -->


    <div class="flex flex-col min-h-screen bg-gray-50 dark:bg-gray-900">
        
        <header class="bg-white dark:bg-gray-800 shadow-md z-10 py-4">
            <div class="container mx-auto flex items-center justify-between h-full px-6 text-gray-800 dark:text-gray-200">
                
                <a class="text-lg font-bold text-gray-800 dark:text-gray-200 flex items-center" href="index.php">
                    <svg class="w-6 h-6 mr-2 text-green-600" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M4 14V8a2 2 0 012-2h12a2 2 0 012 2v6m-4 0v4m-8-4v4m0-8h8m-8 0V6"></path>
                    </svg>
                    <span>TaniMaju</span>
                </a>
                
                <form method="GET" action="index.php" class="relative flex-1 max-w-xl mx-6 hidden md:block">
                    <div class="absolute inset-y-0 flex items-center pl-2">
                        <svg class="w-4 h-4 text-gray-500" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <input
                        class="w-full pl-8 pr-2 text-sm text-gray-700 placeholder-gray-600 bg-gray-100 border-0 rounded-md dark:placeholder-gray-500 dark:focus:shadow-outline-gray dark:focus:placeholder-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:placeholder-gray-500 focus:bg-white focus:border-purple-300 focus:outline-none focus:shadow-outline-purple form-input"
                        type="text"
                        name="q" placeholder="Cari produk segar..."
                        aria-label="Search"
                        value="<?php echo htmlspecialchars($search_query); ?>" />
                    <?php if ($kategori_aktif_id > 0): ?>
                        <input type="hidden" name="kategori" value="<?php echo $kategori_aktif_id; ?>">
                    <?php endif; ?>
                    <button type="submit" class="hidden"></button>
                </form>

                <div class="flex items-center space-x-4">
                    <?php if ($is_logged_in): ?>
                        <!-- Jika SUDAH login -->
                        <a href="../User_Pembeli/ProdukKKatalog.php"
                           class="px-4 py-2 text-sm font-medium leading-5 text-white transition-colors duration-150 bg-purple-600 border border-transparent rounded-lg active:bg-purple-600 hover:bg-purple-700 focus:outline-none focus:shadow-outline-purple">
                            Katalog Saya
                        </a>
                        <a
                            class="align-middle rounded-full focus:shadow-outline-purple focus:outline-none"
                            href="#" 
                        >
                            <img
                                class="object-cover w-8 h-8 rounded-full"
                                src="https://images.unsplash.com/photo-1502378735452-bc7d86632805?ixlib=rb-0.3.5&q=80&fm=jpg&crop=entropy&cs=tinysrgb&w=200&fit=max&s=aa3a807e1bbdfd4364d1f449eaa96d82"
                                alt=""
                                aria-hidden="true"
                            />
                        </a>
                    <?php else: ?>
                        <!-- Jika BELUM login -->
                        <a href="pages/Login.php"
                           class="px-4 py-2 text-sm font-medium leading-5 text-white transition-colors duration-150 bg-purple-600 border border-transparent rounded-lg active:bg-purple-600 hover:bg-purple-700 focus:outline-none focus:shadow-outline-purple">
                            Login
                        </a>
                        <a href="pages/Register.php"
                           class="hidden sm:inline-block px-4 py-2 text-sm font-medium leading-5 text-purple-700 bg-transparent border border-purple-600 rounded-lg dark:text-gray-400 dark:border-gray-400 hover:bg-purple-100 dark:hover:bg-gray-700 focus:outline-none focus:shadow-outline-purple">
                            Daftar
                        </a>
                    <?php endif; ?>
                    
                    <button class="rounded-md focus:outline-none focus:shadow-outline-purple" @click="toggleTheme" aria-label="Toggle color mode">
                        <template x-if="!dark">
                            <svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
                            </svg>
                        </template>
                        <template x-if="dark">
                            <svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"></path>
                            </svg>
                        </template>
                    </button>
                </div>
            </div>
        </header>
        
<main class="flex-grow">
    <div class="container px-6 mx-auto py-8">

        <div class="my-6">
            <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-3">Kategori</h3>
            
            <div class="flex overflow-x-auto gap-3 py-2">
                
                <a href="index.php<?php echo !empty($search_query) ? '?q='.urlencode($search_query) : ''; ?>" 
                   class="flex-shrink-0 kategori-filter-link <?php echo ($kategori_aktif_id == 0) ? 'kategori-aktif' : 'kategori-nonaktif'; ?>">
                    Semua
                </a>
                
                <?php foreach ($daftar_kategori as $kategori): 
                    // PERBAIKAN LINK: Diubah dari 'ProdukKKatalog.php' menjadi 'index.php'
                    $kategori_url = "index.php?kategori={$kategori['id']}";
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
        <div class="catalog-wrapper">
            <h2 class="my-6 text-2xl font-semibold text-gray-700 dark:text-gray-200">
                Produk Segar Hari Ini
            </h2>
            
            <div class="product-grid mb-8">
                
                <?php if (empty($products)): ?>
                    <div class="col-span-full text-center text-gray-500 dark:text-gray-400 py-10">
                        <p class="text-lg">
                            <?php if (!empty($search_query) || $kategori_aktif_id > 0): ?>
                                Produk tidak ditemukan.
                            <?php else: ?>
                                Belum ada produk yang tersedia saat ini.
                            <?php endif; ?>
                        </p>
                    </div>
                
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <?php
                            // Logika link dinamis Anda sudah benar
                            $link_href = $is_logged_in ? "DetailProduk.php?id={$product['id']}" : "#";
                            $link_click = $is_logged_in ? "" : "@click.prevent=\"isModalOpen = true\"";
                        ?>
                        <a href="<?php echo $link_href; ?>" <?php echo $link_click; ?>
                           class="product-card">
                            
                            <div class="product-image-wrapper"> 
                                <img class="product-card-image"
                                     src="<?php echo htmlspecialchars($product['foto_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($product['nama_produk']); ?>" 
                                     loading="lazy"
                                     onerror="this.src='https://placehold.co/300x300/a9a2f7/ffffff?text=Foto+Produk'" />
                            </div>
                            
                            <div class="product-text-container">
                                <div>
                                    <h3 class="product-name"  title="<?php echo htmlspecialchars($product['nama_produk']); ?>">
                                        <?php echo htmlspecialchars($product['nama_produk']); ?>
                                    </h3>
                                </div>
                                <div class="mt-2">
                                    <p class="product-price">
                                        Rp <?php echo number_format($product['harga_kg'], 0, ',', '.'); ?>
                                        <span>/ kg</span>
                                    </p>
                                    <p class="product-stock">
                                        Stok: <?php echo htmlspecialchars($product['stok_kg']); ?> Kg
                                    </p>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>

        <footer class="py-6 bg-white dark:bg-gray-800 shadow-inner mt-auto">
            <div class="container mx-auto text-center text-sm text-gray-500 dark:text-gray-400">
                &copy; <?php echo date('Y'); ?> TaniMaju. All rights reserved.
            </div>
        </footer>
    </div>
    
    <script>
      function pageData() {
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
          // --- PERUBAHAN 3: Tambahkan state modal ---
          isModalOpen: false
          // --- SELESAI PERUBAHAN 3 ---
        }
      }
      document.addEventListener('alpine:init', () => {
        Alpine.data('pageData', pageData);
      });
    </script>
</body>
</html>