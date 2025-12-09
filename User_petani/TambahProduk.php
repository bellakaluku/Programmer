<?php
// 1. MEMULAI SESSION & KONEKSI
session_start();
// Asumsi file ini ada di User_Petani/, jadi kita keluar (../) ke public/
require_once __DIR__ . '/../public/pages/Connection.php';

// 2. KEAMANAN: Pastikan yang login adalah PETANI
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'petani') {
    // Jika bukan petani, tendang ke halaman login
    header("Location: ../public/pages/Login.php");
    exit;
}
$petani_id = (int)$_SESSION['user_id'];


// ===================================================================
// BAGIAN 3: INI ADALAH "ACTION FILE" YANG KITA CARI
// ===================================================================
$pesan_error = "";
$pesan_sukses = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Formulir telah di-submit, mari kita proses
    
    // 1. Ambil data dari formulir (pastikan divalidasi)
    $nama_produk = $_POST['nama_produk'] ?? '';
    $kategori_id = (int)($_POST['kategori_id'] ?? 0);
    $harga_kg = (int)($_POST['harga_kg'] ?? 0);
    $stok_kg = (int)($_POST['stok_kg'] ?? 0);
    $keterangan = $_POST['keterangan'] ?? '';
    
    // (Catatan: Logika upload foto akan ditambahkan nanti)
    $nama_foto = null; // Untuk saat ini, kita kosongkan fotonya

    // 2. Validasi sederhana
    if (empty($nama_produk) || $kategori_id === 0 || $harga_kg <= 0 || $stok_kg < 0) {
        $pesan_error = "Semua field yang ditandai (*) wajib diisi.";
    } else {
        // 3. Masukkan ke database
        try {
            $sql_insert = "INSERT INTO produk 
                            (petani_id, kategori_id, nama_produk, harga_kg, stok_kg, keterangan, foto, status) 
                           VALUES 
                            (?, ?, ?, ?, ?, ?, ?, 'aktif')";
            
            $stmt = $conn->prepare($sql_insert);
            $stmt->bind_param('iisdisss', 
                $petani_id, 
                $kategori_id, 
                $nama_produk, 
                $harga_kg, 
                $stok_kg, 
                $keterangan,
                $nama_foto
            );
            
            if ($stmt->execute()) {
                $pesan_sukses = "Produk baru berhasil ditambahkan!";
                // Nanti kita bisa redirect: header("Location: KelolaProduk.php");
            } else {
                $pesan_error = "Gagal menyimpan ke database: " . $stmt->error;
            }
            $stmt->close();

        } catch (Exception $e) {
            $pesan_error = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}


// ===================================================================
// BAGIAN 4: AMBIL DATA KATEGORI UNTUK FORM DROPDOWN
// ===================================================================
$sql_kategori = "SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori ASC";
$result_kategori = $conn->query($sql_kategori);

$daftar_kategori = [];
if ($result_kategori && $result_kategori->num_rows > 0) {
    while ($row = $result_kategori->fetch_assoc()) {
        $daftar_kategori[] = $row;
    }
}
$conn->close();
?>

<html :class="{ 'theme-dark': dark }" x-data="data()" lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Tambah Produk Baru - Dashboard Petani</title>
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="../User_Pembeli/assets/css/tailwind.output.css" />
    <script
      src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js"
      defer
    ></script>
</head>
<body>
    <div class="flex h-screen bg-gray-50 dark:bg-gray-900">
        <main class="h-full overflow-y-auto w-full">
            <div class="container px-6 mx-auto grid">
                <h2 class="my-6 text-2xl font-semibold text-gray-700 dark:text-gray-200">
                    Tambah Produk Baru
                </h2>

                <?php if (!empty($pesan_error)): ?>
                    <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg dark:bg-red-200 dark:text-red-800" role="alert">
                        <?php echo $pesan_error; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($pesan_sukses)): ?>
                    <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg dark:bg-green-200 dark:text-green-800" role="alert">
                        <?php echo $pesan_sukses; ?>
                    </div>
                <?php endif; ?>


                <form action="TambahProduk.php" method="POST" enctype="multipart/form-data">
                    <div class="px-4 py-3 mb-8 bg-white rounded-lg shadow-md dark:bg-gray-800">
                        
                        <label class="block text-sm">
                            <span class="text-gray-700 dark:text-gray-400">Nama Produk (*)</span>
                            <input
                                name="nama_produk"
                                class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300 dark:focus:shadow-outline-gray form-input"
                                placeholder="Cth: Cabai Merah Keriting"
                                required
                            />
                        </label>

                        <label class="block text-sm mt-4">
                            <span class="text-gray-700 dark:text-gray-400">Kategori Produk (*)</span>
                            <select 
                                name="kategori_id" 
                                id="kategori_id" 
                                class="block w-full mt-1 text-sm dark:text-gray-300 dark:border-gray-600 dark:bg-gray-700 form-select focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:focus:shadow-outline-gray" 
                                required
                            >
                                <option value="">-- Pilih Kategori --</option>
                                <?php foreach ($daftar_kategori as $kategori): ?>
                                    <option value="<?php echo $kategori['id']; ?>">
                                        <?php echo htmlspecialchars($kategori['nama_kategori']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <label class="block text-sm">
                                <span class="text-gray-700 dark:text-gray-400">Harga per Kg (*)</span>
                                <input
                                    name="harga_kg"
                                    type="number"
                                    min="0"
                                    class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300 dark:focus:shadow-outline-gray form-input"
                                    placeholder="Cth: 25000"
                                    required
                                />
                            </label>
                            
                            <label class="block text-sm">
                                <span class="text-gray-700 dark:text-gray-400">Stok (Kg) (*)</span>
                                <input
                                    name="stok_kg"
                                    type="number"
                                    min="0"
                                    class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300 dark:focus:shadow-outline-gray form-input"
                                    placeholder="Stok yang siap dijual"
                                    required
                                />
                            </label>
                        </div>
                        
                        <label class="block mt-4 text-sm">
                            <span class="text-gray-700 dark:text-gray-400">Keterangan / Deskripsi Produk</span>
                            <textarea
                                name="keterangan"
                                class="block w-full mt-1 text-sm dark:text-gray-300 dark:border-gray-600 dark:bg-gray-700 form-textarea focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:focus:shadow-outline-gray"
                                rows="3"
                                placeholder="Jelaskan tentang produk Anda..."
                            ></textarea>
                        </label>

                        <div class="mt-6">
                            <button 
                                type="submit"
                                class="w-full px-5 py-3 font-medium leading-5 text-white transition-colors duration-150 bg-purple-600 border border-transparent rounded-lg active:bg-purple-600 hover:bg-purple-700 focus:outline-none focus:shadow-outline-purple"
                            >
                                Simpan Produk
                            </button>
                        </div>

                    </div>
                </form>

            </div>
        </main>
        </div>

    <script>
      function data() {
        function getThemeFromLocalStorage() { /* ... (fungsi dark mode Anda) ... */ }
        return {
          dark: getThemeFromLocalStorage(),
          /* ... (sisa data Alpine Anda) ... */
        }
      }
    </script>
</body>
</html>