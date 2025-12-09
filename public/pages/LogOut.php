<?php
// 1. Selalu mulai session di awal
session_start();

// 2. Hapus semua variabel session
$_SESSION = array();

// 3. Hancurkan session
session_destroy();

// 4. Arahkan pengguna kembali ke halaman Login
// (Karena logout.php dan Login.php ada di folder 'pages' yang sama,
// kita bisa langsung panggil nama filenya)
header("Location: Login.php");
exit;
?>