<?php
// 1. MEMULAI SESSION & KONEKSI
session_start();
// Path: ../ (keluar dari User_Pembeli) -> public/pages/Connection.php
require_once __DIR__ . '/../public/pages/Connection.php';

// 2. KEAMANAN: Cek jika user sudah login dan adalah 'pembeli'
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'pembeli') {
    header("Location: ../public/pages/Login.php");
    exit;
}

$pembeli_id = (int) $_SESSION['user_id'];

// 3. PROSES AJAX (UPDATE QTY / DELETE ITEM)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    // --- AKSI: UPDATE JUMLAH ---
    if ($_POST['action'] === 'update_qty') {
        $keranjang_id = (int) ($_POST['keranjang_id'] ?? 0);
        $jumlah_kg = (int) ($_POST['jumlah_kg'] ?? 1);

        if ($keranjang_id === 0 || $jumlah_kg <= 0) {
            echo json_encode(['success' => false, 'message' => 'Permintaan tidak valid.']);
            exit;
        }
        
        // Cek stok dulu
        $stmt_stok = $conn->prepare("SELECT p.stok_kg FROM keranjang k JOIN produk p ON k.produk_id = p.id WHERE k.id = ? AND k.pembeli_id = ?");
        $stmt_stok->bind_param('ii', $keranjang_id, $pembeli_id);
        $stmt_stok->execute();
        $stok = $stmt_stok->get_result()->fetch_assoc()['stok_kg'] ?? 0;
        $stmt_stok->close();
        
        if ($jumlah_kg > $stok) {
            echo json_encode(['success' => false, 'message' => 'Stok tidak mencukupi (tersisa ' . $stok . ' Kg).', 'new_qty' => $stok]);
            exit;
        }

        // Update jumlah di keranjang
        $stmt_update = $conn->prepare("UPDATE keranjang SET jumlah_kg = ? WHERE id = ? AND pembeli_id = ?");
        $stmt_update->bind_param('iii', $jumlah_kg, $keranjang_id, $pembeli_id);
        $ok = $stmt_update->execute();
        $stmt_update->close();

        if ($ok) {
            echo json_encode(['success' => true, 'message' => 'Jumlah diperbarui.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui jumlah.']);
        }
        exit;
    }
    
    // --- AKSI: HAPUS ITEM ---
    if ($_POST['action'] === 'delete_item') {
        $keranjang_id = (int) ($_POST['keranjang_id'] ?? 0);
        if ($keranjang_id === 0) {
            echo json_encode(['success' => false, 'message' => 'ID item tidak valid.']);
            exit;
        }
        
        $stmt_delete = $conn->prepare("DELETE FROM keranjang WHERE id = ? AND pembeli_id = ?");
        $stmt_delete->bind_param('ii', $keranjang_id, $pembeli_id);
        $ok = $stmt_delete->execute();
        $stmt_delete->close();
        
        if ($ok) {
            echo json_encode(['success' => true, 'message' => 'Item dihapus dari keranjang.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus item.']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Aksi tidak diketahui.']);
    exit;
}

// 4. AMBIL DATA KERANJANG SPESIFIK (untuk tampilan halaman)
$cart_items_raw = [];
if (isset($conn) && $conn instanceof mysqli) {
    $sql = "SELECT 
                k.id AS keranjang_id, k.jumlah_kg,
                p.id AS produk_id, p.nama_produk, p.harga_kg, p.foto, p.stok_kg,
                (k.jumlah_kg * p.harga_kg) AS subtotal
            FROM keranjang k
            JOIN produk p ON k.produk_id = p.id
            WHERE k.pembeli_id = ?
            ORDER BY p.nama_produk ASC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $pembeli_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
   if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['selected'] = false; 
        $cart_items_raw[] = $row;
       } 
       $stmt->close(); // $stmt->close() harus di dalam 'if ($result)'
       }else { // <-- BENAR: Ini adalah pasangan untuk 'if ($result)'
       die("Error pada Query SQL: "
       . $conn->error);
      }
    } else { // <-- BENAR: Ini adalah pasangan untuk 'if (isset($conn) ...)'
    die("Koneksi database gagal.");
}

// Kurung kurawal ekstra di akhir sudah dihapus

?>
<html :class="{ 'theme-dark': dark }" x-data="pageData()" lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Keranjang Belanja - Windmill Dashboard</title>
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="./assets/css/tailwind.output.css" />
    <script
      src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js"
      defer
    ></script>
    <!-- init-alpine.js tidak diperlukan, digabung di bawah -->
  </head>
  <!-- PERBAIKAN: Pindahkan x-data="pageData()" ke tag body -->
  <body>
    <!-- Notifikasi AJAX (BARU) -->
    <div 
        x-show="notification.show" 
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform translate-y-2"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-300"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0 transform translate-y-2"
        x-text="notification.text"
        :class="{ 'bg-green-600': notification.type === 'success', 'bg-red-600': notification.type === 'error' }"
        class="fixed bottom-4 right-4 z-50 px-4 py-3 text-sm font-medium text-white rounded-lg shadow-lg" 
        role="alert"
        style="display: none;">
    </div>
    
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
           
          </ul>
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

            <!-- Cards, Buttons, Modals removed -->

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
      <!-- Mobile sidebar -->
      <!-- Backdrop -->
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
            href="#"
          >
            Windmill
          </a>
          <ul class="mt-6">
          
          </ul>
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

            <!-- Cards, Buttons, Modals removed -->

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
                  <path
                    d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"
                  ></path>
                  <path d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"></path>
                </svg>
                <span class="ml-4">Keranjang Produk</span>
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
      <div class="flex flex-col flex-1">
        <header class="z-10 py-4 bg-white shadow-md dark:bg-gray-800">
          <div
            class="container flex items-center justify-between h-full px-6 mx-auto text-purple-600 dark:text-purple-300"
          >
            <!-- Mobile hamburger -->
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
            <!-- Search input -->
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
              <!-- Theme toggler -->
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
              <!-- Notifications menu -->
             
               
              <!-- Profile menu -->
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
        
        <!-- ===== AWAL KONTEN MAIN ===== -->
        <main class="h-full pb-16 overflow-y-auto">
          <div class="container grid px-6 mx-auto">
            
            <h2 class="my-6 text-2xl font-semibold text-gray-700 dark:text-gray-200">
              Keranjang Belanja Anda
            </h2>
            
            <!-- PERBAIKAN: Layout 2 kolom -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
              
              <!-- Kolom Kiri: Daftar Produk (Tabel) -->
              <div class="w-full overflow-hidden rounded-lg shadow-xs md:col-span-2">
                <div class="w-full overflow-x-auto">
                  <table class="w-full whitespace-no-wrap">
                    <thead>
                      <tr class="text-xs font-semibold tracking-wide text-left text-gray-500 uppercase border-b dark:border-gray-700 bg-gray-50 dark:text-gray-400 dark:bg-gray-800">
                        <!-- PERBAIKAN: Tambah Checkbox "Pilih Semua" -->
                        <th class="px-1 py-3 text-center">
                          <input type="checkbox" @click="toggleSelectAll($event)" x-ref="selectAllCheckbox" />
                        </th>
                        <th class="px-4 py-3">Produk</th>
                        <th class="px-4 py-3">Harga</th>
                        <th class="px-4 py-3">Jumlah (Kg)</th>
                        <th class="px-4 py-3">Subtotal</th>
                        <th class="px-4 py-3">Aksi</th>
                      </tr>
                    </thead>
                    <tbody class="bg-white divide-y dark:divide-gray-700 dark:bg-gray-800">
                      
                      <!-- Jika keranjang kosong -->
                      <template x-if="cartItems.length === 0">
                        <tr class="text-gray-700 dark:text-gray-400 empty-cart-row">
                          <td colspan="6" class="px-4 py-3 text-center text-sm">
                            Keranjang Anda masih kosong.
                          </td>
                        </tr>
                      </template>
                      
                      <!-- Loop item keranjang -->
                      <template x-for="(item, index) in cartItems" :key="item.keranjang_id">
                        <tr class="text-gray-700 dark:text-gray-400">
                          <!-- PERBAIKAN: Checkbox per item -->
                          <td class="px-4 py-3 text-center">
                            <input type="checkbox" x-model="item.selected" @change="recalculateTotal()" />
                          </td>
                          <td class="px-4 py-3">
                            <div class="flex items-center text-sm">
                              <!-- PERBAIKAN: Ukuran foto w-12 h-12 (lebih kecil) dan object-cover -->
                              <div class="relative hidden w-12 h-12 mr-3 rounded-md md:block">
                                <img class="object-cover w-full h-full rounded-md" :src="'../User_petani/' + (item.foto || 'assets/img/profil/default.png')" alt="" loading="lazy" onerror="this.src='https://placehold.co/100x100/a9a2f7/ffffff?text=Produk'"/>
                              </div>
                              <div>
                                <p class="font-semibold product-name" x-text="item.nama_produk"></p>
                              </div>
                            </div>
                          </td>
                          <td class="px-4 py-3 text-sm">
                            Rp <span class="item-price" x-text="Number(item.harga_kg).toLocaleString('id-ID')"></span>
                          </td>
                          <td class="px-4 py-3 text-sm">
                            <div class="flex items-center gap-2">
                                <button 
                                    class="qty-change-btn px-2 py-1 bg-gray-200 dark:bg-gray-700 rounded text-sm" 
                                    @click.prevent="updateQty(item.keranjang_id, 'minus')"
                                    aria-label="Kurangi jumlah">
                                    −
                                </button>
                                <input 
                                    type="number" 
                                    class="qty-input w-16 text-center border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm" 
                                    :value="item.jumlah_kg" 
                                    min="1" 
                                    :max="item.stok_kg" 
                                    :data-id="item.keranjang_id"
                                    @change="updateQty(item.keranjang_id, 'input', $event.target.value)"
                                    aria-label="Jumlah">
                                <button 
                                    class="qty-change-btn px-2 py-1 bg-gray-200 dark:bg-gray-700 rounded text-sm"
                                    @click.prevent="updateQty(item.keranjang_id, 'plus')"
                                    aria-label="Tambah jumlah">
                                    +
                                </button>
                            </div>
                          </td>
                          <td class="px-4 py-3 text-sm font-semibold">
                            Rp <span class="item-subtotal" x-text="Number(item.jumlah_kg * item.harga_kg).toLocaleString('id-ID')"></span>
                          </td>
                          <td class="px-4 py-3 text-sm">
                            <button 
                                class="delete-item-btn flex items-center justify-between px-2 py-2 text-sm font-medium leading-5 text-red-600 rounded-lg dark:text-gray-400 focus:outline-none focus:shadow-outline-gray" 
                                aria-label="Hapus"
                                @click.prevent="deleteItem(item.keranjang_id, index)">
                              <svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                            </button>
                          </td>
                        </tr>
                      </template>
                    </tbody>
                  </table>
                </div>
              </div>
              
              <!-- Kolom Kanan: Ringkasan Belanja (Sticky) -->
              <div class="md:col-span-1">
                <!-- Wrapper untuk membuat 'sticky' -->
                <div class="sticky top-6">
                  <div class="p-4 bg-white rounded-lg shadow-md dark:bg-gray-800">
                      <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200">Ringkasan Belanja</h3>
                      <div class="flex justify-between items-center mt-4">
                          <span class="text-gray-600 dark:text-gray-400">Total Belanja</span>
                          <!-- PERBAIKAN: x-text="formatRupiah(totalBelanja)" -->
                          <span class="text-xl font-bold text-purple-600 dark:text-purple-400" id="total-belanja" x-text="formatRupiah(totalBelanja)">
                              Rp 0
                          </span>
                      </div>
                      <button 
                          id="checkout-btn"
                          @click="goToCheckout"
                          class="w-full px-4 py-3 mt-6 bg-purple-600 text-white text-sm font-semibold rounded-lg hover:bg-purple-700 transition-colors"
                          :disabled="getSelectedCount() === 0"
                          :class="{ 'opacity-50 cursor-not-allowed': getSelectedCount() === 0 }"
                      >
                         <!-- PERBAIKAN: Teks tombol dinamis -->
                         <span x-text="getSelectedCount() > 0 ? 'Lanjut ke Checkout (' + getSelectedCount() + ')' : 'Pilih Produk Dulu'"></span>
                      </button>
                  </div>
                </div>
              </div>

            </div>

          </div>
        </main>
        <!-- ===== AKHIR KONTEN MAIN ===== -->
      </div>
    </div>
    
    <!-- PERBAIKAN: Menggabungkan fungsi data() dan logika keranjang -->
    <script>
      function pageData() {
        // --- Bagian 1: Logika Sidebar/Header ---
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
        
        // --- Bagian 2: Data PHP untuk Halaman ---
        const cartItemsPHP = <?php echo json_encode($cart_items_raw); ?>;
        
        // --- Bagian 3: Mengembalikan Objek Gabungan ---
        return {
          // Properti Sidebar
          dark: getThemeFromLocalStorage(),
          isSideMenuOpen: false,
          isNotificationsMenuOpen: false,
          isProfileMenuOpen: false,
          
          // Properti Halaman Keranjang
          cartItems: cartItemsPHP,
          totalBelanja: 0,
          notification: { text: '', type: 'success', show: false },

          // --- Metode Gabungan ---
          
          // Metode Sidebar
          toggleTheme() { this.dark = !this.dark; setThemeToLocalStorage(this.dark); },
          toggleSideMenu() { this.isSideMenuOpen = !this.isSideMenuOpen; },
          closeSideMenu() { this.isSideMenuOpen = false; },
          toggleNotificationsMenu() { this.isNotificationsMenuOpen = !this.isNotificationsMenuOpen; },
          closeNotificationsMenu() { this.isNotificationsMenuOpen = false; },
          toggleProfileMenu() { this.isProfileMenuOpen = !this.isProfileMenuOpen; },
          closeProfileMenu() { this.isProfileMenuOpen = false; },

          // Metode Halaman Keranjang
          init() {
            this.recalculateTotal(); // Hitung total saat halaman dimuat
          },
          
          showNotification(message, isSuccess) {
            this.notification.text = message;
            this.notification.type = isSuccess ? 'success' : 'error';
            this.notification.show = true;
            setTimeout(() => { this.notification.show = false; }, 3000);
          },
          
          formatRupiah(angka) {
            return 'Rp ' + Number(angka).toLocaleString('id-ID');
          },
          
          // PERBAIKAN: Hitung ulang total HANYA untuk item yang 'selected'
          recalculateTotal() {
            let newTotal = 0;
            let allSelected = this.cartItems.length > 0;
            this.cartItems.forEach(item => {
                if (item.selected) {
                    newTotal += (parseInt(item.jumlah_kg) * parseInt(item.harga_kg));
                } else {
                    allSelected = false; // Jika ada satu saja yang tidak terpilih
                }
            });
            this.totalBelanja = newTotal;
            
            // Update checkbox "Pilih Semua"
            // Update checkbox "Pilih Semua"
            if (this.$refs.selectAllCheckbox) {
                // Langsung sinkronkan status 'checked' dengan variabel 'allSelected'
                this.$refs.selectAllCheckbox.checked = allSelected;
            }
          },
          
          // PERBAIKAN: Fungsi baru untuk checkbox "Pilih Semua"
          toggleSelectAll(event) {
            let checked = event.target.checked;
            this.cartItems.forEach(item => item.selected = checked);
            this.recalculateTotal();
          },

          // PERBAIKAN: Fungsi baru untuk tombol checkout
          getSelectedCount() {
            return this.cartItems.filter(item => item.selected).length;
          },
          
          async updateQty(keranjangId, action, inputValue = null) {
            let item = this.cartItems.find(i => i.keranjang_id == keranjangId);
            if (!item) return;

            let qty = parseInt(item.jumlah_kg);
            const stok = parseInt(item.stok_kg);

            if (action === 'plus') {
                if (qty < stok) qty++;
                else this.showNotification('Stok tidak mencukupi', false);
            } else if (action === 'minus') {
                if (qty > 1) qty--;
            } else if (action === 'input') {
                let newQty = parseInt(inputValue);
                if (isNaN(newQty) || newQty < 1) {
                    qty = 1;
                } else if (newQty > stok) {
                    qty = stok;
                    this.showNotification('Stok tidak mencukupi (tersisa ' + stok + ' Kg).', false);
                } else {
                    qty = newQty;
                }
            }
            
            // Jika tidak ada perubahan, jangan kirim AJAX
            if (qty === parseInt(item.jumlah_kg)) {
                document.querySelector(`input[data-id="${keranjangId}"]`).value = qty; // Pastikan input sesuai
                return;
            }

            // Kirim AJAX
            const formData = new FormData();
            formData.append('action', 'update_qty');
            formData.append('keranjang_id', keranjangId);
            formData.append('jumlah_kg', qty);
            
            try {
                const response = await fetch('KeranjangProduk.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (!data.success && data.new_qty) {
                    // Jika stok tidak cukup, server memaksa kembali
                     item.jumlah_kg = data.new_qty;
                     this.showNotification(data.message, false);
                } else if (data.success) {
                    // Update data lokal jika sukses
                    item.jumlah_kg = qty;
                    this.showNotification(data.message, true);
                } else {
                    // Tampilkan error lain
                    this.showNotification(data.message, false);
                }
                
                // Setel ulang nilai input di DOM untuk memastikan konsistensi
                document.querySelector(`input[data-id="${keranjangId}"]`).value = item.jumlah_kg;
                item.subtotal = item.jumlah_kg * item.harga_kg;
                this.recalculateTotal(); // Hitung ulang total

            } catch (error) {
                console.error('Error:', error);
                this.showNotification('Terjadi kesalahan. Coba lagi.', false);
            }
          },
          
          async deleteItem(keranjangId, index) {
            if (!confirm('Apakah Anda yakin ingin menghapus item ini dari keranjang?')) return;
            
            const formData = new FormData();
            formData.append('action', 'delete_item');
            formData.append('keranjang_id', keranjangId);
            
            try {
                const response = await fetch('KeranjangProduk.php', { method: 'POST', body: formData });
                const data = await response.json();
                
                this.showNotification(data.message, data.success);
                
                if (data.success) {
                    this.cartItems.splice(index, 1); // Hapus dari array
                    this.recalculateTotal(); // Hitung ulang total
                }
            } catch (error) {
                console.error('Error:', error);
                this.showNotification('Terjadi kesalahan. Coba lagi.', false);
            }
          },
          
          // PERBAIKAN: Mengirim ID item yang dipilih ke Checkout
          goToCheckout() {
            let selectedIds = this.cartItems
                                .filter(item => item.selected)
                                .map(item => item.keranjang_id);
            
            if (selectedIds.length === 0) {
                this.showNotification('Pilih minimal satu produk untuk di-checkout.', 'error');
                return;
            }
            
            // Arahkan ke Checkout.php dengan ID item sebagai parameter URL
            window.location.href = 'Chekout.php?items=' + selectedIds.join(',');
          }
        }
      }
      
      // Panggil init setelah Alpine siap
      document.addEventListener('alpine:init', () => {
        Alpine.data('pageData', pageData);
      });
    </script>
  </body>
</html>