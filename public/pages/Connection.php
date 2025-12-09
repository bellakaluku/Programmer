<?php

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

error_reporting(E_ALL); 

$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'ebisnis';


$conn = @mysqli_connect($host, $user, $pass, $db);


if (!$conn) {

    die("Koneksi Database Gagal: " . mysqli_connect_error());
}


mysqli_set_charset($conn, "utf8");
?>