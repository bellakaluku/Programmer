<?php
$file = 'User_petani/pages/login.php';
$content = file_get_contents($file);

// Array of replacements [search => replace]
$replacements = [
    'href="../index.html"' => 'href="../index.php"',
    'href="./forgot-password.html"' => 'href="./forgot-password.php"',
    'href="./create-account.html"' => 'href="./create-account.php"'
];

foreach ($replacements as $search => $replace) {
    $content = str_replace($search, $replace, $content);
}

file_put_contents($file, $content);
echo "Login.php file updated successfully!\n";

// Now update create-account.php
$file = 'User_petani/pages/create-account.php';
$content = file_get_contents($file);

$replacements = [
    'href="../index.html"' => 'href="../index.php"',
    'href="./login.html"' => 'href="./login.php"',
    'href="./forgot-password.html"' => 'href="./forgot-password.php"'
];

foreach ($replacements as $search => $replace) {
    $content = str_replace($search, $replace, $content);
}

file_put_contents($file, $content);
echo "Create-account.php file updated successfully!\n";

// Now update forgot-password.php
$file = 'User_petani/pages/forgot-password.php';
$content = file_get_contents($file);

$replacements = [
    'href="./login.html"' => 'href="./login.php"',
    'href="./create-account.html"' => 'href="./create-account.php"'
];

foreach ($replacements as $search => $replace) {
    $content = str_replace($search, $replace, $content);
}

file_put_contents($file, $content);
echo "Forgot-password.php file updated successfully!\n";
?>