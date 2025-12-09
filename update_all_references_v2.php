<?php
function updateFileReferences($file) {
    if (!file_exists($file)) {
        echo "File not found: $file\n";
        return;
    }

    $content = file_get_contents($file);
    if ($content === false) {
        echo "Could not read file: $file\n";
        return;
    }

    // Generic replacements for common patterns
    $replacements = [
        'href="./index.html"' => 'href="./index.php"',
        'href="./login.html"' => 'href="./login.php"',
        'href="./create-account.html"' => 'href="./create-account.php"',
        'href="./forgot-password.html"' => 'href="./forgot-password.php"',
        'href="pages/login.html"' => 'href="pages/login.php"',
        'href="pages/create-account.html"' => 'href="pages/create-account.php"',
        'href="pages/forgot-password.html"' => 'href="pages/forgot-password.php"',
        'href="DasboardPetani.html"' => 'href="DasboardPetani.php"',
        'href="DataLahan.html"' => 'href="DataLahan.php"',
        'href="KelolaProduk.html"' => 'href="KelolaProduk.php"',
        'href="PesananMasuk.html"' => 'href="PesananMasuk.php"',
        'href="DasboardPembeli.html"' => 'href="DasboardPembeli.php"',
        'href="KeranjangProduk.html"' => 'href="KeranjangProduk.php"',
        'href="ProdukKKatalog.html"' => 'href="ProdukKKatalog.php"',
        'href="RiwayatTransaksi.html"' => 'href="RiwayatTransaksi.php"',
        'href="../index.html"' => 'href="../index.php"',
        'href="pages/profile.html"' => 'href="pages/profile.php"',
        'href="pages/settings.html"' => 'href="pages/settings.php"',
        'href="pages/404.html"' => 'href="pages/404.php"',
        'href="pages/blank.html"' => 'href="pages/blank.php"',
        'href="index.html"' => 'href="index.php"',
        'href="forms.html"' => 'href="forms.php"',
        'href="cards.html"' => 'href="cards.php"',
        'href="charts.html"' => 'href="charts.php"',
        'href="buttons.html"' => 'href="buttons.php"',
        'href="modals.html"' => 'href="modals.php"',
        'href="tables.html"' => 'href="tables.php"',
    ];

    $originalContent = $content;
    foreach ($replacements as $search => $replace) {
        $content = str_replace($search, $replace, $content);
    }

    if ($content !== $originalContent) {
        if (file_put_contents($file, $content) !== false) {
            echo "Updated references in: $file\n";
        } else {
            echo "Failed to write to file: $file\n";
        }
    } else {
        echo "No changes needed in: $file\n";
    }
}

// List of directories to process
$directories = [
    'public',
    'public/pages',
    'User_Pembeli',
    'User_Pembeli/pages',
    'User_petani',
    'User_petani/pages'
];

// Process all PHP files in each directory
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        echo "Directory not found: $dir\n";
        continue;
    }

    $files = glob($dir . '/*.php');
    foreach ($files as $file) {
        updateFileReferences($file);
    }
}

echo "All files processed!\n";
?>