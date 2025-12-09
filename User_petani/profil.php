<?php
// 1. MEMULAI SESSION & KONEKSI
session_start();
// PERBAIKAN: Path dari User_petani/ naik 1 level ke windmill-dashboard/, lalu masuk ke public/pages/
include __DIR__ . '/../public/pages/Connection.php'; 

// 2. KEAMANAN: Cek jika user sudah login dan adalah 'petani'
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'petani') {
    // Path: naik 1 level, lalu masuk ke public/pages/
    header("Location: ../public/pages/Login.php");
    exit;
}

$petani_id = (int) $_SESSION['user_id'];
$pesan_sukses = "";
$pesan_error = "";

// 3. PROSES SIMPAN PERUBAHAN (UPDATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_profil'])) {
    
    $nama = trim($_POST['nama'] ?? '');
    $telepon = trim($_POST['telepon'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $jenis_kelamin = trim($_POST['jenis_kelamin'] ?? ''); 

    // --- A. PROSES UPDATE FOTO PROFIL ---
    // Ambil path foto lama dulu
    $stmt_get_foto = mysqli_prepare($conn, "SELECT foto_profil FROM user_profiles WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt_get_foto, 'i', $petani_id);
    mysqli_stmt_execute($stmt_get_foto);
    // PERBAIKAN: Path default foto
    $foto_path = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get_foto))['foto_profil'] ?? 'assets/img/profil/default.png';
    mysqli_stmt_close($stmt_get_foto);

    // Cek jika ada file BARU di-upload
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
        // PERBAIKAN: Path folder upload
        $upload_dir_path = __DIR__ . '/assets/img/profil/'; // Path: User_petani/assets/img/profil/
        if (!is_dir($upload_dir_path)) {
            mkdir($upload_dir_path, 0755, true);
        }
        
        $file_ext = strtolower(pathinfo($_FILES['foto_profil']['name'], PATHINFO_EXTENSION));
        $unique_name = uniqid('profil_', true) . '.' . $file_ext;
        
        if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $upload_dir_path . $unique_name)) {
            // TODO: Hapus file foto lama ($foto_path) dari server jika bukan 'default.png'
            $foto_path = 'assets/img/profil/' . $unique_name; // Path web baru
        } else {
            $pesan_error = "Gagal meng-upload foto.";
        }
    }

    // --- B. PROSES UPDATE DATABASE ---
    if (empty($pesan_error)) {
        // 1. Update tabel 'users' (untuk nama)
        $stmt_user = mysqli_prepare($conn, "UPDATE users SET nama = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_user, 'si', $nama, $petani_id);
        mysqli_stmt_execute($stmt_user);
        mysqli_stmt_close($stmt_user);

        // 2. Update/Insert tabel 'user_profiles'
        // PERBAIKAN: Gunakan nama kolom DB yang benar (Jenis_Kelamin)
        $query_profile = "INSERT INTO user_profiles (user_id, telepon, bio, foto_profil, Jenis_Kelamin) 
                            VALUES (?, ?, ?, ?, ?) 
                            ON DUPLICATE KEY UPDATE 
                            telepon = VALUES(telepon), bio = VALUES(bio), foto_profil = VALUES(foto_profil), Jenis_Kelamin = VALUES(Jenis_Kelamin)";
        
        $stmt_profile = mysqli_prepare($conn, $query_profile);
        mysqli_stmt_bind_param($stmt_profile, 'issss', $petani_id, $telepon, $bio, $foto_path, $jenis_kelamin);
        
        if (mysqli_stmt_execute($stmt_profile)) {
            $pesan_sukses = "Profil berhasil diperbarui!";
            $_SESSION['nama'] = $nama; 
        } else {
            $pesan_error = "Gagal memperbarui profil: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt_profile);
    }
}

// 4. AMBIL DATA PROFIL UNTUK DITAMPILKAN DI FORM
// PERBAIKAN UTAMA: Mengambil p.Jenis_Kelamin (sesuai DB) dan memberikan alias (AS) 'jenis_kelamin' (huruf kecil)
$query_select = "SELECT u.nama, u.email, u.role, p.telepon, p.bio, p.foto_profil, p.Jenis_Kelamin AS jenis_kelamin
                 FROM users u 
                 LEFT JOIN user_profiles p ON u.id = p.user_id 
                 WHERE u.id = ?";
$stmt_select = mysqli_prepare($conn, $query_select);
mysqli_stmt_bind_param($stmt_select, 'i', $petani_id);
mysqli_stmt_execute($stmt_select);
$profil = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_select));
mysqli_stmt_close($stmt_select);

// Set default jika data profil belum ada
$profil['telepon'] = $profil['telepon'] ?? '';
$profil['bio'] = $profil['bio'] ?? '';
$profil['foto_profil'] = $profil['foto_profil'] ?? 'assets/img/profil/default.png';
$profil['jenis_kelamin'] = $profil['jenis_kelamin'] ?? ''; // PERBAIKAN: Gunakan kunci lowercase
$profil['role'] = $profil['role'] ?? 'Petani';

?>

<!DOCTYPE html>
<html :class="{ 'theme-dark': dark }" x-data="data()" lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Profil Diri - TaniMaju</title>
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
        <link
            rel="stylesheet"
            href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.3/Chart.min.css"
        />
        <script
            src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.3/Chart.min.js"
            defer
        ></script>
        <script src="./assets/js/charts-lines.js" defer></script>
        <script src="./assets/js/charts-pie.js" defer></script>
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
                                href="DasboardPetani.php"
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
                                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
                                    ></path>
                                </svg>
                                <span class="ml-4">Dashboard</span>
                            </a>
                        </li>
                    </ul>
                    <ul>
                        <li class="relative px-6 py-3">
                            <a
                                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
                                href="DataLahan.php"
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
                                <span class="ml-4">Data Lahan & Penanaman</span>
                            </a>
                        </li>
                        
                        <li class="relative px-6 py-3">
                            <a
                                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
                                href="kebun.php"
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
                                    <path d="M4 14V8a2 2 0 012-2h12a2 2 0 012 2v6m-4 0v4m-8-4v4m0-8h8m-8 0V6"></path>
                                </svg>
                                <span class="ml-4">Manajemen Lahan (Master)</span>
                            </a>
                        </li>

                        <li class="relative px-6 py-3">
                            <a
                                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
                                href="KelolaProduk.php"
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
                                <span class="ml-4">Kelola Produk</span>
                            </a>
                        </li>

                        <li class="relative px-6 py-3">
                            <a
                                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
                                href="PesananMasuk.php"
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
                                <span class="ml-4">Pesanan Masuk</span>
                            </a>
                        </li>
                        
                        <li class="relative px-6 py-3">
                            <a
                                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
                                href="CatatanKeuangan.php"
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
                                    <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0c-1.657 0-3-.895-3-2s1.343-2 3-2 3-.895 3-2 1.343-2 3-2m0 8c1.11 0 2.08-.402 2.599-1M12 16V7m0 9v-1m0-8V7m0 0h.01M6 12h.01M6 12h.01M6 12h.01M18 12h.01M18 12h.01M18 12h.01M6 9h.01M6 15h.01M18 9h.01M18 15h.01"></path>
                                </svg>
                                <span class="ml-4">Catatan Keuangan</span>
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
                    <ul class="mt-6">
                        <li class="relative px-6 py-3">
                            <a
                                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
                                href="DasboardPetani.php"
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
                                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
                                    ></path>
                                </svg>
                                <span class="ml-4">Dashboard</span>
                            </a>
                        </li>
                    </ul>
                    <ul>
                        <li class="relative px-6 py-3">
                            <a
                                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
                                href="DataLahan.php"
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
                                <span class="ml-4">Data Lahan & Penanaman</span>
                            </a>
                        </li>
                        <li class="relative px-6 py-3">
                            <a
                                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
                                href="kebun.php"
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
                                    <path d="M4 14V8a2 2 0 012-2h12a2 2 0 012 2v6m-4 0v4m-8-4v4m0-8h8m-8 0V6"></path>
                                </svg>
                                <span class="ml-4">Manajemen Lahan (Master)</span>
                            </a>
                        </li>
                        <li class="relative px-6 py-3">
                            <a
                                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
                                href="KelolaProduk.php"
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
                                <span class="ml-4">Kelola Produk</span>
                            </a>
                        </li>

                        <li class="relative px-6 py-3">
                            <a
                                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
                                href="PesananMasuk.php"
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
                                <span class="ml-4">Pesanan Masuk</span>
                            </a>
                        </li> 
                        <li class="relative px-6 py-3">
                            <a
                                class="inline-flex items-center w-full text-sm font-semibold transition-colors duration-150 hover:text-gray-800 dark:hover:text-gray-200"
                                href="CatatanKeuangan.php"
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
                                    <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0c-1.657 0-3-.895-3-2s1.343-2 3-2 3-.895 3-2 1.343-2 3-2m0 8c1.11 0 2.08-.402 2.599-1M12 16V7m0 9v-1m0-8V7m0 0h.01M6 12h.01M6 12h.01M6 12h.01M18 12h.01M18 12h.01M18 12h.01M6 9h.01M6 15h.01M18 9h.01M18 15h.01"></path>
                                </svg>
                                <span class="ml-4">Catatan Keuangan</span>
                            </a>
                        </li>     
                    </ul>
                </div>
            </aside>
            <div class="flex flex-col flex-1 w-full">
                <header class="z-10 py-4 bg-white shadow-md dark:bg-gray-800">
                    <div
                        class="container flex items-center justify-between h-full px-6 mx-auto text-green-600 dark:text-green-300"
                    >
                        <button
                            class="p-1 mr-5 -ml-1 rounded-md md:hidden focus:outline-none focus:shadow-outline-green"
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
                                class="relative w-full max-w-xl mr-6 focus-within:text-green-500"
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
                                    class="w-full pl-8 pr-2 text-sm text-gray-700 placeholder-gray-600 bg-gray-100 border-0 rounded-md dark:placeholder-gray-500 dark:focus:shadow-outline-gray dark:focus:placeholder-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:placeholder-gray-500 focus:bg-white focus:border-green-300 focus:outline-none focus:shadow-outline-green form-input"
                                    type="text"
                                    placeholder="Search for projects"
                                    aria-label="Search"
                                />
                            </div>
                        </div>
                        <ul class="flex items-center flex-shrink-0 space-x-6">
                            <li class="flex">
                                <button
                                    class="rounded-md focus:outline-none focus:shadow-outline-green"
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
                                    class="align-middle rounded-full focus:shadow-outline-green focus:outline-none"
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
  <div class="container grid px-6 mx-auto" x-data="{ 
      isEditing: false, 
      fotoPreviewUrl: '<?php echo htmlspecialchars($profil['foto_profil']); ?>?t=<?php echo time(); ?>',
      updateFotoPreview(event) {
          const file = event.target.files[0];
          if (file) {
              this.fotoPreviewUrl = URL.createObjectURL(file);
          }
      } 
    }">

    <div class="flex items-center justify-between my-6">
      <h2 class="text-2xl font-semibold text-gray-700 dark:text-gray-200">
        Profil Diri
      </h2>

      <div class="bg-blue-500 p-2 rounded-lg">
        <button
          @click="isEditing = !isEditing"
          class="flex items-center px-3 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 focus:outline-none focus:shadow-outline-green"
        >
          <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
          </svg>
          <span x-text="isEditing ? 'Batal Edit' : 'Edit Profil'"></span>
        </button>
      </div>
    </div>

    <?php if (!empty($pesan_sukses)): ?>
      <div class="px-4 py-3 mb-4 text-sm text-white bg-green-600 rounded-lg"><?php echo $pesan_sukses; ?></div>
    <?php endif; ?>
    <?php if (!empty($pesan_error)): ?>
      <div class="px-4 py-3 mb-4 text-sm text-white bg-red-600 rounded-lg"><?php echo $pesan_error; ?></div>
    <?php endif; ?>

    <div x-show="!isEditing" x-transition class="grid gap-6 mb-8 md:grid-cols-3">
      <div class="p-4 bg-white rounded-lg shadow-md dark:bg-gray-800 md:col-span-1">
        <div class="relative">
          <div class="h-24 bg-green-500 rounded-t-lg"></div>
          <img src="./<?php echo htmlspecialchars($profil['foto_profil']); ?>" class="w-32 h-32 object-cover border-4 border-white rounded-full mx-auto -mt-16" onerror="this.src='./assets/img/profil/default.png'">
        </div>

        <div class="text-center mt-4">
          <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($profil['nama']); ?></h3>
          <p class="text-sm text-gray-500 capitalize"><?php echo htmlspecialchars($profil['role'] ?? 'Petani'); ?></p>
          <span class="mt-2 inline-block px-3 py-1 text-xs font-semibold text-green-600 bg-green-100 rounded-full">VERIFIED</span>
        </div>

        <hr class="my-4 dark:border-gray-700">

        <h4 class="font-semibold mb-2 text-gray-700 dark:text-gray-200">Bio Singkat</h4>
        <p class="text-sm leading-relaxed text-gray-700 dark:text-gray-200"><?php echo htmlspecialchars($profil['bio'] ?: 'Belum ada bio.'); ?></p>
      </div>
      
      <div class="p-6 bg-white rounded-lg shadow-md dark:bg-gray-800 md:col-span-2">
        <h4 class="font-semibold mb-4 text-gray-700 dark:text-gray-200">Identitas</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
          <div>
            <p class="text-gray-500 dark:text-gray-400">Nama Lengkap</p>
            <p class="font-medium text-gray-700 dark:text-gray-200"><?php echo htmlspecialchars($profil['nama']); ?></p>
          </div>
          <div>
            <p class="text-gray-500 dark:text-gray-400">Email</p>
            <p class="font-medium text-gray-700 dark:text-gray-200"><?php echo htmlspecialchars($profil['email']); ?></p>
          </div>
          <div>
            <p class="text-gray-500 dark:text-gray-400">Telepon</p>
            <p class="font-medium text-gray-700 dark:text-gray-200"><?php echo htmlspecialchars($profil['telepon'] ?: 'Belum diatur'); ?></p>
          </div>
          <div>
            <p class="text-gray-500 dark:text-gray-400">Jenis Kelamin</p>
            <p class="font-medium text-gray-700 dark:text-gray-200 capitalize"><?php echo htmlspecialchars($profil['jenis_kelamin'] ?: '-'); ?></p>
          </div>
        </div>
      </div>
    </div>

    <div x-show="isEditing" x-transition class="p-6 bg-white rounded-lg shadow-md dark:bg-gray-800">
      <form method="POST" action="profil.php" enctype="multipart/form-data">

        <div class="mb-6">
            <div class="text-center mb-2">
                <span class="text-gray-700 dark:text-gray-400 text-sm font-semibold"></span>
            </div>
            
            <div class="flex justify-center">
                <div 
                    class="relative w-32 h-32 group cursor-pointer"
                    @click="$refs.fotoProfilInput.click()"
                    title="Klik untuk ganti foto"
                >
                    <img 
                        :src="fotoPreviewUrl" 
                        alt="Foto Profil" 
                        class="w-32 h-32 rounded-full object-cover border-4 border-gray-200 dark:border-gray-600 shadow-md transition-opacity duration-200 group-hover:opacity-60"
                        style="width: 8rem; height: 8rem; object-fit: cover;"
                        onerror="this.src='./assets/img/profil/default.png'"
                    >
                    
                    <div 
                        class="absolute inset-0 flex items-center justify-center rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-200"
                        aria-hidden="true"
                    >
                        <div class="bg-black bg-opacity-50 p-2 rounded-full">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </div>
                    </div>
                    
                    <input 
                        type="file"
                        name="foto_profil"
                        x-ref="fotoProfilInput"
                        @change="updateFotoPreview"
                        accept="image/png, image/jpeg"
                        class="hidden"
                    >
                </div>
            </div>
            <p class="text-center text-xs text-gray-500 mt-3 dark:text-gray-400">Klik foto untuk mengubah. Maksimal 2MB.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <label class="block text-sm">
            <span class="text-gray-700 dark:text-gray-400">Nama Lengkap</span>
            <input name="nama" value="<?php echo htmlspecialchars($profil['nama']); ?>" required class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-green-400 focus:outline-none focus:shadow-outline-green dark:text-gray-300 form-input">
          </label>
          
          <label class="block text-sm">
            <span class="text-gray-700 dark:text-gray-400">Email (Tidak bisa diubah)</span>
            <input value="<?php echo htmlspecialchars($profil['email']); ?>" disabled class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 text-gray-400 dark:text-gray-500 form-input cursor-not-allowed">
          </label>

          <label class="block text-sm">
            <span class="text-gray-700 dark:text-gray-400">Nomor Telepon</span>
            <input name="telepon" value="<?php echo htmlspecialchars($profil['telepon']); ?>" class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-green-400 focus:outline-none focus:shadow-outline-green dark:text-gray-300 form-input">
          </label>

          <label class="block text-sm">
            <span class="text-gray-700 dark:text-gray-400">Jenis Kelamin</span>
            <select name="jenis_kelamin" class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-green-400 focus:outline-none focus:shadow-outline-green dark:text-gray-300 form-select">
              <option value="" <?php echo ($profil['jenis_kelamin'] == "" ? 'selected' : ''); ?>>- Pilih -</option>
              <option value="Laki-laki" <?php echo ($profil['jenis_kelamin'] == "Laki-laki" ? 'selected' : ''); ?>>Laki-laki</option>
              <option value="Perempuan" <?php echo ($profil['jenis_kelamin'] == "Perempuan" ? 'selected' : ''); ?>>Perempuan</option>
            </select>
          </label>
          
          <label class="block text-sm md:col-span-2">
            <span class="text-gray-700 dark:text-gray-400">Bio</span>
            <textarea name="bio" rows="4" class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-green-400 focus:outline-none focus:shadow-outline-green dark:text-gray-300 form-textarea"><?php echo htmlspecialchars($profil['bio']); ?></textarea>
          </label>
        </div> 
        
        <div class="text-right mt-6">
          <button
            name="simpan_profil"
            class="px-5 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:shadow-outline-blue"
          >
            Simpan Perubahan
          </button>
        </div>

      </form>
    </div>
  </div>
</main>


            </div>
        </div>
    </body>
</html>