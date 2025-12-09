<?php
session_start();
// Asumsi Connection.php berada di folder yang sama (public/pages)
require_once 'Connection.php'; 

$error_message = '';
$success_message = '';

// Cek jika ada request POST (seseorang menekan tombol submit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? null;

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Silakan masukkan email yang valid.';
    } else {
        // 1. Cek apakah email ada di database
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            // Email tidak ditemukan.
            // Untuk keamanan, kita tetap tampilkan pesan sukses generik.
            $success_message = 'Jika email Anda terdaftar, instruksi pemulihan telah dibuat.';
        } else {
            // Email ditemukan
            $user = $result->fetch_assoc();
            
            // 2. Buat token unik dan waktu kedaluwarsa (1 jam)
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 jam dari sekarang

            // 3. Simpan token ke database untuk user ini
            // (Ini membutuhkan kolom 'reset_token' dan 'reset_token_expires_at' di tabel 'users')
            try {
                $update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires_at = ? WHERE id = ?");
                $update_stmt->bind_param('ssi', $token, $expires, $user['id']);
                $update_stmt->execute();

                if ($update_stmt->affected_rows > 0) {
                    // 4. Buat link reset
                    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
                    // Asumsi file ResetPassword.php ada di folder yang sama
                    $reset_link = $base_url . dirname($_SERVER['PHP_SELF']) . '/ResetPassword.php?token=' . $token;

                    // 5. Tampilkan pesan sukses (Simulasi pengiriman email)
                    $success_message = "<b>SIMULASI EMAIL:</b> Link pemulihan telah dibuat. Di aplikasi sungguhan, ini akan dikirim ke email Anda. <br><br> Klik link di bawah untuk reset: <br><br> <a href='$reset_link' class='text-purple-400 hover:underline break-all'>$reset_link</a>";

                } else {
                    $error_message = 'Gagal membuat token pemulihan. Coba lagi.';
                }
                $update_stmt->close();
            } catch (Exception $e) {
                $error_message = 'Gagal memperbarui database. Pastikan Anda sudah menambah kolom `reset_token` dan `reset_token_expires_at` di tabel `users`.';
            }
        }
        $stmt->close();
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html :class="{ 'theme-dark': dark }" x-data="data()" lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Forgot password - Windmill Dashboard</title>
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
                Forgot password
              </h1>

              <!-- Tampilkan Pesan Sukses atau Error -->
              <?php if ($success_message): ?>
                  <div class="px-4 py-3 mb-4 text-sm font-medium text-white bg-green-600 rounded-lg" role="alert">
                      <?php echo $success_message; // Menggunakan echo agar tag <a> di link simulasi bisa dirender ?>
                  </div>
              <?php endif; ?>
              <?php if ($error_message): ?>
                  <div class="px-4 py-3 mb-4 text-sm font-medium text-white bg-red-600 rounded-lg" role="alert">
                      <?php echo htmlspecialchars($error_message); ?>
                  </div>
              <?php endif; ?>

              <!-- Sembunyikan form jika permintaan sukses -->
              <?php if (empty($success_message)): ?>
                <form method="POST" action="LupaPassword.php">
                  <label class="block text-sm">
                    <span class="text-gray-700 dark:text-gray-400">Email</span>
                    <input
                      type="email"
                      name="email"
                      class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300 dark:focus:shadow-outline-gray form-input"
                      placeholder="nama@email.com"
                      required
                    />
                  </label>

                  <!-- Mengganti <a> dengan <button> agar bisa submit form -->
                  <button
                    type="submit"
                    class="block w-full px-4 py-2 mt-4 text-sm font-medium leading-5 text-center text-white transition-colors duration-150 bg-purple-600 border border-transparent rounded-lg active:bg-purple-600 hover:bg-purple-700 focus:outline-none focus:shadow-outline-purple"
                  >
                    Recover password
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

            </div>
          </div>
        </div>
      </div>
    </div>
  </body>
</html>