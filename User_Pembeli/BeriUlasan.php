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

// 3. Ambil ID Detail Pesanan dari URL
$detail_id = (int) ($_GET['detail_id'] ?? 0);
if ($detail_id === 0) {
    die("Error: ID Item Pesanan tidak ditemukan.");
}

// 4. VALIDASI
if (isset($conn) && $conn instanceof mysqli) {
    
    // Validasi 1: Cek apakah item ini sudah pernah diulas
    $stmt_cek = $conn->prepare("SELECT id FROM ulasan WHERE detail_pesanan_id = ?");
    $stmt_cek->bind_param('i', $detail_id);
    $stmt_cek->execute();
    $result_cek = $stmt_cek->get_result();
    if ($result_cek->num_rows > 0) {
        $stmt_cek->close();
        echo "<script>alert('Anda sudah pernah memberi ulasan untuk produk ini.'); window.location='RiwayatTransaksi.php';</script>";
        exit;
    }
    $stmt_cek->close();

    // Validasi 2: Ambil info produk dan pastikan pesanan ini milik Anda
    $sql_item = "SELECT 
                    dp.produk_id, 
                    p.nama_produk, 
                    p.foto,
                    pes.pembeli_id
                 FROM detail_pesanan dp
                 JOIN produk p ON dp.produk_id = p.id
                 JOIN pesanan pes ON dp.pesanan_id = pes.id
                 WHERE dp.id = ?";
    
    $stmt_item = $conn->prepare($sql_item);
    $stmt_item->bind_param('i', $detail_id);
    $stmt_item->execute();
    $result_item = $stmt_item->get_result();
    $item = $result_item->fetch_assoc();
    $stmt_item->close();

    if (!$item) {
        die("Error: Item pesanan tidak ditemukan.");
    }
    
    // Validasi 3: Pastikan ini milik Anda
    if ($item['pembeli_id'] != $pembeli_id) {
        die("Error: Anda tidak berhak mengulas pesanan ini.");
    }
    
    // Jika semua lolos, siapkan variabel untuk form
    $produk_id = $item['produk_id'];
    $nama_produk = $item['nama_produk'];
    // Path foto (relatif dari folder User_petani)
    $foto_path = '../User_petani/' . ($item['foto'] ?: 'assets/img/profil/default.png');
    
} else {
    die("Koneksi database gagal.");
}
?>
<!DOCTYPE html>
<html :class="{ 'theme-dark': dark }" x-data="data()" lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Beri Ulasan - TaniMaju</title>
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
    
    <!-- CSS Khusus untuk Rating Bintang -->
    <style>
        .star-rating {
            display: inline-flex; /* Menggunakan flex alih-alih inline-block */
            direction: rtl; /* Balik arah (kanan ke kiri) */
            justify-content: center; /* Pusatkan bintang */
        }
        .star-rating input[type=radio] {
            display: none; /* Sembunyikan radio button asli */
        }
        .star-rating label {
            font-size: 2.5rem; /* Ukuran bintang (sesuai text-4xl/5xl) */
            color: #d1d5db; /* Warna bintang mati (gray-300) */
            cursor: pointer;
            transition: color 0.2s ease;
            padding: 0 0.125rem; /* Jarak kecil antar bintang */
        }
        /* Logika hover: Bintang yang di-hover dan semua bintang di kirinya akan menyala */
        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input[type=radio]:checked ~ label {
            color: #f59e0b; /* Warna bintang menyala (yellow-500) */
        }
    </style>
  </head>
  <body>
    <div
      class="flex h-screen bg-gray-50 dark:bg-gray-900"
      :class="{ 'overflow-hidden': isSideMenuOpen}"
    >
      <!-- Sidebar (Desktop) -->
      <aside
        class="z-20 hidden w-64 overflow-y-auto bg-white dark:bg-gray-800 md:block flex-shrink-0"
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
      <!-- Mobile sidebar -->
      <!-- ... (kode sidebar mobile Anda) ... -->

      <div class="flex flex-col flex-1 w-full">
        <header class="z-10 py-4 bg-white shadow-md dark:bg-gray-800">
          <!-- ... (kode header Anda) ... -->
        </header>
        
        <main class="h-full pb-16 overflow-y-auto">
          <div class="container max-w-xl mx-auto px-6 py-12">
            
            <h2 class="my-6 text-2xl font-semibold text-gray-700 dark:text-gray-200">
              Beri Ulasan
            </h2>

            <!-- Form Ulasan -->
            <div class="px-6 py-4 bg-white rounded-lg shadow-md dark:bg-gray-800">
              
              <!-- Info Produk yang Diulas -->
              <div class="flex items-center pb-4 border-b dark:border-gray-700">
                  <div class="relative w-16 h-16 rounded-md overflow-hidden mr-4 flex-shrink-0" style="width: 64px; height: 64px; flex-shrink: 0;">
                      <img 
                          class="absolute w-full h-full object-cover" 
                          style="width: 100%; height: 100%; object-fit: cover;"
                          src="<?php echo htmlspecialchars($foto_path); ?>" 
                          alt="<?php echo htmlspecialchars($nama_produk); ?>"
                          onerror="this.src='https://placehold.co/100x100/a9a2f7/ffffff?text=Produk'"
                      >
                  </div>
                  <div>
                      <p class="text-sm text-gray-500 dark:text-gray-400">Anda mengulas:</p>
                      <p class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                          <?php echo htmlspecialchars($nama_produk); ?>
                      </p>
                  </div>
              </div>
              
              <!-- Form -->
              <form action="proses_ulasan.php" method="POST" class="mt-6">
                <!-- Data tersembunyi untuk dikirim -->
                <input type="hidden" name="detail_id" value="<?php echo $detail_id; ?>">
                <input type="hidden" name="produk_id" value="<?php echo $produk_id; ?>">
                <input type="hidden" name="pembeli_id" value="<?php echo $pembeli_id; ?>">

                <!-- Rating Bintang -->
                <div class="text-center">
                    <span class="text-lg font-semibold text-gray-700 dark:text-gray-300">Beri Rating Anda</span>
                    <div class="star-rating mt-2">
                        <!-- 
                          Radio button dibalik (5 ke 1) dan CSS 'direction: rtl' 
                          akan membuatnya berfungsi dengan benar saat di-hover 
                        -->
                        <input type="radio" id="star5" name="rating" value="5" required/><label for="star5" title="5 stars">★</label>
                        <input type="radio" id="star4" name="rating" value="4"/><label for="star4" title="4 stars">★</label>
                        <input type="radio" id="star3" name="rating" value="3"/><label for="star3" title="3 stars">★</label>
                        <input type="radio" id="star2" name="rating" value="2"/><label for="star2" title="2 stars">★</label>
                        <input type="radio" id="star1" name="rating" value="1"/><label for="star1" title="1 star">★</label>
                    </div>
                </div>

                <!-- Komentar -->
                <label class="block text-sm mt-6">
                  <span class="text-gray-700 dark:text-gray-400">Tulis Ulasan Anda (Opsional)</span>
                  <textarea
                    name="komentar"
                    class="block w-full mt-1 text-sm dark:text-gray-300 dark:border-gray-600 dark:bg-gray-700 form-textarea focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:focus:shadow-outline-gray"
                    rows="5"
                    placeholder="Bagaimana kualitas produknya?"
                  ></textarea>
                </label>

                <div class="flex justify-end mt-6">
                  <button
                    type="submit"
                    class="w-full px-5 py-3 text-sm font-medium leading-5 text-center text-white transition-colors duration-150 bg-purple-600 border border-transparent rounded-lg active:bg-purple-600 hover:bg-purple-700 focus:outline-none focus:shadow-outline-purple"
                  >
                    Kirim Ulasan
                  </button>
                </div>
              </form>
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