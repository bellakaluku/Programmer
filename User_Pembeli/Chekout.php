<?php
// --- DEBUGGING: Aktifkan ini jika layar masih putih ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ----------------------------------------------------

// 1. MEMULAI SESSION & KONEKSI
session_start();

// Pastikan path file Connection benar. Gunakan realpath untuk memastikan file ditemukan.
$connectionPath = __DIR__ . '/../public/pages/Connection.php';
if (!file_exists($connectionPath)) {
    die("Error: File koneksi tidak ditemukan di: " . $connectionPath);
}
require_once $connectionPath;

// 2. KEAMANAN: Cek jika user sudah login dan adalah 'pembeli'
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'pembeli') {
    header("Location: ../public/pages/Login.php");
    exit;
}

$pembeli_id = (int) $_SESSION['user_id'];

// 3. PROSES AJAX (MEMBUAT PESANAN)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    if ($_POST['action'] === 'place_order') {
        $alamat_id = (int) ($_POST['alamat_id'] ?? 0);
        $metode_pembayaran = trim($_POST['metode_pembayaran'] ?? '');
        $item_ids_str = $_POST['item_ids'] ?? '';

        if ($alamat_id === 0 || empty($metode_pembayaran)) {
            echo json_encode(['success' => false, 'message' => 'Silakan pilih alamat dan metode pembayaran.']);
            exit;
        }

        $conn->begin_transaction();

        try {
            // A. AMBIL ITEM KERANJANG
            $params = [$pembeli_id];
            $types = 'i';
            
            $sql_cart = "SELECT k.id, k.produk_id, k.jumlah_kg, p.harga_kg, p.stok_kg, p.petani_id 
                         FROM keranjang k 
                         JOIN produk p ON k.produk_id = p.id
                         WHERE k.pembeli_id = ?";

            if (!empty($item_ids_str)) {
                $item_ids_array = explode(',', $item_ids_str);
                $item_ids_array = array_map('intval', $item_ids_array);
                $placeholders = implode(',', array_fill(0, count($item_ids_array), '?'));
                $sql_cart .= " AND k.id IN ($placeholders)";
                $types .= str_repeat('i', count($item_ids_array));
                $params = array_merge($params, $item_ids_array);
            }
            
            $sql_cart .= " FOR UPDATE";
            $stmt_cart = $conn->prepare($sql_cart);
            $stmt_cart->bind_param($types, ...$params);
            $stmt_cart->execute();
            $result_cart = $stmt_cart->get_result();

            if ($result_cart->num_rows === 0) {
                throw new Exception("Item keranjang tidak ditemukan. Pastikan produk sudah masuk keranjang.");
            }

            // B. KELOMPOKKAN PER PETANI
            $items_by_farmer = [];
            $cart_ids_to_delete = [];

            while ($row = $result_cart->fetch_assoc()) {
                if ($row['jumlah_kg'] > $row['stok_kg']) {
                    throw new Exception("Stok produk (ID: {$row['produk_id']}) tidak mencukupi.");
                }
                $p_id = (int)$row['petani_id'];
                $items_by_farmer[$p_id][] = $row;
                $cart_ids_to_delete[] = $row['id'];
            }
            $stmt_cart->close();

            // C. PROSES BUAT PESANAN
            $status_awal = ($metode_pembayaran === 'COD') ? 'Dikemas' : 'Menunggu Pembayaran';
            
            $stmt_order = $conn->prepare("INSERT INTO pesanan (pembeli_id, alamat_id, petani_id, total_harga, metode_pembayaran, status_pesanan) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_detail = $conn->prepare("INSERT INTO detail_pesanan (pesanan_id, produk_id, jumlah_kg, harga_kg_saat_beli, subtotal) VALUES (?, ?, ?, ?, ?)");
            $stmt_update_stok = $conn->prepare("UPDATE produk SET stok_kg = stok_kg - ? WHERE id = ?");

            $pesanan_ids_created = [];

            foreach ($items_by_farmer as $petani_id_tujuan => $items) {
                $total_harga_pesanan = 0;
                foreach ($items as $item) {
                    $total_harga_pesanan += $item['jumlah_kg'] * $item['harga_kg'];
                }

                $stmt_order->bind_param('iiidss', $pembeli_id, $alamat_id, $petani_id_tujuan, $total_harga_pesanan, $metode_pembayaran, $status_awal);
                if (!$stmt_order->execute()) throw new Exception("Gagal membuat pesanan.");
                
                $new_pesanan_id = $conn->insert_id;
                $pesanan_ids_created[] = $new_pesanan_id;

                foreach ($items as $item) {
                    $subtotal_item = $item['jumlah_kg'] * $item['harga_kg'];
                    $stmt_detail->bind_param('iiiii', $new_pesanan_id, $item['produk_id'], $item['jumlah_kg'], $item['harga_kg'], $subtotal_item);
                    $stmt_detail->execute();

                    $stmt_update_stok->bind_param('ii', $item['jumlah_kg'], $item['produk_id']);
                    $stmt_update_stok->execute();
                }
            }

            $stmt_order->close();
            $stmt_detail->close();
            $stmt_update_stok->close();

            // D. HAPUS KERANJANG
            if (!empty($cart_ids_to_delete)) {
                $placeholders_del = implode(',', array_fill(0, count($cart_ids_to_delete), '?'));
                $types_del = str_repeat('i', count($cart_ids_to_delete));
                $sql_delete = "DELETE FROM keranjang WHERE id IN ($placeholders_del)";
                $stmt_delete = $conn->prepare($sql_delete);
                $stmt_delete->bind_param($types_del, ...$cart_ids_to_delete);
                $stmt_delete->execute();
                $stmt_delete->close();
            }

            $conn->commit();
            $last_id = end($pesanan_ids_created);
            echo json_encode(['success' => true, 'message' => 'Pesanan berhasil dibuat!', 'pesanan_id' => $last_id]);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// 4. AMBIL DATA UNTUK TAMPILAN (GET)
$cart_items = [];
$alamat_list = [];
$total_belanja = 0;
$item_ids_str = $_GET['items'] ?? '';

if (isset($conn) && $conn instanceof mysqli) {
    
    $sql_cart = "SELECT k.id, k.jumlah_kg, p.nama_produk, p.harga_kg, p.foto, (k.jumlah_kg * p.harga_kg) AS subtotal
                 FROM keranjang k
                 JOIN produk p ON k.produk_id = p.id
                 WHERE k.pembeli_id = ?";

    $params_get = [$pembeli_id];
    $types_get = 'i';

    if (!empty($item_ids_str)) {
        $item_ids = explode(',', $item_ids_str);
        $item_ids = array_map('intval', $item_ids);
        if(!empty($item_ids)){
            $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
            $sql_cart .= " AND k.id IN ($placeholders)";
            $types_get .= str_repeat('i', count($item_ids));
            $params_get = array_merge($params_get, $item_ids);
        }
    } 

    $stmt_cart = $conn->prepare($sql_cart);
    $stmt_cart->bind_param($types_get, ...$params_get);
    $stmt_cart->execute();
    $result_cart = $stmt_cart->get_result();
    
    if ($result_cart) {
        while ($row = $result_cart->fetch_assoc()) {
            $cart_items[] = $row;
            $total_belanja += $row['subtotal'];
        }
    }
    $stmt_cart->close();
    
    // Jika kosong, jangan tampilkan layar putih, tapi redirect atau beri pesan
    if (empty($cart_items)) {
        echo "<script>alert('Keranjang kosong atau item tidak ditemukan!'); window.location.href='KeranjangProduk.php';</script>";
        exit;
    }

    $sql_alamat = "SELECT * FROM alamat WHERE pembeli_id = ?";
    $stmt_alamat = $conn->prepare($sql_alamat);
    $stmt_alamat->bind_param('i', $pembeli_id);
    $stmt_alamat->execute();
    $result_alamat = $stmt_alamat->get_result();
    while ($row = $result_alamat->fetch_assoc()) {
        $alamat_list[] = $row;
    }
    $stmt_alamat->close();
    
} else {
    die("Koneksi database gagal.");
}
?>
<!DOCTYPE html>
<html :class="{ 'theme-dark': dark }" x-data="data()" lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Checkout - TaniMaju</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="./assets/css/tailwind.output.css" />
    <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js" defer></script>
    <script src="./assets/js/init-alpine.js" defer></script>
  </head>
  <body>
    <div class="flex h-screen bg-gray-50 dark:bg-gray-900" :class="{ 'overflow-hidden': isSideMenuOpen }">
      <div class="flex flex-col flex-1 w-full">
        <header class="z-10 py-4 bg-white shadow-md dark:bg-gray-800">
            <div class="container flex items-center justify-between h-full px-6 mx-auto text-green-600 dark:text-green-300">
                <button class="p-1 -ml-1 mr-5 rounded-md md:hidden focus:outline-none focus:shadow-outline-green" @click="toggleSideMenu" aria-label="Menu">
                    <svg class="w-6 h-6" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path></svg>
                </button>

                <div class="flex justify-center flex-1 lg:mr-32">
                     <a href="ProdukKKatalog.php" class="text-lg font-bold text-gray-800 dark:text-gray-200">TaniMaju</a>
                </div>
                
                <ul class="flex items-center flex-shrink-0 space-x-6">
                  <li class="flex">
                    <button class="rounded-md focus:outline-none focus:shadow-outline-green" @click="toggleTheme" aria-label="Toggle color mode">
                      <template x-if="!dark"><svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg></template>
                      <template x-if="dark"><svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"></path></svg></template>
                    </button>
                  </li>
                  <li class="relative">
                    <button class="align-middle rounded-full focus:shadow-outline-purple focus:outline-none" @click="toggleProfileMenu" @keydown.escape="closeProfileMenu" aria-label="Account" aria-haspopup="true">
                      <img class="object-cover w-8 h-8 rounded-full" src="https://images.unsplash.com/photo-1502378735452-bc7d86632805?ixlib=rb-0.3.5&q=80&fm=jpg&crop=entropy&cs=tinysrgb&w=200&fit=max&s=aa3a807e1bbdfd4364d1f449eaa96d82" alt="" aria-hidden="true"/>
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
          <div class="container grid px-6 mx-auto max-w-5xl">
            <h2 class="my-6 text-2xl font-semibold text-gray-700 dark:text-gray-200">Checkout</h2>
            
            <div id="notification-bar" class="hidden px-4 py-3 mb-4 text-sm font-medium text-white rounded-lg" role="alert"></div>

            <form id="checkout-form">
                <input type="hidden" name="item_ids" value="<?php echo htmlspecialchars($item_ids_str); ?>">

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="md:col-span-2">
                        <div class="p-6 bg-white rounded-lg shadow-md dark:bg-gray-800 mb-6">
                            <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-4">Alamat Pengiriman</h3>
                            <?php if (empty($alamat_list)): ?>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Anda belum memiliki alamat.</p>
                                <a href="profil.php" class="text-sm text-green-600 hover:underline">Tambah Alamat di Profil</a>
                            <?php else: ?>
                                <div class="space-y-4">
                                    <?php foreach ($alamat_list as $alamat): ?>
                                    <label class="block p-4 border rounded-lg dark:border-gray-700 has-[:checked]:bg-green-50 dark:has-[:checked]:bg-gray-700 has-[:checked]:border-green-400 cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <div class="flex items-start">
                                            <input type="radio" name="alamat_id" value="<?php echo $alamat['id']; ?>" class="mt-1 mr-3 text-green-600 focus:ring-green-500" required>
                                            <div>
                                                <span class="font-semibold text-gray-700 dark:text-gray-200"><?php echo htmlspecialchars($alamat['judul_alamat']); ?></span> 
                                                <span class="text-gray-600 dark:text-gray-400 text-sm ml-1">(<?php echo htmlspecialchars($alamat['nama_penerima']); ?>)</span>
                                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1"><?php echo htmlspecialchars($alamat['telepon_penerima']); ?></p>
                                                <p class="text-sm text-gray-600 dark:text-gray-400"><?php echo htmlspecialchars($alamat['alamat_lengkap']); ?>, <?php echo htmlspecialchars($alamat['kota']); ?></p>
                                            </div>
                                        </div>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="p-6 bg-white rounded-lg shadow-md dark:bg-gray-800">
                            <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-4">Metode Pembayaran</h3>
                            <div class="space-y-4">
                                <label class="block p-4 border rounded-lg dark:border-gray-700 has-[:checked]:bg-green-50 dark:has-[:checked]:bg-gray-700 has-[:checked]:border-green-400 cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <div class="flex items-start">
                                        <input type="radio" name="metode_pembayaran" value="Transfer Bank" class="mt-1 mr-3 text-green-600 focus:ring-green-500" required>
                                        <div>
                                            <span class="font-medium text-gray-700 dark:text-gray-200">Transfer Bank / QRIS</span>
                                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Pembayaran melalui transfer manual.</p>
                                        </div>
                                    </div>
                                </label>
                                <label class="block p-4 border rounded-lg dark:border-gray-700 has-[:checked]:bg-green-50 dark:has-[:checked]:bg-gray-700 has-[:checked]:border-green-400 cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <div class="flex items-start">
                                        <input type="radio" name="metode_pembayaran" value="COD" class="mt-1 mr-3 text-green-600 focus:ring-green-500" required>
                                        <div>
                                            <span class="font-medium text-gray-700 dark:text-gray-200">Cash on Delivery (COD)</span>
                                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Bayar tunai saat barang diterima.</p>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="md:col-span-1">
                         <div class="p-6 bg-white rounded-lg shadow-md dark:bg-gray-800 sticky top-6">
                            <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-4">Ringkasan Pesanan</h3>
                            <div class="space-y-4">
                                <?php foreach ($cart_items as $item): ?>
                                <div class="flex justify-between items-start text-sm border-b dark:border-gray-700 pb-3 last:border-0">
                                    <div>
                                        <p class="text-gray-800 dark:text-gray-200 font-medium"><?php echo htmlspecialchars($item['nama_produk']); ?></p>
                                        <p class="text-gray-500 dark:text-gray-400 text-xs"><?php echo $item['jumlah_kg']; ?> Kg x Rp <?php echo number_format($item['harga_kg'], 0, ',', '.'); ?></p>
                                    </div>
                                    <span class="font-medium text-gray-700 dark:text-gray-200">Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <hr class="my-4 dark:border-gray-700">
                            <div class="flex justify-between items-center mb-6">
                                <span class="text-lg font-semibold text-gray-700 dark:text-gray-200">Total Bayar</span>
                                <span class="text-2xl font-bold text-green-600 dark:text-green-400">Rp <?php echo number_format($total_belanja, 0, ',', '.'); ?></span>
                            </div>
<button id="place-order-btn" type="button" class="w-full px-4 py-3 bg-green-600 text-black text-sm font-semibold rounded-lg hover:bg-green-700 transition-colors focus:outline-none focus:shadow-outline-green mb-3">
                                Buat Pesanan
                            </button>
                            <a href="KeranjangProduk.php" class="block w-full px-4 py-3 text-center text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                               Batal
                            </a>
                         </div>
                    </div>
                </div>
            </form>
          </div>
        </main>

        <script>
          function data() {
            function getThemeFromLocalStorage() {
              if (window.localStorage.getItem('dark')) { return JSON.parse(window.localStorage.getItem('dark')) }
              return (!!window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)
            }
            function setThemeToLocalStorage(value) { window.localStorage.setItem('dark', value) }
            return {
              dark: getThemeFromLocalStorage(),
              toggleTheme() { this.dark = !this.dark; setThemeToLocalStorage(this.dark) },
              isSideMenuOpen: false, toggleSideMenu() { this.isSideMenuOpen = !this.isSideMenuOpen }, closeSideMenu() { this.isSideMenuOpen = false },
              isProfileMenuOpen: false, toggleProfileMenu() { this.isProfileMenuOpen = !this.isProfileMenuOpen }, closeProfileMenu() { this.isProfileMenuOpen = false }
            }
          }
          
          document.addEventListener('DOMContentLoaded', function() {
                  const placeOrderBtn = document.getElementById('place-order-btn');
                  const notificationBar = document.getElementById('notification-bar');
                  const checkoutForm = document.getElementById('checkout-form');
                  const itemIdsInput = checkoutForm.querySelector('input[name="item_ids"]');

                  function showNotification(message, isSuccess) {
                      notificationBar.textContent = message;
                      notificationBar.classList.remove('hidden');
                      notificationBar.classList.toggle('bg-green-600', isSuccess);
                      notificationBar.classList.toggle('bg-red-600', !isSuccess);
                      window.scrollTo(0, 0);
                      setTimeout(() => { notificationBar.classList.add('hidden'); }, 4000);
                  }

                  placeOrderBtn.addEventListener('click', async function() {
                      placeOrderBtn.textContent = 'Memproses...';
                      placeOrderBtn.setAttribute('disabled', true);

                      const formData = new FormData();
                      formData.append('action', 'place_order');
                      formData.append('item_ids', itemIdsInput.value);

                      const selectedAlamat = checkoutForm.querySelector('input[name="alamat_id"]:checked');
                      if (selectedAlamat) formData.append('alamat_id', selectedAlamat.value);
                      else {
                          showNotification('Silakan pilih alamat pengiriman.', false);
                          placeOrderBtn.textContent = 'Buat Pesanan';
                          placeOrderBtn.removeAttribute('disabled');
                          return;
                      }
                      
                      const selectedMetode = checkoutForm.querySelector('input[name="metode_pembayaran"]:checked');
                      if (selectedMetode) formData.append('metode_pembayaran', selectedMetode.value);
                      else {
                          showNotification('Silakan pilih metode pembayaran.', false);
                          placeOrderBtn.textContent = 'Buat Pesanan';
                          placeOrderBtn.removeAttribute('disabled');
                          return;
                      }

                      try {
                          const response = await fetch(window.location.href, { method: 'POST', body: formData });
                          const text = await response.text();
                          try {
                              const data = JSON.parse(text);
                              if (data.success) {
                                  showNotification(data.message, true);
                                  setTimeout(() => { window.location.href = 'RiwayatTransaksi.php?pesanan_baru=' + data.pesanan_id; }, 2000);
                              } else {
                                  showNotification(data.message, false);
                                  placeOrderBtn.textContent = 'Buat Pesanan';
                                  placeOrderBtn.removeAttribute('disabled');
                              }
                          } catch (e) {
                              console.error("Server Response (Not JSON):", text);
                              showNotification('Terjadi kesalahan server: ' + text.substring(0, 100) + '...', false);
                              placeOrderBtn.textContent = 'Buat Pesanan';
                              placeOrderBtn.removeAttribute('disabled');
                          }
                      } catch (error) {
                          console.error('Error:', error);
                          showNotification('Terjadi kesalahan jaringan. Coba lagi.', false);
                          placeOrderBtn.textContent = 'Buat Pesanan';
                          placeOrderBtn.removeAttribute('disabled');
                      }
                  });
          });
        </script>
      </div>
    </div>
  </body>
</html>