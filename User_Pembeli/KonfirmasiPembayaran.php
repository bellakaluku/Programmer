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

// 3. Ambil ID Pesanan dari URL
$pesanan_id = (int) ($_GET['id'] ?? 0);
if ($pesanan_id === 0) {
    die("Error: ID Pesanan tidak ditemukan.");
}

// 4. Ambil detail pesanan untuk ditampilkan
// Digunakan struktur tabel 'pesanan' LAMA Anda (total_harga)
$sql_pesanan = "SELECT total_harga, status_pesanan FROM pesanan WHERE id = ? AND pembeli_id = ?";
$stmt = $conn->prepare($sql_pesanan);
$stmt->bind_param('ii', $pesanan_id, $pembeli_id);
$stmt->execute();
$result = $stmt->get_result();
$pesanan = $result->fetch_assoc();
$stmt->close();

if (!$pesanan) {
    die("Error: Pesanan tidak ditemukan atau bukan milik Anda.");
}

// Jika pesanan sudah lunas atau sedang diverifikasi, tendang
if ($pesanan['status_pesanan'] !== 'Menunggu Pembayaran') {
     echo "<script>alert('Pesanan ini sudah dibayar atau sedang diverifikasi.'); window.location='RiwayatTransaksi.php';</script>";
     exit;
}

$total_bayar = $pesanan['total_harga']; // Menggunakan 'total_harga' sesuai DB Anda

// Ambil pesan error dari session jika ada
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']);

?>
<!DOCTYPE html>
<html :class="{ 'theme-dark': dark }" x-data="data()" lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Konfirmasi Pembayaran - TaniMaju</title>
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
           <div class="container flex items-center justify-end h-full px-6 mx-auto text-purple-600 dark:text-purple-300">
                <ul class="flex items-center flex-shrink-0 space-x-6">
                    <li class="flex">
                        <button class="rounded-md focus:outline-none focus:shadow-outline-purple" @click="toggleTheme" aria-label="Toggle color mode">
                            <template x-if="!dark"><svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg></template>
                            <template x-if="dark"><svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"></path></svg></template>
                        </button>
                    </li>
                    <li class="relative">
                        <button class="align-middle rounded-full focus:shadow-outline-purple focus:outline-none" @click="toggleProfileMenu" @keydown.escape="closeProfileMenu" aria-label="Account" aria-haspopup="true">
                            <img class="object-cover w-8 h-8 rounded-full" src="https://images.unsplash.com/photo-1502378735452-bc7d86632805?..." alt="" aria-hidden="true"/>
                        </button>
                        <template x-if="isProfileMenuOpen">
                            <ul x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" @click.away="closeProfileMenu" @keydown.escape="closeProfileMenu" class="absolute right-0 w-56 p-2 mt-2 space-y-2 text-gray-600 bg-white border border-gray-100 rounded-md shadow-md dark:border-gray-700 dark:text-gray-300 dark:bg-gray-700" aria-label="submenu">
                                <li class="flex">
                                    <a class="inline-flex items-center w-full px-2 py-1 text-sm font-semibold transition-colors duration-150 rounded-md hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-800 dark:hover:text-gray-200" href="profil.php">
                                        <svg class="w-4 h-4 mr-3" aria-hidden="true" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                        <span>Profile</span>
                                    </a>
                                </li>
                                <li class="flex">
                                    <a class="inline-flex items-center w-full px-2 py-1 text-sm font-semibold transition-colors duration-150 rounded-md hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-800 dark:hover:text-gray-200" href="../public/pages/LogOut.php">
                                        <svg class="w-4 h-4 mr-3" aria-hidden="true" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor"><path d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
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
          <div class="container max-w-xl mx-auto px-6 py-12">
            
            <h2 class="my-6 text-2xl font-semibold text-gray-700 dark:text-gray-200">
              Konfirmasi Pembayaran
            </h2>

            <!-- Info Pembayaran -->
            <div class="px-6 py-4 mb-8 bg-white rounded-lg shadow-md dark:bg-gray-800">
                <h4 class="mb-4 text-lg font-semibold text-gray-600 dark:text-gray-300">
                    Detail Tagihan
                </h4>
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm text-gray-600 dark:text-gray-400">ID Pesanan:</span>
                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200">#<?php echo $pesanan_id; ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-lg font-semibold text-gray-600 dark:text-gray-400">Total Pembayaran:</span>
                    <span class="text-xl font-bold text-purple-600 dark:text-purple-400">
                        Rp <?php echo number_format($total_bayar, 0, ',', '.'); ?>
                    </span>
                </div>
                <hr class="my-4 dark:border-gray-700">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Silakan transfer jumlah yang sama persis ke rekening berikut:
                    <br>
                    <span class="font-medium text-gray-800 dark:text-gray-200">Bank BCA: 123-456-7890</span> a/n TaniMaju Indonesia
                    <br>
                    <span class="font-medium text-gray-800 dark:text-gray-200">Bank Mandiri: 987-654-3210</span> a/n TaniMaju Indonesia
                    <br><br>
                    Anda juga bisa scan QRIS di bawah ini (jika tersedia).
                </p>
            </div>
            
            <!-- Notifikasi Error (jika ada) -->
            <?php if ($error_message): ?>
                <div class="px-4 py-3 mb-4 text-sm font-medium text-white bg-red-600 rounded-lg" role="alert">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Form Upload Bukti -->
            <div class="px-6 py-4 bg-white rounded-lg shadow-md dark:bg-gray-800">
              <form action="proses_konfirmasi.php" method="POST" enctype="multipart/form-data">
                <!-- Kirim ID Pesanan secara tersembunyi -->
                <input type="hidden" name="pesanan_id" value="<?php echo $pesanan_id; ?>">
                <input type="hidden" name="jumlah_transfer" value="<?php echo $total_bayar; ?>">

                <label class="block text-sm mt-4">
                  <span class="text-gray-700 dark:text-gray-400">Metode Pembayaran Anda</span>
                  <select
                    name="metode"
                    class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 form-select focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300 dark:focus:shadow-outline-gray"
                    required
                  >
                    <option value="">Pilih Metode yang Anda Gunakan</option>
                    <option value="Transfer BCA">Transfer Bank BCA</option>
                    <option value="Transfer Mandiri">Transfer Bank Mandiri</option>
                    <option value="QRIS">QRIS (GoPay, DANA, OVO, dll)</option>
                    <option value="Lainnya">Bank Lainnya</option>
                  </select>
                </label>
                
                <label class="block text-sm mt-4">
                  <span class="text-gray-700 dark:text-gray-400">Tanggal & Waktu Transfer</span>
                  <input
                    name="tgl_transfer"
                    type="datetime-local"
                    class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300 dark:focus:shadow-outline-gray form-input"
                    required
                  />
                </label>

                <label class="block mt-4 text-sm">
                    <span class="text-gray-700 dark:text-gray-400">Upload Bukti Transfer (Screenshot)</span>
                     <input
                      type="file"
                      name="bukti_transfer"
                      accept="image/jpeg,image/png,image/jpg"
                      class="block w-full mt-1 text-sm dark:text-gray-300"
                      required
                    />
                     <span class="text-xs text-gray-500 dark:text-gray-400">Format: JPG, JPEG, atau PNG. Maks 2MB.</span>
                </label>

                <div class="flex justify-end mt-6">
                  <button
                    type="submit"
                    class="w-full px-5 py-3 text-sm font-medium leading-5 text-center text-white transition-colors duration-150 bg-purple-600 border border-transparent rounded-lg active:bg-purple-600 hover:bg-purple-700 focus:outline-none focus:shadow-outline-purple"
                  >
                    Konfirmasi Pembayaran Saya
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