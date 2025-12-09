<?php
session_start();
require_once 'Connection.php';

$token = $_GET['token'] ?? null;
$error_message = '';
$success_message = '';
$show_form = false;
$user_id = null;

if (empty($token)) {
    $error_message = 'Token tidak ditemukan. Link tidak valid.';
} else {
    // 1. Validasi token
    $stmt = $conn->prepare("SELECT id, reset_token_expires_at FROM users WHERE reset_token = ?");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $error_message = 'Token tidak valid. Silakan ulangi permintaan lupa password.';
    } else {
        $user = $result->fetch_assoc();
        $expires = $user['reset_token_expires_at'];
        
        // 2. Cek apakah token sudah kedaluwarsa
        if (strtotime($expires) < time()) {
            $error_message = 'Token pemulihan sudah kedaluwarsa. Silakan ulangi permintaan.';
        } else {
            // Token valid dan belum kedaluwarsa
            $show_form = true;
            $user_id = $user['id'];
        }
    }
    $stmt->close();
}

// 3. Proses form jika password baru disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $show_form) {
    $password_baru = $_POST['password_baru'] ?? '';
    $konfirmasi_password = $_POST['konfirmasi_password'] ?? '';

    if (empty($password_baru) || strlen($password_baru) < 6) {
        $error_message = 'Password minimal harus 6 karakter.';
    } elseif ($password_baru !== $konfirmasi_password) {
        $error_message = 'Password dan konfirmasi password tidak cocok.';
    } else {
        // Semua validasi lolos
        try {
            // 4. Hash password baru
            $hashed_password = password_hash($password_baru, PASSWORD_DEFAULT);
            
            // 5. Update password baru dan hapus token
            $update_stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires_at = NULL WHERE id = ?");
            $update_stmt->bind_param('si', $hashed_password, $user_id);
            $update_stmt->execute();

            if ($update_stmt->affected_rows > 0) {
                $success_message = 'Password Anda telah berhasil diperbarui. Silakan login.';
                $show_form = false; // Sembunyikan form setelah sukses
            } else {
                $error_message = 'Gagal memperbarui password. Coba lagi.';
            }
            $update_stmt->close();

        } catch (Exception $e) {
            $error_message = 'Terjadi kesalahan database: ' . $e->getMessage();
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html :class="{ 'theme-dark': dark }" x-data="data()" lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Reset Password - Windmill Dashboard</title>
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="../assets/css/tailwind.output.css" />
    <script
      src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.min.js"
      defer
    ></script>
    <script src="../assets/js/init-alpine.js"></script>
  </head>
  <body>
    <div class="flex items-center min-h-screen p-6 bg-gray-50 dark:bg-gray-900">
      <div
        class="flex-1 h-full max-w-4xl mx-auto overflow-hidden bg-white rounded-lg shadow-xl dark:bg-gray-800"
      >
        <div class="flex flex-col overflow-y-auto md:flex-row">
          <div class="h-32 md:h-auto md:w-1/2">
            <img
              aria-hidden="true"
              class="object-cover w-full h-full dark:hidden"
              src="../assets/img/forgot-password-office.jpeg"
              alt="Office"
            />
            <img
              aria-hidden="true"
              class="hidden object-cover w-full h-full dark:block"
              src="../assets/img/forgot-password-office-dark.jpeg"
              alt="Office"
            />
          </div>
          <div class="flex items-center justify-center p-6 sm:p-12 md:w-1/2">
            <div class="w-full">
              <h1
                class="mb-4 text-xl font-semibold text-gray-700 dark:text-gray-200"
              >
                Reset Password
              </h1>

              <!-- Tampilkan Pesan Sukses atau Error -->
              <?php if ($success_message): ?>
                  <div class="px-4 py-3 mb-4 text-sm font-medium text-white bg-green-600 rounded-lg" role="alert">
                      <?php echo htmlspecialchars($success_message); ?>
                  </div>
              <?php endif; ?>
              <?php if ($error_message): ?>
                  <div class="px-4 py-3 mb-4 text-sm font-medium text-white bg-red-600 rounded-lg" role="alert">
                      <?php echo htmlspecialchars($error_message); ?>
                  </div>
              <?php endif; ?>

              <!-- Tampilkan form HANYA jika token valid -->
              <?php if ($show_form): ?>
                <form method="POST" action="ResetPassword.php?token=<?php echo htmlspecialchars($token); ?>">
                  
                  <label class="block text-sm mt-4">
                    <span class="text-gray-700 dark:text-gray-400">Password Baru</span>
                    <input
                      type="password"
                      name="password_baru"
                      class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300 dark:focus:shadow-outline-gray form-input"
                      placeholder="Minimal 6 karakter"
                      required
                    />
                  </label>

                  <label class="block text-sm mt-4">
                    <span class="text-gray-700 dark:text-gray-400">Konfirmasi Password Baru</span>
                    <input
                      type="password"
                      name="konfirmasi_password"
                      class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300 dark:focus:shadow-outline-gray form-input"
                      placeholder="Ulangi password"
                      required
                    />
                  </label>

                  <button
                    type="submit"
                    class="block w-full px-4 py-2 mt-4 text-sm font-medium leading-5 text-center text-white transition-colors duration-150 bg-purple-600 border border-transparent rounded-lg active:bg-purple-600 hover:bg-purple-700 focus:outline-none focus:shadow-outline-purple"
                  >
                    Simpan Password Baru
                  </button>
                </form>
              <?php endif; ?>

              <hr class="my-8">
              <p class="mt-4">
                <a
                  class="text-sm font-medium text-purple-600 dark:text-purple-400 hover:underline"
                  href="./Login.php"
                >
                  Kembali ke halaman Login
                </a>
              </p>
              <?php if (!$success_message): ?>
              <p class="mt-2">
                <a
                  class="text-sm font-medium text-purple-600 dark:text-purple-400 hover:underline"
                  href="./LupaPassword.php"
                >
                  Kirim ulang link pemulihan?
                </a>
              </p>
              <?php endif; ?>

            </div>
          </div>
        </div>
      </div>
    </div>
  </body>
</html>