<?php
session_start();

// ==========================================================
// 1. PERBAIKAN KONEKSI:
// Naik 2 level dari '.../public/pages/' ke '.../windmill-dashboard/'
include 'Connection.php';
// ==========================================================


// ==========================================================
// 2. PERBAIKAN BLOK 1 (JIKA SUDAH LOGIN):
// Naik 2 level + Nama folder 'User_Pembeli' yang benar
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'pembeli') {
        header("Location: ../../User_Pembeli/ProdukKKatalog.php"); 
        exit;
    } elseif ($_SESSION['role'] === 'petani') {
        header("Location: ../../User_petani/DasboardPetani.php"); 
        exit;
    }
}
// ==========================================================

$login_error = ""; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password_input = $_POST['password']; // Password yang diketik

    $stmt = mysqli_prepare($conn, "SELECT id, nama, password, role FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    
    // Ini adalah password dari database (yang plain text)
    mysqli_stmt_bind_result($stmt, $id, $nama, $password_dari_db, $role);
    $found = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    if ($found) {
        // ==========================================================
        // 3. PERBAIKAN LOGIKA PASSWORD (TIDAK AMAN):
        // Menggunakan '===' untuk mencocokkan plain text
        if ($password_input === $password_dari_db) {
        // ==========================================================
            
            // Password COCOK
            $_SESSION['user_id'] = $id;
            $_SESSION['nama'] = $nama;
            $_SESSION['email'] = $email;
            $_SESSION['role'] = $role;

            // ==========================================================
            // 4. PERBAIKAN BLOK 2 (REDIRECT SETELAH LOGIN):
            // Naik 2 level + Nama folder 'User_Pembeli' yang benar
            if ($role === 'pembeli') {
                header("Location: ../../User_Pembeli/ProdukKKatalog.php");
                exit;
            } elseif ($role === 'petani') {
                header("Location: ../../User_petani/DasboardPetani.php");
                exit;
            } else {
                $login_error = "Role tidak dikenali.";
            }
            // ==========================================================
        } else {
            $login_error = "Email atau Password salah.";
        }
    } else {
        $login_error = "Email atau Password salah.";
    }
}
?>

<!DOCTYPE html>
<html :class="{ 'theme-dark': dark }" x-data="data()" lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login - Windmill Dashboard</title>
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
              src="../assets/img/login-office.jpeg"
              alt="Office"
            />
            <img
              aria-hidden="true"
              class="hidden object-cover w-full h-full dark:block"
              src="../assets/img/login-office-dark.jpeg"
              alt="Office"
            />
          </div>
          <div class="flex items-center justify-center p-6 sm:p-12 md:w-1/2">
            <div class="w-full">
              <h1
                class="mb-4 text-xl font-semibold text-gray-700 dark:text-gray-200"
              >
                Login
              </h1>

              <?php if (!empty($login_error)): ?>
                <div style="padding: 10px; margin-bottom: 15px; border-radius: 5px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;">
                    <?php echo htmlspecialchars($login_error); ?>
                </div>
              <?php endif; ?>

              <form method="post" action="Login.php">
                <label class="block text-sm">
                  <span class="text-gray-700 dark:text-gray-400">Email</span>
                  <input
                    name="email"
                    type="email"
                    required
                    class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300 dark:focus:shadow-outline-gray form-input"
                    placeholder="jane@example.com"
                  />
                </label>
                <label class="block mt-4 text-sm">
                  <span class="text-gray-700 dark:text-gray-400">Password</span>
                  <input
                    name="password"
                    type="password"
                    required
                    class="block w-full mt-1 text-sm dark:border-gray-600 dark:bg-gray-700 focus:border-purple-400 focus:outline-none focus:shadow-outline-purple dark:text-gray-300 dark:focus:shadow-outline-gray form-input"
                    placeholder="***************"
                  />
                </label>

                <button
                  type="submit"
                  class="block w-full px-4 py-2 mt-4 text-sm font-medium leading-5 text-center text-white transition-colors duration-150 bg-purple-600 border border-transparent rounded-lg active:bg-purple-600 hover:bg-purple-700 focus:outline-none focus:shadow-outline-purple"
                >
                  Log in
                </button>
              </form>

      

             

              <p class="mt-4">
                <a
                  class="text-sm font-medium text-purple-600 dark:text-purple-400 hover:underline"
                  href="./LupaPassword.php"
                >
                  Forgot your password?
                </a>
              </p>
              <p class="mt-1">
                <a
                  class="text-sm font-medium text-purple-600 dark:text-purple-400 hover:underline"
                  href="./create-account.php"
                >
                  Create account
                </a>
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>
</html>