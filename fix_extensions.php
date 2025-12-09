<?php
$file = 'User_petani/DasboardPetani.php';
$content = file_get_contents($file);

// Array of replacements [search => replace]
$replacements = [
    'href="DasboardPetani.html"' => 'href="DasboardPetani.php"',
    'href="DataLahan.html"' => 'href="DataLahan.php"',
    'href="KelolaProduk.html"' => 'href="KelolaProduk.php"',
    'href="PesananMasuk.html"' => 'href="PesananMasuk.php"',
    'href="pages/profile.html"' => 'href="pages/profile.php"',
    'href="pages/settings.html"' => 'href="pages/settings.php"',
    'href="pages/create-account.html"' => 'href="pages/create-account.php"',
    'href="pages/forgot-password.html"' => 'href="pages/forgot-password.php"',
];

foreach ($replacements as $search => $replace) {
    $content = str_replace($search, $replace, $content);
}

file_put_contents($file, $content);
echo "File updated successfully!\n";
?>