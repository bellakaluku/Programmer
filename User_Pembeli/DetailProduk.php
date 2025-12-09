<?php
// 1. MEMULAI SESSION & KONEKSI
session_start();
require_once __DIR__ . '/../public/pages/Connection.php';

// --- Cek status login (tanpa redirect) ---
$is_pembeli = (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'pembeli');
$pembeli_id = (int) ($_SESSION['user_id'] ?? 0);
// --- Selesai Cek ---

$product_id = (int) ($_GET['id'] ?? 0); // Ambil product_id dari URL

// 3. PROSES FORM (TAMBAH KERANJANG / BELI SEKARANG)
// Ini sekarang menangani POST dari form di halaman ini sendiri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    
    // Jika user tidak login, paksa dia login dulu
    if (!$is_pembeli) {
        // Simpan URL ini untuk redirect kembali setelah login
        $request_uri = urlencode($_SERVER['REQUEST_URI']);
        header("Location: ../public/pages/Login.php?redirect=" . $request_uri);
        exit;
    }
    
    $produk_id_form = (int) ($_POST['produk_id'] ?? 0);
    $jumlah_kg = (int) ($_POST['jumlah_kg'] ?? 1);
    $action_type = $_POST['action_type'];

    // Pastikan produk_id dari form sama dengan dari URL
    if ($produk_id_form !== $product_id || $jumlah_kg <= 0) {
        // MODIFIKASI: Kirim error JSON jika ini AJAX
        if (isset($_POST['is_ajax']) && $_POST['is_ajax'] === '1') {
             header('Content-Type: application/json');
             echo json_encode(['success' => false, 'message' => 'Permintaan tidak valid.']);
             exit;
        }
        $_SESSION['error_message'] = "Permintaan tidak valid.";
        header("Location: DetailProduk.php?id=" . $product_id);
        exit;
    }

    // 4.1. Cek Stok Produk
    $stmt_stok = $conn->prepare("SELECT stok_kg FROM produk WHERE id = ?");
    $stmt_stok->bind_param('i', $product_id);
    $stmt_stok->execute();
    $stok = $stmt_stok->get_result()->fetch_assoc()['stok_kg'] ?? 0;
    $stmt_stok->close();

    if ($jumlah_kg > $stok) {
        // MODIFIKASI: Kirim error JSON jika ini AJAX
        if (isset($_POST['is_ajax']) && $_POST['is_ajax'] === '1') {
             header('Content-Type: application/json');
             echo json_encode(['success' => false, 'message' => "Stok tidak mencukupi (tersisa $stok Kg)."]);
             exit;
        }
        $_SESSION['error_message'] = "Stok tidak mencukupi (tersisa $stok Kg)."; 
        header("Location: DetailProduk.php?id=" . $product_id);
        exit;
    }

    // 4.2. Cek apakah produk sudah ada di keranjang
    $stmt_cek = $conn->prepare("SELECT id, jumlah_kg FROM keranjang WHERE pembeli_id = ? AND produk_id = ?");
    $stmt_cek->bind_param('ii', $pembeli_id, $product_id);
    $stmt_cek->execute();
    $item_keranjang = $stmt_cek->get_result()->fetch_assoc();
    $stmt_cek->close();

    $keranjang_id_target = null;

    if ($item_keranjang) {
        // --- PRODUK SUDAH ADA: UPDATE QTY ---
        $keranjang_id_target = $item_keranjang['id'];
        $jumlah_baru = $jumlah_kg; 
        if ($jumlah_baru > $stok) $jumlah_baru = $stok;

        $stmt_update = $conn->prepare("UPDATE keranjang SET jumlah_kg = ? WHERE id = ?");
        $stmt_update->bind_param('ii', $jumlah_baru, $keranjang_id_target);
        $stmt_update->execute();
        $stmt_update->close();

    } else {
        // --- PRODUK BELUM ADA: INSERT BARU ---
        $stmt_insert = $conn->prepare("INSERT INTO keranjang (pembeli_id, produk_id, jumlah_kg) VALUES (?, ?, ?)");
        $stmt_insert->bind_param('iii', $pembeli_id, $product_id, $jumlah_kg);
        $stmt_insert->execute();
        $keranjang_id_target = mysqli_insert_id($conn);
        $stmt_insert->close();
    }

    // ===================================================================
    // --- (MODIFIKASI 1) LOGIKA REDIRECT DIUBAH ---
    // ===================================================================
    if ($action_type === 'beli_sekarang') {
        // "Beli Sekarang" selalu redirect (kirim form biasa)
        header("Location: Chekout.php?items=" . $keranjang_id_target);
        exit;
    } else { 
        // Jika "Tambah Keranjang", cek apakah ini permintaan AJAX?
        if (isset($_POST['is_ajax']) && $_POST['is_ajax'] === '1') {
            // INI BLOK BARU UNTUK AJAX
            // Kirim respon JSON, jangan redirect
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true, 
                'message' => 'Produk berhasil ditambahkan ke keranjang!'
            ]);
            exit; // Wajib exit di sini
        } else {
            // Ini adalah fallback jika JS gagal (kirim form biasa)
            $_SESSION['success_message'] = "Produk berhasil ditambahkan ke keranjang!";
            header("Location: KeranjangProduk.php");
            exit;
        }
    }
    // --- AKHIR MODIFIKASI 1 ---
}
// --- AKHIR PROSES FORM ---


// 6. AMBIL DATA PRODUK SPESIFIK (untuk tampilan halaman)
if ($product_id === 0) {
    die("Produk tidak valid. Pastikan Anda mengklik dari halaman katalog.");
}

$produk = null;
if (isset($conn) && $conn instanceof mysqli) {
    // ... (Query ambil data produk Anda tidak berubah) ...
    $sql = "SELECT 
                p.id, p.nama_produk, p.keterangan, p.stok_kg, p.harga_kg, p.foto,
                u.nama AS nama_petani
            FROM produk p
            JOIN users u ON p.petani_id = u.id
            WHERE p.id = ? AND p.stok_kg > 0 AND p.status = 'aktif'";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $produk = $result->fetch_assoc();
    $stmt->close();

    if ($produk === null) {
        die("Produk tidak ditemukan atau stok habis.");
    }
    
    // ... (Query ambil ulasan Anda tidak berubah) ...
    $ulasan = [];
    $sql_ulasan = "SELECT 
                        u.rating, 
                        u.komentar, 
                        u.tgl_ulasan, 
                        us.nama AS nama_pembeli
                    FROM ulasan u
                    JOIN users us ON u.pembeli_id = us.id
                    WHERE u.produk_id = ?
                    ORDER BY u.tgl_ulasan DESC";
    $stmt_ulasan = $conn->prepare($sql_ulasan);
    $stmt_ulasan->bind_param('i', $product_id);
    $stmt_ulasan->execute();
    $result_ulasan = $stmt_ulasan->get_result();
    while ($row = $result_ulasan->fetch_assoc()) {
        $ulasan[] = $row;
    }
    $stmt_ulasan->close();
    
    // ... (Query ambil rating Anda tidak berubah) ...
    $sql_rating = "SELECT 
                        COUNT(id) as total_ulasan, 
                        AVG(rating) as avg_rating 
                    FROM ulasan 
                    WHERE produk_id = ?";
    $stmt_rating = $conn->prepare($sql_rating);
    $stmt_rating->bind_param('i', $product_id);
    $stmt_rating->execute();
    $rating_stats = $stmt_rating->get_result()->fetch_assoc();
    $stmt_rating->close();
    
    $total_ulasan = (int)($rating_stats['total_ulasan'] ?? 0);
    $avg_rating = (float)($rating_stats['avg_rating'] ?? 0);

} else {
    die("Koneksi database gagal.");
}

// Set variabel untuk tampilan
$photo = $produk['foto'] ? '../User_petani/' . $produk['foto'] : 'https://placehold.co/400x300/a9a2f7/ffffff?text=Produk';
$price = (int)$produk['harga_kg'];
$stock = (int)$produk['stok_kg'];

// Ambil pesan notifikasi dari session jika ada
$error_message = $_SESSION['error_message'] ?? null;
$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['error_message'], $_SESSION['success_message']);

?>
<!DOCTYPE html>
<html :class="{ 'theme-dark': dark }" x-data="data()" lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Detail Produk: <?php echo htmlspecialchars($produk['nama_produk']); ?></title>
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="./assets/css/tailwind.output.css" />
    <script
      src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js"
      defer
    ></script>
    <script src="./assets/js/init-alpine.js" defer></script>
    
    <style>
        .star-display {
            display: inline-flex;
            color: #d1d5db; /* gray-300 */
            position: relative;
            font-size: 1.25rem; /* text-xl */
        }
        .star-display-inner {
            position: absolute;
            top: 0;
            left: 0;
            white-space: nowrap;
            overflow: hidden;
            color: #f59e0b; /* yellow-500 */
        }
    </style>
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
              <a
                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
                href="RiwayatTransaksi.php"
              >
                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor"><path d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                <span class="ml-4">Riwayat Transaksi</span>
              </a>
            </li>
          </ul>
        </div>
      </aside>
      
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
              <a
                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
                href="RiwayatTransaksi.php"
              >
                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor"><path d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                <span class="ml-4">Riwayat Transaksi</span>
              </a>
            </li>
          </ul>
        </div>
      </aside>

      <div class="flex flex-col flex-1 w-full">
        <header class="z-10 py-4 bg-white shadow-md dark:bg-gray-800">
          <div class="container flex items-center justify-between h-full px-6 mx-auto text-purple-600 dark:text-purple-300">
            <button class="p-1 -ml-1 mr-5 rounded-md md:hidden focus:outline-none focus:shadow-outline-purple" @click="toggleSideMenu" aria-label="Menu">
              <svg class="w-6 h-6" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path></svg>
            </button>
            <div class="flex justify-center flex-1 lg:mr-32">
            </div>
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
                  <ul x-transition:leave="transition ease-in duration-150" ... class="absolute right-0 w-56 p-2 mt-2 ...">
                    <li class="flex">
                      <a class="inline-flex items-center w-full ..." href="profil.php">
                        <svg class="w-4 h-4 mr-3" ...></svg>
                        <span>Profile</span>
                      </a>
                    </li>
                    <li class="flex">
                       <a class="inline-flex items-center w-full ..." href="../public/pages/LogOut.php"  >
                        <svg class="w-4 h-4 mr-3" ...></svg>
                        <span>Log out</span>
                      </a>
                    </li>
                  </ul>
                </template>
              </li>
            </ul>
          </div>
        </header>
        
        <main class="h-full pb-16 overflow-y-auto" x-data="detailPageData()">
          <div class="container grid px-6 mx-auto">
            
            <div class="my-6">
              <a href="ProdukKKatalog.php" class="flex items-center text-sm font-medium text-purple-600 hover:underline">
                  <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                  Kembali ke Katalog
              </a>
            </div>
            
            <?php if ($error_message): ?>
                <div class="px-4 py-3 mb-4 text-sm font-medium text-white bg-red-600 rounded-lg" role="alert">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            <?php if ($success_message): ?>
                 <div class="px-4 py-3 mb-4 text-sm font-medium text-white bg-green-600 rounded-lg" role="alert">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <div x-show="notif.show"
                 x-transition
                 :class="{ 'bg-green-600': notif.type === 'success', 'bg-red-600': notif.type === 'error' }"
                 class="px-4 py-3 mb-4 text-sm font-medium text-white rounded-lg"
                 role="alert"
                 x-text="notif.message"
                 style="display: none;">
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-0">
                  
                  <div>
                      <img src="<?php echo htmlspecialchars($photo); ?>" alt="<?php echo htmlspecialchars($produk['nama_produk']); ?>" class="w-full h-80 object-cover" onerror="this.src='https://placehold.co/600x600/a9a2f7/ffffff?text=Error'">
                  </div>

                  <div class="p-6 flex flex-col">
                      <h1 class="text-3xl font-semibold text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($produk['nama_produk']); ?></h1>
                      <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                          Oleh: <span class="font-medium text-purple-600"><?php echo htmlspecialchars($produk['nama_petani']); ?></span>
                      </p>
                      
                      <div class="flex items-center mt-4">
                          <div class="star-display" title="<?php echo number_format($avg_rating, 1); ?> dari 5 bintang">
                              <span>★★★★★</span>
                              <div class="star-display-inner" style="width: <?php echo ($avg_rating / 5) * 100; ?>%">
                                  <span>★★★★★</span>
                              </div>
                          </div>
                          <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">
                              <?php echo number_format($avg_rating, 1); ?> 
                              (<?php echo $total_ulasan; ?> Ulasan)
                          </span>
                      </div>

                      <p class="text-4xl font-bold text-purple-600 dark:text-purple-400 mt-4">
                          Rp <?php echo number_format($price, 0, ',', '.'); ?>
                          <span class="text-lg font-normal text-gray-500 dark:text-gray-400">/ kg</span>
                      </p>

                      <hr class="my-6 dark:border-gray-700">

                      <div>
                          <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-2">Deskripsi Produk</h3>
                          <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                              <?php echo nl2br(htmlspecialchars($produk['keterangan'] ?: 'Tidak ada keterangan.')); ?>
                          </p>
                      </div>
                      
                      <p class="text-sm text-green-600 dark:text-green-400 mt-4">
                          Stok Tersedia: <span x-text="currentStock"><?php echo $stock; ?></span> Kg
                      </p>

                      <div class="mt-auto pt-6">
                        <form method="POST" action="DetailProduk.php?id=<?php echo $product_id; ?>">
                            
                            <input type="hidden" name="produk_id" value="<?php echo $product_id; ?>">
                            <input type="hidden" name="action_type" x-model="actionType">

                            <div class="flex items-center gap-2 mb-4">
                                <span class="text-sm text-gray-700 dark:text-gray-300 mr-2">Jumlah (Kg):</span>
                                <button type="button" @click="decrement()" class="px-3 py-1 bg-gray-200 dark:bg-gray-700 rounded text-lg" aria-label="Kurangi jumlah">−</button>
                                
                                <input name="jumlah_kg" 
                                       type="number" 
                                       class="w-16 text-center border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded text-sm" 
                                       x-model.number="quantity" 
                                       min="1" 
                                       :max="maxStock" 
                                       aria-label="Jumlah"
                                       required>
                                       
                                <button type="button" @click="increment()" class="px-3 py-1 bg-gray-200 dark:bg-gray-700 rounded text-sm" aria-label="Tambah jumlah">+</button>
                            </div>

                            <?php if ($is_pembeli): ?>
                                <div class="grid grid-cols-2 gap-4">
    
<button
    type="button"
    @click="addToCart()"
    class="w-full px-4 py-3 text-sm font-semibold rounded-lg
            text-green-700 hover:text-green-800 
            dark:text-green-400 dark:hover:text-green-300
            transition-colors
            flex items-center justify-center space-x-2"
>
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
    </svg>
    <span>+ Keranjang</span>
</button>

                                    <button 
                                        type="submit"
                                        @click="actionType = 'beli_sekarang'" 
                                        class="w-full px-4 py-3 text-sm font-semibold rounded-lg
                                               bg-purple-600 text-white
                                               hover:bg-purple-700 transition-colors">
                                        Beli Sekarang
                                    </button>

                                </div>

                            <?php else: ?>
                                <a href="../public/pages/Login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>"
                                   class="block text-center w-full px-4 py-3 bg-purple-600 text-white text-sm font-semibold rounded-lg hover:bg-purple-700 transition-colors">
                                    Login untuk Beli
                                </a>
                            <?php endif; ?>
                            </form>
                      </div>
                  </div>
              </div>
            </div>
            
            <div class="mt-8">
                <h3 class="text-xl font-semibold text-gray-700 dark:text-gray-200 mb-4">
                    Ulasan Produk (<?php echo $total_ulasan; ?>)
                </h3>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md">
                    <div class="divide-y dark:divide-gray-700">
                        <?php if (empty($ulasan)): ?>
                            <p class="text-center text-gray-500 dark:text-gray-400 p-6">
                                Belum ada ulasan untuk produk ini.
                            </p>
                        <?php else: ?>
                            <?php foreach ($ulasan as $u): ?>
                                <div class="p-4">
                                    <div class="flex items-center mb-2">
                                        <span class="font-semibold text-gray-700 dark:text-gray-200">
                                            <?php echo htmlspecialchars($u['nama_pembeli']); ?>
                                        </span>
                                    </div>
                                    <div class="flex items-center mb-2">
                                        <div class="star-display" title="<?php echo $u['rating']; ?> dari 5 bintang">
                                            <span>★★★★★</span>
                                            <div class="star-display-inner" style="width: <?php echo ($u['rating'] / 5) * 100; ?>%">
                                                <span>★★★★★</span>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-1">
                                        <?php echo nl2br(htmlspecialchars($u['komentar'])); ?>
                                    </p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500">
                                        <?php echo date('d M Y, H:i', strtotime($u['tgl_ulasan'])); ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

          </div>
        </main>
        
      </div>
    </div>
    
    <script>
      // Fungsi data() AlpineJS untuk sidebar/header
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
      
      // Fungsi Alpine baru untuk Halaman Detail
      function detailPageData() {
        return {
          quantity: 1,
          maxStock: <?php echo $stock; ?>,
          currentStock: <?php echo $stock; ?>,
          actionType: 'beli_sekarang', // Ini HANYA untuk tombol "Beli Sekarang"
          notif: { show: false, message: '', type: 'success' }, // Untuk notifikasi AJAX

          increment() {
            if (this.quantity < this.maxStock) {
              this.quantity++;
            }
          },
          decrement() {
            if (this.quantity > 1) {
              this.quantity--;
            }
          },

          // FUNGSI BARU UNTUK MENAMPILKAN NOTIFIKASI
          showNotif(type, message) {
              this.notif.message = message;
              this.notif.type = type;
              this.notif.show = true;
              
              // Sembunyikan setelah 3 detik
              setTimeout(() => {
                  this.notif.show = false;
              }, 3000);
          },

          // FUNGSI BARU UNTUK AJAX "TAMBAH KERANJANG"
          async addToCart() {
              // Tampilkan notifikasi loading
              this.showNotif('success', 'Menambahkan ke keranjang...'); // Pakai 'success' (hijau) sbg loading

              const formData = new FormData();
              formData.append('is_ajax', '1'); // Penanda ini AJAX
              formData.append('action_type', 'tambah_keranjang');
              formData.append('produk_id', <?php echo $product_id; ?>);
              formData.append('jumlah_kg', this.quantity);

              try {
                  // Kirim data ke halaman ini sendiri
                  const response = await fetch('DetailProduk.php?id=<?php echo $product_id; ?>', {
                      method: 'POST',
                      body: formData
                  });
                  
                  // Cek jika respon bukan JSON atau error
                  if (!response.ok) {
                      throw new Error('Respon server error.');
                  }

                  const data = await response.json();

                  if (data.success) {
                      this.showNotif('success', data.message);
                  } else {
                      // Tampilkan pesan error dari PHP (misal: "Stok tidak cukup")
                      this.showNotif('error', data.message || 'Gagal menambahkan produk.');
                  }

              } catch (error) {
                  console.error('Error:', error);
                  this.showNotif('error', 'Terjadi kesalahan. Coba lagi nanti.');
              }
          }
        }
      }

      // Inisialisasi Alpine
      document.addEventListener('alpine:init', () => {
          Alpine.data('data', data);
          Alpine.data('detailPageData', detailPageData);
      });
    </script>
    </body>
</html>