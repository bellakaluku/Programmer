<?php
// 1. BUFFER & SESSION
ob_start(); // Tahan output agar tidak bocor
session_start();

// Matikan display error ke layar agar tidak merusak JSON, tapi catat ke log
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../public/pages/Connection.php'; 

// 2. KEAMANAN
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'pembeli') {
    header("Location: ../public/pages/Login.php");
    exit;
}

$pembeli_id = (int) $_SESSION['user_id'];
$pesan_sukses = "";
$pesan_error = "";

// 3. PROSES SIMPAN PROFIL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_profil'])) {
    $nama = trim($_POST['nama'] ?? '');
    $telepon = trim($_POST['telepon'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $jenis_kelamin_form = trim($_POST['jenis_kelamin'] ?? 'Laki-laki'); 

    // A. Update Foto
    $stmt_get_foto = mysqli_prepare($conn, "SELECT foto_profil FROM user_profiles WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt_get_foto, 'i', $pembeli_id);
    mysqli_stmt_execute($stmt_get_foto);
    $res = mysqli_stmt_get_result($stmt_get_foto);
    $row_foto = mysqli_fetch_assoc($res);
    $foto_path = $row_foto['foto_profil'] ?? 'assets/img/profil/default.png';
    mysqli_stmt_close($stmt_get_foto);

    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
        $upload_dir_path = __DIR__ . '/assets/img/profil/'; 
        if (!is_dir($upload_dir_path)) mkdir($upload_dir_path, 0755, true);
        
        $file_ext = strtolower(pathinfo($_FILES['foto_profil']['name'], PATHINFO_EXTENSION));
        $unique_name = uniqid('profil_', true) . '.' . $file_ext;
        
        if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $upload_dir_path . $unique_name)) {
            $foto_path = 'assets/img/profil/' . $unique_name; 
        } else {
            $pesan_error = "Gagal meng-upload foto.";
        }
    }

    // B. Update Database
    if (empty($pesan_error)) {
        $conn->begin_transaction();
        try {
            $stmt_user = $conn->prepare("UPDATE users SET nama = ? WHERE id = ?");
            $stmt_user->bind_param('si', $nama, $pembeli_id);
            $stmt_user->execute();
            $stmt_user->close();

            $query_profile = "INSERT INTO user_profiles (user_id, telepon, bio, foto_profil, Jenis_Kelamin) 
                                VALUES (?, ?, ?, ?, ?) 
                                ON DUPLICATE KEY UPDATE 
                                telepon = VALUES(telepon), bio = VALUES(bio), foto_profil = VALUES(foto_profil), Jenis_Kelamin = VALUES(Jenis_Kelamin)";
            
            $stmt_profile = $conn->prepare($query_profile);
            $stmt_profile->bind_param('issss', $pembeli_id, $telepon, $bio, $foto_path, $jenis_kelamin_form);
            $stmt_profile->execute();
            $stmt_profile->close();

            $conn->commit();
            $pesan_sukses = "Profil berhasil diperbarui!";
            $_SESSION['nama'] = $nama; 
        } catch (Exception $e) {
            $conn->rollback();
            $pesan_error = "Gagal memperbarui profil: " . $e->getMessage();
        }
    }
}

// 4. PROSES AJAX (TAMBAH/EDIT/HAPUS ALAMAT)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // BERSIHKAN BUFFER AGAR JSON MURNI
    ob_clean(); 
    header('Content-Type: application/json; charset=utf-8');

    $action = $_POST['action'];
    $judul = trim($_POST['judul_alamat'] ?? '');
    $nama_p = trim($_POST['nama_penerima'] ?? '');
    $telepon_p = trim($_POST['telepon_penerima'] ?? '');
    $alamat_l = trim($_POST['alamat_lengkap'] ?? '');
    $kota = trim($_POST['kota'] ?? '');
    $kode_pos = trim($_POST['kode_pos'] ?? '');
    $alamat_id = (int)($_POST['id'] ?? 0);

    try {
        // --- TAMBAH ALAMAT ---
        if ($action === 'tambah_alamat') {
            $stmt = $conn->prepare("INSERT INTO alamat (pembeli_id, judul_alamat, nama_penerima, telepon_penerima, alamat_lengkap, kota, kode_pos) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('issssss', $pembeli_id, $judul, $nama_p, $telepon_p, $alamat_l, $kota, $kode_pos);
            
            if ($stmt->execute()) {
                $newId = $conn->insert_id;
                $res = $conn->query("SELECT * FROM alamat WHERE id = $newId");
                $newRow = $res->fetch_assoc();
                echo json_encode(['success' => true, 'row' => $newRow, 'message' => 'Alamat baru ditambahkan!']);
            } else {
                throw new Exception("Gagal menyimpan ke database.");
            }
            $stmt->close();
        }

        // --- UPDATE ALAMAT ---
        elseif ($action === 'update_alamat') {
            $stmt = $conn->prepare("UPDATE alamat SET judul_alamat = ?, nama_penerima = ?, telepon_penerima = ?, alamat_lengkap = ?, kota = ?, kode_pos = ? WHERE id = ? AND pembeli_id = ?");
            $stmt->bind_param('ssssssii', $judul, $nama_p, $telepon_p, $alamat_l, $kota, $kode_pos, $alamat_id, $pembeli_id);
            
            if ($stmt->execute()) {
                $res = $conn->query("SELECT * FROM alamat WHERE id = $alamat_id");
                $updatedRow = $res->fetch_assoc();
                echo json_encode(['success' => true, 'row' => $updatedRow, 'message' => 'Alamat diperbarui!']);
            } else {
                throw new Exception("Gagal memperbarui data.");
            }
            $stmt->close();
        }

        // --- HAPUS ALAMAT (DENGAN TRY-CATCH) ---
        elseif ($action === 'hapus_alamat') {
            // Aktifkan mode exception untuk mysqli agar bisa di-catch
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

            $stmt = $conn->prepare("DELETE FROM alamat WHERE id = ? AND pembeli_id = ?");
            $stmt->bind_param('ii', $alamat_id, $pembeli_id);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Alamat berhasil dihapus!']);
            } else {
                // Jika tidak ada baris yang terhapus (mungkin ID salah)
                echo json_encode(['success' => false, 'message' => 'Alamat tidak ditemukan atau sudah terhapus.']);
            }
            $stmt->close();
        }

    } catch (mysqli_sql_exception $e) {
        // Cek kode error 1451 (Foreign Key Constraint)
        if ($e->getCode() == 1451) {
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus: Alamat ini sedang digunakan dalam Transaksi/Pesanan.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit; // Stop eksekusi PHP di sini agar HTML tidak ikut terkirim
}


// 5. AMBIL DATA HALAMAN
$stmt = $conn->prepare("SELECT u.nama, u.email, u.role, p.telepon, p.bio, p.foto_profil, p.Jenis_Kelamin AS jenis_kelamin FROM users u LEFT JOIN user_profiles p ON u.id = p.user_id WHERE u.id = ?");
$stmt->bind_param('i', $pembeli_id);
$stmt->execute();
$profil = $stmt->get_result()->fetch_assoc();
$stmt->close();

$profil['telepon'] = $profil['telepon'] ?? '';
$profil['bio'] = $profil['bio'] ?? '';
$profil['foto_profil'] = $profil['foto_profil'] ?? 'assets/img/profil/default.png';
$profil['jenis_kelamin'] = $profil['jenis_kelamin'] ?? '';
$profil['role'] = $profil['role'] ?? 'Pembeli';

$alamat_list = [];
$res = $conn->query("SELECT * FROM alamat WHERE pembeli_id = $pembeli_id ORDER BY id DESC");
while ($row = $res->fetch_assoc()) { $alamat_list[] = $row; }

// Flush buffer dan kirim HTML
ob_end_flush();
?>

<!DOCTYPE html>
<html :class="{ 'theme-dark': dark }" lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Profil Diri - TaniMaju</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="./assets/css/tailwind.output.css" />
    <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js" defer></script>
    </head>
  <body>
    <div class="flex h-screen bg-gray-50 dark:bg-gray-900" :class="{ 'overflow-hidden': isSideMenuOpen}" x-data="pageData()" x-init="initPage()">
      <aside class="z-20 hidden w-64 overflow-y-auto bg-white dark:bg-gray-800 md:block flex-shrink-0">
        <div class="py-4 text-gray-500 dark:text-gray-400">
          <a class="ml-6 text-lg font-bold text-gray-800 dark:text-gray-200 flex items-center" href="DasboardPembeli.php">
              <svg class="w-6 h-6 mr-2 text-green-600" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24">
                  <path d="M4 14V8a2 2 0 012-2h12a2 2 0 012 2v6m-4 0v4m-8-4v4m0-8h8m-8 0V6"></path>
              </svg>
              <span>TaniMaju</span>
          </a>
          <ul class="mt-6"></ul>
          <ul>
            <li class="relative px-6 py-3">
              <a class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200" href="ProdukKKatalog.php">
                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor">
                  <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                </svg>
                <span class="ml-4">Produk Katalog</span>
              </a>
            </li>
            <li class="relative px-6 py-3">
              <a class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200" href="KeranjangProduk.php">
                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor">
                  <path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <span class="ml-4">Keranjang</span>
              </a>
            </li>
            <li class="relative px-6 py-3">
              <a class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200" href="RiwayatTransaksi.php">
                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor">
                  <path d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                </svg>
                <span class="ml-4">Riwayat Transaksi</span>
              </a>
            </li>
          </ul>
        </div>
      </aside>
      
      <div x-show="isSideMenuOpen" x-transition:enter="transition ease-in-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in-out duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-10 flex items-end bg-black bg-opacity-50 sm:items-center sm:justify-center"></div>
      <aside class="fixed inset-y-0 z-20 flex-shrink-0 w-64 mt-16 overflow-y-auto bg-white dark:bg-gray-800 md:hidden" x-show="isSideMenuOpen" x-transition:enter="transition ease-in-out duration-150" x-transition:enter-start="opacity-0 transform -translate-x-20" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in-out duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 transform -translate-x-20" @click.away="closeSideMenu" @keydown.escape="closeSideMenu">
        <div class="py-4 text-gray-500 dark:text-gray-400">
          <a class="ml-6 text-lg font-bold text-gray-800 dark:text-gray-200 flex items-center" href="DasboardPembeli.php">
            <svg class="w-6 h-6 mr-2 text-green-600" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24">
                <path d="M4 14V8a2 2 0 012-2h12a2 2 0 012 2v6m-4 0v4m-8-4v4m0-8h8m-8 0V6"></path>
            </svg>
            <span>TaniMaju</span>
          </a>
          <ul class="mt-6"></ul>
          <ul>
            <li class="relative px-6 py-3">
              <a class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200" href="ProdukKatalog.php">
                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor">
                  <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                </svg>
                <span class="ml-4">Produk Katalog</span>
              </a>
            </li>
            <li class="relative px-6 py-3">
              <a class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200" href="KeranjangProduk.php">
                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor">
                  <path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <span class="ml-4">Keranjang</span>
              </a>
            </li>
            <li class="relative px-6 py-3">
              <a class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200" href="RiwayatTransaksi.php">
                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" stroke="currentColor">
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
          <div class="container flex items-center justify-between h-full px-6 mx-auto text-green-600 dark:text-green-300">
            <button class="p-1 mr-5 -ml-1 rounded-md md:hidden focus:outline-none focus:shadow-outline-green" @click="toggleSideMenu" aria-label="Menu">
              <svg class="w-6 h-6" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>
              </svg>
            </button>
            <div class="flex justify-center flex-1 lg:mr-32">
              <div class="relative w-full max-w-xl mr-6 focus-within:text-green-500">
                <div class="absolute inset-y-0 flex items-center pl-2">
                  <svg class="w-4 h-4" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                  </svg>
                </div>
                <input class="w-full pl-8 pr-2 text-sm text-gray-700 placeholder-gray-600 bg-gray-100 border-0 rounded-md dark:placeholder-gray-500 dark:focus:shadow-outline-gray dark:focus:placeholder-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:placeholder-gray-500 focus:bg-white focus:border-green-300 focus:outline-none focus:shadow-outline-green form-input" type="text" placeholder="Cari transaksi..." aria-label="Search"/>
              </div>
            </div>
            <ul class="flex items-center flex-shrink-0 space-x-6">
              <li class="flex">
                <button class="rounded-md focus:outline-none focus:shadow-outline-green" @click="toggleTheme" aria-label="Toggle color mode">
                  <template x-if="!dark">
                    <svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
                  </template>
                  <template x-if="dark">
                    <svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"></path></svg>
                  </template>
                </button>
              </li>
              <li class="relative">
                <button class="align-middle rounded-full focus:shadow-outline-green focus:outline-none" @click="toggleProfileMenu" @keydown.escape="closeProfileMenu" aria-label="Account" aria-haspopup="true">
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
            <div class="container grid px-6 mx-auto">

                <div class="flex items-center justify-between my-6">
                    <h2 class="text-2xl font-semibold text-gray-700 dark:text-gray-200">Profil Diri</h2>
                    <div class="bg-blue-500 p-2 rounded-lg">
                        <button @click="isEditingProfil = !isEditingProfil" class="flex items-center px-3 py-2 text-sm bg-green-600 font-medium leading-5 text-white transition-colors duration-150 border border-transparent rounded-lg active:bg-green-600 hover:bg-green-700 focus:outline-none focus:shadow-outline-green" type="button">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path></svg>
                            <span x-text="isEditingProfil ? 'Batal Edit' : 'Edit Profil'"></span>
                        </button>
                    </div>
                </div>

                <div x-show="pesanNotifikasi.teks" x-transition :class="{ 'bg-green-600': pesanNotifikasi.tipe === 'success', 'bg-red-600': pesanNotifikasi.tipe === 'error' }" class="px-4 py-3 mb-4 text-sm font-medium text-white rounded-lg" role="alert" x-text="pesanNotifikasi.teks" style="display: none;"></div>

                <div x-show="!isEditingProfil" x-transition class="grid gap-6 mb-8 md:grid-cols-3">
                    <div class="md:col-span-1">
                        <div class="p-4 bg-white rounded-lg shadow-md dark:bg-gray-800">
                            <div class="relative">
                                <div class="h-24 bg-green-500 rounded-t-lg"></div>
                                <div class="flex justify-center -mt-16">
                                    <img src="./<?php echo htmlspecialchars($profil['foto_profil']); ?>" alt="Foto Profil" class="w-32 h-32 object-cover border-4 border-white rounded-full shadow-md bg-white" style="width: 8rem; height: 8rem; object-fit: cover;" onerror="this.src='./assets/img/profil/default.png'">
                                </div>
                            </div>
                            <div class="text-center mt-4">
                                <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($profil['nama']); ?></h3>
                                <p class="text-sm text-gray-500 capitalize"><?php echo htmlspecialchars($profil['role'] ?? 'Pembeli'); ?></p>
                                <span class="mt-2 inline-block px-3 py-1 text-xs font-semibold text-green-600 bg-green-100 rounded-full dark:bg-green-700 dark:text-green-100">VERIFIED</span>
                            </div>
                            <hr class="my-4 dark:border-gray-700">
                            <h4 class="font-semibold mb-2 text-gray-700 dark:text-gray-200">Bio Singkat</h4>
                            <p class="text-sm leading-relaxed text-gray-700 dark:text-gray-200"><?php echo nl2br(htmlspecialchars($profil['bio'] ?: 'Pembeli belum menambahkan bio.')); ?></p>
                        </div>
                    </div>
                    <div class="md:col-span-2 space-y-6">
                        <div class="p-6 bg-white rounded-lg shadow-md dark:bg-gray-800">
                            <h4 class="mb-4 text-lg font-semibold text-gray-700 dark:text-gray-200">Identitas</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm">
                                <div><p class="mb-1 text-gray-500 dark:text-gray-400">Nama Lengkap</p><p class="font-medium text-gray-700 dark:text-gray-200"><?php echo htmlspecialchars($profil['nama']); ?></p></div>
                                <div><p class="mb-1 text-gray-500 dark:text-gray-400">Email</p><p class="font-medium text-gray-700 dark:text-gray-200"><?php echo htmlspecialchars($profil['email']); ?></p></div>
                                <div><p class="mb-1 text-gray-500 dark:text-gray-400">Telepon</p><p class="font-medium text-gray-700 dark:text-gray-200"><?php echo htmlspecialchars($profil['telepon'] ?: '-'); ?></p></div>
                                <div><p class="mb-1 text-gray-500 dark:text-gray-400">Jenis Kelamin</p><p class="font-medium text-gray-700 dark:text-gray-200 capitalize"><?php echo htmlspecialchars($profil['jenis_kelamin'] ?: '-'); ?></p></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div x-show="isEditingProfil" x-transition class="px-6 py-6 mb-8 bg-white rounded-lg shadow-md dark:bg-gray-800" style="display: none;">
                    <form method="POST" action="profil.php" enctype="multipart/form-data">
                        <div class="mb-6">
                            <div class="text-center mb-2"><span class="text-gray-700 dark:text-gray-400 text-sm font-semibold">Foto Profil</span></div>
                            <div class="flex justify-center">
                                <div class="relative w-32 h-32 group cursor-pointer" @click="$refs.fotoProfilInput.click()" title="Klik untuk ganti foto">
                                    <img :src="fotoPreviewUrl" alt="Foto Profil" class="w-32 h-32 rounded-full object-cover border-4 border-gray-200 dark:border-gray-600 transition-opacity duration-200 group-hover:opacity-40 shadow-md" style="width: 8rem; height: 8rem; object-fit: cover;" onerror="this.src='./assets/img/profil/default.png'">
                                    <div class="absolute inset-0 flex items-center justify-center rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-200" aria-hidden="true"><div class="bg-black bg-opacity-50 p-2 rounded-full"><svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg></div></div>
                                    <input type="file" name="foto_profil" x-ref="fotoProfilInput" @change="updateFotoPreview" accept="image/png, image/jpeg, image/jpg" class="hidden">
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-3 text-center dark:text-gray-400">Klik foto untuk mengubah. Maksimal 2MB.</p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <label class="block text-sm"><span class="text-gray-700 dark:text-gray-400">Nama Lengkap</span><input name="nama" value="<?php echo htmlspecialchars($profil['nama']); ?>" class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-green-400 focus:outline-none focus:shadow-outline-green dark:text-gray-300 form-input" required /></label>
                            <label class="block text-sm"><span class="text-gray-700 dark:text-gray-400">Email (Tidak bisa diubah)</span><input type="email" name="email" value="<?php echo htmlspecialchars($profil['email']); ?>" class="block w-full mt-1 text-sm bg-gray-100 dark:border-gray-600 dark:bg-gray-700 text-gray-400 dark:text-gray-500 form-input cursor-not-allowed" disabled /></label>
                            <label class="block text-sm"><span class="text-gray-700 dark:text-gray-400">Nomor Telepon</span><input type="tel" name="telepon" value="<?php echo htmlspecialchars($profil['telepon']); ?>" class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-green-400 focus:outline-none focus:shadow-outline-green dark:text-gray-300 form-input" placeholder="Contoh: 08123456789" /></label>
                            <label class="block text-sm"><span class="text-gray-700 dark:text-gray-400">Jenis Kelamin</span><select name="jenis_kelamin" class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-green-400 focus:outline-none focus:shadow-outline-green dark:text-gray-300 form-select"><option value="Laki-laki" <?php echo ($profil['jenis_kelamin'] == 'Laki-laki' ? 'selected' : ''); ?>>Laki-laki</option><option value="Perempuan" <?php echo ($profil['jenis_kelamin'] == 'Perempuan' ? 'selected' : ''); ?>>Perempuan</option></select></label>
                            <label class="block text-sm md:col-span-2"><span class="text-gray-700 dark:text-gray-400">Bio Singkat / Keterangan</span><textarea name="bio" class="block w-full mt-1 text-sm dark:text-gray-300 dark:border-gray-600 dark:bg-gray-700 form-textarea focus:border-green-400 focus:outline-none focus:shadow-outline-green" rows="4" placeholder="Ceritakan sedikit tentang diri Anda."><?php echo htmlspecialchars($profil['bio']); ?></textarea></label>
                        </div>
                        <div class="text-right mt-6"><button name="simpan_profil" class="px-5 py-2 text-sm padding font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:shadow-outline-blue">Simpan Perubahan</button></div>
                    </form>
                </div>

                <div class="mb-8">
                    <div class="p-6 bg-white rounded-lg shadow-md dark:bg-gray-800">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-lg font-semibold text-gray-700 dark:text-gray-200">Alamat Saya</h4>
                            <button @click="bukaModalTambah()" class="px-3 py-1 text-sm font-medium leading-5 text-green-600 transition-colors duration-150 border border-green-600 rounded-lg hover:bg-green-600 hover:text-white focus:outline-none focus:shadow-outline-green" type="button">+ Tambah</button>
                        </div>
                        <div class="space-y-4">
                            <template x-if="alamatList.length === 0"><p class="text-sm text-gray-500 dark:text-gray-400">Anda belum menambahkan alamat pengiriman.</p></template>
                            <template x-for="(alamat, index) in alamatList" :key="alamat.id">
                                <div class="p-4 rounded-lg border dark:border-gray-700">
                                    <div class="flex justify-between items-center mb-2">
                                        <h3 class="font-semibold text-gray-700 dark:text-gray-200" x-text="alamat.judul_alamat"></h3>
                                        <div class="space-x-2">
                                            <button @click="bukaModalEdit(alamat)" class="text-sm text-green-600 hover:underline" type="button">Edit</button>
                                            <button type="button" @click.prevent="hapusAlamat(alamat.id, index)" class="text-sm text-red-600 hover:underline">Hapus</button>
                                        </div>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-300 font-medium" x-text="alamat.nama_penerima"></p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400" x-text="alamat.telepon_penerima"></p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1" x-text="alamat.alamat_lengkap + ', ' + alamat.kota + ', ' + (alamat.kode_pos || '')"></p>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div x-show="isModalAlamatOpen" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-30 flex items-end bg-black bg-opacity-50 sm:items-center sm:justify-center" style="display: none;">
                    <div x-show="isModalAlamatOpen" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 transform translate-y-1/2" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 transform translate-y-1/2" @click.away="tutupModalAlamat" @keydown.escape.window="tutupModalAlamat" class="w-full px-6 py-4 overflow-hidden bg-white rounded-t-lg dark:bg-gray-800 sm:rounded-lg sm:m-4 sm:max-w-xl" role="dialog" id="modal-alamat">
                        <header class="flex justify-between items-center"><h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200" x-text="formAlamat.id ? 'Edit Alamat' : 'Tambah Alamat Baru'"></h3><button class="inline-flex items-center justify-center w-6 h-6 text-gray-400 transition-colors duration-150 rounded dark:hover:text-gray-200 hover:text-gray-700" aria-label="Close" @click="tutupModalAlamat"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20" role="img" aria-hidden="true"><path d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" fill-rule="evenodd"></path></svg></button></header>
                        <div class="mt-4 mb-6">
                            <form @submit.prevent="simpanAlamat">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4"><label class="block text-sm"><span class="text-gray-700 dark:text-gray-400">Judul Alamat (Cth: Rumah)</span><input x-model="formAlamat.judul_alamat" name="judul_alamat" class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-green-400 focus:outline-none focus:shadow-outline-green dark:text-gray-300 form-input" required /></label><label class="block text-sm"><span class="text-gray-700 dark:text-gray-400">Nama Penerima</span><input x-model="formAlamat.nama_penerima" name="nama_penerima" class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-green-400 focus:outline-none focus:shadow-outline-green dark:text-gray-300 form-input" required /></label></div>
                                <label class="block mt-4 text-sm"><span class="text-gray-700 dark:text-gray-400">Telepon Penerima</span><input x-model="formAlamat.telepon_penerima" name="telepon_penerima" class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-green-400 focus:outline-none focus:shadow-outline-green dark:text-gray-300 form-input" required /></label>
                                <label class="block mt-4 text-sm"><span class="text-gray-700 dark:text-gray-400">Alamat Lengkap</span><textarea x-model="formAlamat.alamat_lengkap" name="alamat_lengkap" class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-green-400 focus:outline-none focus:shadow-outline-green dark:text-gray-300 form-textarea" rows="3" required></textarea></label>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4"><label class="block text-sm"><span class="text-gray-700 dark:text-gray-400">Kota / Kabupaten</span><input x-model="formAlamat.kota" name="kota" class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-green-400 focus:outline-none focus:shadow-outline-green dark:text-gray-300 form-input" /></label><label class="block text-sm"><span class="text-gray-700 dark:text-gray-400">Kode Pos</span><input x-model="formAlamat.kode_pos" name="kode_pos" class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-green-400 focus:outline-none focus:shadow-outline-green dark:text-gray-300 form-input" /></label></div>
                                <footer class="flex flex-col px-6 py-3 -mx-6 -mb-4 space-y-3 sm:px-6 bg-gray-50 dark:bg-gray-800 mt-6"><button @click="tutupModalAlamat" type="button" class="w-full px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 focus:outline-none">Batal</button><button type="submit" class="w-full px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none">Simpan Alamat</button></footer>
                            </form>
                        </div>
                    </div>
                </div>

                <div x-show="isModalPesanOpen" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-40 flex items-end bg-black bg-opacity-50 sm:items-center sm:justify-center" style="display: none;">
                    <div x-show="isModalPesanOpen" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 transform translate-y-1/2" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 transform translate-y-1/2" @click.away="isModalPesanOpen = false" @keydown.escape.window="isModalPesanOpen = false" class="w-full px-6 py-4 overflow-hidden bg-white rounded-t-lg dark:bg-gray-800 sm:rounded-lg sm:m-4 sm:max-w-md" role="dialog" id="modal-pesan">
                        <header class="flex justify-between items-center"><h3 class="text-lg font-semibold" :class="modalPesanTipe === 'success' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'" x-text="modalPesanTipe === 'success' ? 'Berhasil' : 'Terjadi Kesalahan'"></h3><button class="inline-flex items-center justify-center w-6 h-6 text-gray-400 transition-colors duration-150 rounded dark:hover:text-gray-200 hover:text-gray-700" aria-label="Close" @click="isModalPesanOpen = false"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20" role="img" aria-hidden="true"><path d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" fill-rule="evenodd"></path></svg></button></header>
                        <div class="mt-4 mb-6"><p class="text-sm text-gray-700 dark:text-gray-300" x-text="modalPesanTeks"></p></div>
                        <footer class="flex items-center justify-end px-6 py-3 -mx-6 -mb-4 space-x-4 sm:px-6 bg-gray-50 dark:bg-gray-800"><button @click="isModalPesanOpen = false" type="button" class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:shadow-outline-green">OK</button></footer>
                    </div>
                </div>
            </div>
        </main>

    <script>
      function pageData() {
        function getThemeFromLocalStorage() { if (window.localStorage.getItem('dark')) { return JSON.parse(window.localStorage.getItem('dark')) } return (!!window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) }
        function setThemeToLocalStorage(value) { window.localStorage.setItem('dark', value) }
        const alamatListPHP = <?php echo json_encode($alamat_list); ?>;
        const formAlamatDefault = { id: null, judul_alamat: '', nama_penerima: '', telepon_penerima: '', alamat_lengkap: '', kota: '', kode_pos: '' };

        let autoOpenModal = false; let modalMsg = ''; let modalType = 'success';
        <?php if (!empty($pesan_sukses)) { echo "autoOpenModal = true; modalMsg = " . json_encode($pesan_sukses) . "; modalType = 'success';"; } elseif (!empty($pesan_error)) { echo "autoOpenModal = true; modalMsg = " . json_encode($pesan_error) . "; modalType = 'error';"; } ?>

        return {
          dark: getThemeFromLocalStorage(),
          isSideMenuOpen: false,
          isNotificationsMenuOpen: false,
          isProfileMenuOpen: false,
          isEditingProfil: false,
          isModalAlamatOpen: false,
          pesanNotifikasi: { teks: '', tipe: 'success' },
          alamatList: alamatListPHP,
          formAlamat: { ...formAlamatDefault },
          isModalPesanOpen: autoOpenModal,
          modalPesanTeks: modalMsg,
          modalPesanTipe: modalType,
          fotoAsliUrl: "<?php echo htmlspecialchars($profil['foto_profil']); ?>?t=" + new Date().getTime(), 
          fotoPreviewUrl: "<?php echo htmlspecialchars($profil['foto_profil']); ?>?t=" + new Date().getTime(),
          initPage() { this.fotoPreviewUrl = this.fotoAsliUrl; if (this.isModalPesanOpen) { this.isEditingProfil = true; } },
          toggleTheme() { this.dark = !this.dark; setThemeToLocalStorage(this.dark); },
          toggleSideMenu() { this.isSideMenuOpen = !this.isSideMenuOpen; },
          closeSideMenu() { this.isSideMenuOpen = false; },
          toggleNotificationsMenu() { this.isNotificationsMenuOpen = !this.isNotificationsMenuOpen; },
          closeNotificationsMenu() { this.isNotificationsMenuOpen = false; },
          toggleProfileMenu() { this.isProfileMenuOpen = !this.isProfileMenuOpen; },
          closeProfileMenu() { this.isProfileMenuOpen = false; },
          updateFotoPreview(event) { const file = event.target.files[0]; if (file) { this.fotoPreviewUrl = URL.createObjectURL(file); } else { this.fotoPreviewUrl = this.fotoAsliUrl; } },
          tampilNotifikasi(pesan, tipe) { this.pesanNotifikasi.teks = pesan; this.pesanNotifikasi.tipe = tipe; setTimeout(() => { this.pesanNotifikasi.teks = ''; }, 3000); },
          resetFormAlamat() { this.formAlamat = { ...formAlamatDefault }; },
          bukaModalTambah() { this.resetFormAlamat(); this.isModalAlamatOpen = true; },
          bukaModalEdit(alamat) { this.formAlamat = Object.assign({}, alamat); this.isModalAlamatOpen = true; },
          tutupModalAlamat() { this.isModalAlamatOpen = false; this.resetFormAlamat(); },
          async simpanAlamat() {
            const formData = new FormData();
            formData.append('action', this.formAlamat.id ? 'update_alamat' : 'tambah_alamat');
            for (const key in this.formAlamat) { formData.append(key, this.formAlamat[key]); }
            try {
                const response = await fetch('profil.php', { method: 'POST', body: formData });
                const rawText = await response.text(); // Ambil text mentah
                let data;
                try { data = JSON.parse(rawText); } catch(e) { console.error("JSON Error:", rawText); throw new Error("Server Error: " + rawText); }
                
                if (data.success) {
                    if (this.formAlamat.id) { const index = this.alamatList.findIndex(a => a.id == this.formAlamat.id); if (index !== -1) { this.alamatList.splice(index, 1, data.row); } } else { this.alamatList.unshift(data.row); }
                    this.tampilNotifikasi(data.message, 'success');
                    this.tutupModalAlamat();
                } else { this.tampilNotifikasi(data.message || 'Gagal menyimpan alamat.', 'error'); }
            } catch (err) { console.error(err); this.tampilNotifikasi('Terjadi kesalahan sistem. Cek console.', 'error'); }
          },
          async hapusAlamat(id, index) {
            if (!confirm('Apakah Anda yakin ingin menghapus alamat ini?')) return;
            const formData = new FormData();
            formData.append('action', 'hapus_alamat');
            formData.append('id', id);
            try {
                const response = await fetch('profil.php', { method: 'POST', body: formData });
                const rawText = await response.text(); // Ambil text mentah dulu
                let data;
                try { data = JSON.parse(rawText); } catch(e) { console.error("JSON Error:", rawText); throw new Error("Server Error: " + rawText); }
                
                if (data.success) {
                    this.alamatList.splice(index, 1);
                    this.tampilNotifikasi(data.message, 'success');
                } else { this.tampilNotifikasi(data.message || 'Gagal menghapus alamat.', 'error'); }
            } catch (err) { console.error(err); this.tampilNotifikasi('Terjadi kesalahan sistem. Cek console.', 'error'); }
          }
        }
      }
    </script>
  </body>
</html>