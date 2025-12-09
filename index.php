<?php
// File ini akan mengalihkan pengguna dari
// http://localhost/windmill-dashboard/
// ke halaman landing page Anda yang sebenarnya.

// PERBAIKAN: Path diubah dari 'public/pages/index.php' 
// menjadi 'public/Index.php' (sesuai screenshot baru Anda)
header("Location: public/Index.php");
exit;
?>