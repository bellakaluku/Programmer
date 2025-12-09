-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Dec 04, 2025 at 05:17 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ebisnis`
--

-- --------------------------------------------------------

--
-- Table structure for table `alamat`
--

CREATE TABLE `alamat` (
  `id` int NOT NULL,
  `pembeli_id` int NOT NULL,
  `judul_alamat` varchar(100) NOT NULL COMMENT 'Contoh: Rumah, Kantor',
  `nama_penerima` varchar(255) NOT NULL,
  `telepon_penerima` varchar(20) NOT NULL,
  `alamat_lengkap` text NOT NULL,
  `kota` varchar(100) DEFAULT NULL,
  `provinsi` varchar(100) DEFAULT NULL,
  `kode_pos` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `alamat`
--

INSERT INTO `alamat` (`id`, `pembeli_id`, `judul_alamat`, `nama_penerima`, `telepon_penerima`, `alamat_lengkap`, `kota`, `provinsi`, `kode_pos`) VALUES
(4, 2, 'jln  trans sulawesih', 'ilham', '082349166049', 'sulawsih tengah kab banggai', 'Kota Luwuk', NULL, '00000');

-- --------------------------------------------------------

--
-- Table structure for table `detail_pesanan`
--

CREATE TABLE `detail_pesanan` (
  `id` int NOT NULL,
  `pesanan_id` int NOT NULL,
  `produk_id` int NOT NULL,
  `jumlah_kg` int NOT NULL,
  `harga_kg_saat_beli` int NOT NULL,
  `subtotal` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `detail_pesanan`
--

INSERT INTO `detail_pesanan` (`id`, `pesanan_id`, `produk_id`, `jumlah_kg`, `harga_kg_saat_beli`, `subtotal`) VALUES
(24, 27, 15, 1, 100000, 100000),
(25, 28, 4, 1, 350000, 350000),
(26, 28, 6, 4, 350000, 1400000),
(27, 28, 13, 6, 100000, 600000),
(28, 28, 15, 2, 100000, 200000),
(29, 29, 12, 1, 100000, 100000),
(30, 30, 12, 1, 100000, 100000),
(31, 30, 13, 1, 100000, 100000),
(32, 30, 15, 3, 100000, 300000),
(33, 30, 16, 3, 25000, 75000),
(34, 31, 12, 1, 100000, 100000),
(35, 32, 12, 3, 100000, 300000),
(36, 32, 19, 10, 100000, 1000000),
(37, 33, 18, 10, 100000, 1000000),
(38, 34, 14, 5, 100000, 500000),
(39, 35, 4, 3, 350000, 1050000),
(40, 36, 17, 1, 25000, 25000),
(41, 37, 21, 1, 350000, 350000),
(42, 38, 12, 1, 100000, 100000),
(43, 39, 16, 1, 25000, 25000),
(44, 40, 21, 1, 350000, 350000),
(45, 41, 17, 1, 25000, 25000),
(46, 42, 6, 1, 350000, 350000),
(47, 43, 20, 1, 350000, 350000),
(48, 44, 12, 1, 100000, 100000),
(49, 45, 16, 1, 25000, 25000),
(50, 46, 8, 1, 350000, 350000),
(51, 47, 12, 1, 100000, 100000),
(52, 48, 12, 1, 100000, 100000),
(53, 49, 22, 1, 350000, 350000),
(54, 51, 22, 1, 350000, 350000),
(55, 53, 16, 1, 25000, 25000),
(56, 55, 6, 1, 350000, 350000),
(57, 57, 20, 1, 350000, 350000),
(58, 59, 6, 1, 350000, 350000),
(59, 60, 16, 1, 25000, 25000),
(60, 61, 22, 1, 350000, 350000),
(61, 62, 23, 1, 10000, 10000),
(62, 63, 16, 1, 25000, 25000),
(63, 63, 22, 1, 350000, 350000),
(64, 64, 22, 1, 350000, 350000),
(65, 65, 23, 1, 10000, 10000),
(66, 66, 16, 1, 25000, 25000),
(67, 66, 22, 1, 350000, 350000),
(68, 67, 23, 1, 10000, 10000),
(69, 68, 23, 1, 10000, 10000),
(70, 69, 22, 1, 350000, 350000),
(71, 70, 8, 1, 350000, 350000),
(72, 71, 16, 1, 25000, 25000),
(73, 72, 16, 1, 25000, 25000),
(74, 73, 16, 1, 25000, 25000),
(75, 74, 16, 1, 25000, 25000),
(76, 75, 16, 1, 25000, 25000),
(77, 76, 16, 1, 25000, 25000),
(78, 77, 22, 1, 350000, 350000);

-- --------------------------------------------------------

--
-- Table structure for table `kategori`
--

CREATE TABLE `kategori` (
  `id` int NOT NULL,
  `nama_kategori` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `kategori`
--

INSERT INTO `kategori` (`id`, `nama_kategori`, `slug`) VALUES
(1, 'Sayuran', 'sayuran'),
(2, 'Buah-buahan', 'buah-buahan'),
(3, 'Rempah', 'rempah'),
(4, 'Umbi-umbian', 'umbi-umbian');

-- --------------------------------------------------------

--
-- Table structure for table `keranjang`
--

CREATE TABLE `keranjang` (
  `id` int NOT NULL,
  `pembeli_id` int NOT NULL,
  `produk_id` int NOT NULL,
  `jumlah_kg` int NOT NULL DEFAULT '1',
  `tanggal_ditambahkan` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `keranjang`
--

INSERT INTO `keranjang` (`id`, `pembeli_id`, `produk_id`, `jumlah_kg`, `tanggal_ditambahkan`) VALUES
(19, 4, 6, 1, '2025-11-13 18:23:50'),
(83, 2, 23, 1, '2025-11-19 20:59:18');

-- --------------------------------------------------------

--
-- Table structure for table `lahan`
--

CREATE TABLE `lahan` (
  `id` int NOT NULL,
  `petani_id` int NOT NULL,
  `lahan_id` int DEFAULT NULL,
  `nama_lahan` varchar(255) NOT NULL,
  `jenis_tanaman` varchar(255) NOT NULL,
  `mulai_tanam` date NOT NULL,
  `tanggal_panen` date DEFAULT NULL,
  `biaya_modal` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `lahan`
--

INSERT INTO `lahan` (`id`, `petani_id`, `lahan_id`, `nama_lahan`, `jenis_tanaman`, `mulai_tanam`, `tanggal_panen`, `biaya_modal`) VALUES
(20, 5, NULL, 'lahan Pisang', 'pisang', '2025-11-19', '2025-11-19', 10000),
(22, 1, NULL, 'Lahan Pisang', 'Pisang', '2025-11-13', '2025-11-27', 3000000),
(23, 1, NULL, 'lahan cabe', 'Cabai', '2025-10-26', '2025-01-07', 22000000),
(24, 1, NULL, 'Lahan Pisang', 'pisang', '2025-10-26', '2025-12-14', 22000000),
(25, 1, NULL, 'lahan cabe', 'cabai', '2025-07-16', '2025-11-23', 2000000);

-- --------------------------------------------------------

--
-- Table structure for table `manajemen_lahan`
--

CREATE TABLE `manajemen_lahan` (
  `id` int NOT NULL,
  `petani_id` int NOT NULL,
  `nama_lahan` varchar(255) DEFAULT NULL,
  `luas` decimal(10,2) DEFAULT NULL,
  `satuan_luas` varchar(20) DEFAULT NULL,
  `alamat_lokasi` text,
  `status_kepemilikan` varchar(50) DEFAULT NULL,
  `tanggal_dibuat` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `manajemen_lahan`
--

INSERT INTO `manajemen_lahan` (`id`, `petani_id`, `nama_lahan`, `luas`, `satuan_luas`, `alamat_lokasi`, `status_kepemilikan`, `tanggal_dibuat`) VALUES
(14, 1, 'lahan cabe', 2.00, 'Hektar', 'jl.andalas', 'Sewa', '2025-11-15'),
(15, 9, 'Lahan Cabai', 2.00, 'Hektar', 'Jl. Ahmada Dani Kota Palembang', 'Milik Sendiri', '2025-11-16'),
(16, 9, 'lahan bawang', 0.37, 'm²', 'Belakang Masjid Baiturrhaman', 'Milik Sendiri', '2025-11-18'),
(17, 9, 'Lahan Pepaya', 0.09, 'm²', 'Andalas Kota Gorontalo', 'Sewa', '2025-11-16'),
(18, 9, 'Lahan Brokoli', 0.24, 'Hektar', 'Disamping Rumah', 'Milik Sendiri', '2025-11-16'),
(19, 9, 'Lahan 4 petak', 3.00, 'Hektar', 'Pisou Sulawesi Tengah', 'Milik Sendiri', '2025-11-16'),
(20, 5, 'lahan Pisang', 0.01, 'Hektar', 'JLN Bachruddin', 'Milik Sendiri', '2025-11-17'),
(21, 1, 'Lahan Pisang', 2.00, 'Hektar', 'Jalan Kenangan', 'Milik Sendiri', '2025-11-21'),
(22, 1, 'lahan bawang', 4.00, 'Hektar', 'Jl bachruddin', 'Sewa', '2025-11-19');

-- --------------------------------------------------------

--
-- Table structure for table `pembayaran`
--

CREATE TABLE `pembayaran` (
  `id` int NOT NULL,
  `pesanan_id` int NOT NULL,
  `metode` varchar(50) NOT NULL,
  `jumlah_transfer` int NOT NULL COMMENT 'Disesuaikan dengan total_harga Anda (INT)',
  `bukti_transfer` varchar(255) NOT NULL COMMENT 'Path ke file gambar',
  `tgl_transfer` datetime NOT NULL,
  `status_verifikasi` enum('menunggu','valid','ditolak') NOT NULL DEFAULT 'menunggu'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pembayaran`
--

INSERT INTO `pembayaran` (`id`, `pesanan_id`, `metode`, `jumlah_transfer`, `bukti_transfer`, `tgl_transfer`, `status_verifikasi`) VALUES
(1, 38, 'Transfer BCA', 100000, 'bukti_pembayaran/bukti_691945e0cbb2f3.19024843.jpeg', '2025-11-22 10:32:00', 'valid'),
(2, 39, 'QRIS', 25000, 'bukti_pembayaran/bukti_69194d630297d0.67086897.jpeg', '2025-11-16 11:04:00', 'valid'),
(3, 42, 'Transfer BCA', 350000, 'bukti_pembayaran/bukti_691957eece14f4.03796541.jpeg', '2025-11-16 11:49:00', 'valid'),
(4, 44, 'Transfer Mandiri', 100000, 'bukti_pembayaran/bukti_691959df46cfd4.58452503.jpg', '2025-11-16 11:57:00', 'valid'),
(5, 45, 'Transfer BCA', 25000, 'bukti_pembayaran/bukti_69195ff23ae699.61374854.jpeg', '2025-11-16 12:23:00', 'valid'),
(6, 37, 'Transfer BCA', 350000, 'bukti_pembayaran/bukti_691964b2bdbe74.78235961.jpeg', '2025-11-16 12:44:00', 'valid'),
(7, 46, 'Transfer Mandiri', 350000, 'bukti_pembayaran/bukti_691964e4a02459.15197618.jpeg', '2025-11-20 12:44:00', 'valid'),
(8, 48, 'Transfer Mandiri', 100000, 'bukti_pembayaran/bukti_691986ce678de4.46725686.jpeg', '2025-11-16 15:09:00', 'valid'),
(9, 47, 'Transfer Mandiri', 100000, 'bukti_pembayaran/bukti_691ada1e9aafa9.69939587.jpeg', '2025-11-17 15:17:00', 'ditolak'),
(10, 60, 'Transfer BCA', 25000, 'bukti_pembayaran/bukti_691ae9dd665587.92579735.jpeg', '2025-11-17 16:24:00', 'valid'),
(11, 68, 'QRIS', 10000, 'bukti_pembayaran/bukti_691cd19353f114.73353604.png', '2025-11-19 03:05:00', 'valid'),
(12, 69, 'QRIS', 350000, 'bukti_pembayaran/bukti_691e290bed62a9.39984006.png', '2025-11-20 03:30:00', 'valid'),
(13, 73, 'Transfer BCA', 25000, 'bukti_pembayaran/bukti_69310c52e43e80.79691839.png', '2025-12-04 11:21:00', 'ditolak'),
(14, 74, 'Transfer Mandiri', 25000, 'bukti_pembayaran/bukti_69310d74a1fd38.38924060.png', '2025-12-04 11:26:00', 'ditolak'),
(15, 75, 'Transfer BCA', 25000, 'bukti_pembayaran/bukti_69310dbe7a5592.84636380.png', '2025-12-26 11:27:00', 'valid'),
(16, 76, 'Transfer BCA', 25000, 'bukti_pembayaran/bukti_69310f77065db2.91885758.png', '2025-12-04 11:34:00', 'ditolak'),
(17, 77, 'Transfer Mandiri', 350000, 'bukti_pembayaran/bukti_693110fba1a7d8.49833710.png', '2025-12-04 11:41:00', 'ditolak');

-- --------------------------------------------------------

--
-- Table structure for table `pesanan`
--

CREATE TABLE `pesanan` (
  `id` int NOT NULL,
  `pembeli_id` int NOT NULL,
  `alamat_id` int NOT NULL,
  `petani_id` int NOT NULL,
  `total_harga` int NOT NULL,
  `status_pesanan` enum('Menunggu Pembayaran','Menunggu Verifikasi','Dikemas','Dikirim','Selesai','Dibatalkan') NOT NULL DEFAULT 'Menunggu Pembayaran',
  `metode_pembayaran` varchar(50) NOT NULL,
  `tanggal_pesan` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `pesanan`
--

INSERT INTO `pesanan` (`id`, `pembeli_id`, `alamat_id`, `petani_id`, `total_harga`, `status_pesanan`, `metode_pembayaran`, `tanggal_pesan`) VALUES
(27, 2, 4, 1, 100000, 'Selesai', 'COD', '2025-11-14 12:29:34'),
(28, 2, 4, 1, 2550000, 'Dikirim', 'COD', '2025-11-14 18:27:41'),
(29, 2, 4, 1, 100000, 'Dibatalkan', 'COD', '2025-11-14 18:45:13'),
(30, 2, 4, 1, 575000, 'Dikirim', 'COD', '2025-11-15 05:26:18'),
(31, 2, 4, 1, 100000, 'Dikirim', 'Transfer Bank', '2025-11-15 06:06:54'),
(32, 2, 4, 1, 1300000, 'Menunggu Pembayaran', 'COD', '2025-11-15 12:40:58'),
(33, 2, 4, 1, 1000000, 'Menunggu Pembayaran', 'COD', '2025-11-15 13:01:03'),
(34, 2, 4, 1, 500000, 'Menunggu Pembayaran', 'COD', '2025-11-15 13:01:33'),
(35, 2, 4, 1, 1050000, 'Selesai', 'COD', '2025-11-15 13:02:03'),
(36, 2, 4, 5, 25000, 'Menunggu Pembayaran', 'Transfer Bank', '2025-11-16 02:45:07'),
(37, 2, 4, 1, 350000, 'Dikirim', 'Transfer Bank', '2025-11-16 03:10:43'),
(38, 2, 4, 1, 100000, 'Dikirim', 'Transfer Bank', '2025-11-16 03:24:28'),
(39, 2, 4, 1, 25000, 'Dikirim', 'Transfer Bank', '2025-11-16 04:04:24'),
(40, 2, 4, 1, 350000, 'Selesai', 'COD', '2025-11-16 04:14:48'),
(41, 2, 4, 5, 25000, 'Dikemas', 'COD', '2025-11-16 04:36:36'),
(42, 2, 4, 1, 350000, 'Dikirim', 'Transfer Bank', '2025-11-16 04:49:06'),
(43, 2, 4, 1, 350000, 'Dikirim', 'COD', '2025-11-16 04:57:00'),
(44, 2, 4, 1, 100000, 'Dikirim', 'Transfer Bank', '2025-11-16 04:57:35'),
(45, 2, 4, 1, 25000, 'Selesai', 'Transfer Bank', '2025-11-16 05:23:36'),
(46, 2, 4, 1, 350000, 'Selesai', 'Transfer Bank', '2025-11-16 05:44:50'),
(47, 2, 4, 1, 100000, 'Dibatalkan', 'Transfer Bank', '2025-11-16 07:22:00'),
(48, 2, 4, 1, 100000, 'Selesai', 'Transfer Bank', '2025-11-16 08:08:44'),
(49, 2, 4, 1, 350000, 'Dikirim', 'COD', '2025-11-17 09:05:02'),
(50, 2, 4, 1, 350000, 'Dikirim', 'COD', '2025-11-17 09:05:02'),
(51, 2, 4, 1, 350000, 'Menunggu Pembayaran', 'Transfer Bank', '2025-11-17 09:05:33'),
(52, 2, 4, 1, 350000, 'Menunggu Pembayaran', 'Transfer Bank', '2025-11-17 09:05:33'),
(53, 2, 4, 1, 25000, 'Menunggu Pembayaran', 'Transfer Bank', '2025-11-17 09:05:53'),
(54, 2, 4, 1, 25000, 'Menunggu Pembayaran', 'Transfer Bank', '2025-11-17 09:05:53'),
(55, 2, 4, 1, 350000, 'Selesai', 'COD', '2025-11-17 09:14:40'),
(56, 2, 4, 1, 350000, 'Dikirim', 'COD', '2025-11-17 09:14:40'),
(57, 2, 4, 1, 350000, 'Selesai', 'COD', '2025-11-17 09:16:30'),
(58, 2, 4, 1, 350000, 'Dikirim', 'COD', '2025-11-17 09:16:30'),
(59, 2, 4, 1, 350000, 'Menunggu Pembayaran', 'Transfer Bank', '2025-11-17 09:22:25'),
(60, 2, 4, 1, 25000, 'Selesai', 'Transfer Bank', '2025-11-17 09:22:46'),
(61, 2, 4, 1, 350000, 'Menunggu Pembayaran', 'Transfer Bank', '2025-11-17 10:01:38'),
(62, 2, 4, 5, 10000, 'Menunggu Pembayaran', 'Transfer Bank', '2025-11-17 10:01:38'),
(63, 2, 4, 1, 375000, 'Menunggu Pembayaran', 'Transfer Bank', '2025-11-17 10:02:47'),
(64, 2, 4, 1, 350000, 'Selesai', 'COD', '2025-11-17 10:03:22'),
(65, 2, 4, 5, 10000, 'Dikemas', 'COD', '2025-11-17 10:03:22'),
(66, 2, 4, 1, 375000, 'Menunggu Pembayaran', 'Transfer Bank', '2025-11-17 20:53:05'),
(67, 2, 4, 5, 10000, 'Menunggu Pembayaran', 'Transfer Bank', '2025-11-17 20:53:05'),
(68, 2, 4, 5, 10000, 'Dikemas', 'Transfer Bank', '2025-11-18 20:05:12'),
(69, 2, 4, 1, 350000, 'Selesai', 'Transfer Bank', '2025-11-19 20:25:39'),
(70, 2, 4, 1, 350000, 'Selesai', 'COD', '2025-11-19 20:43:37'),
(71, 2, 4, 1, 25000, 'Menunggu Pembayaran', 'Transfer Bank', '2025-11-23 05:03:13'),
(72, 2, 4, 1, 25000, 'Dikirim', 'COD', '2025-11-23 20:00:25'),
(73, 2, 4, 1, 25000, 'Dibatalkan', 'Transfer Bank', '2025-12-04 04:20:43'),
(74, 2, 4, 1, 25000, 'Dibatalkan', 'Transfer Bank', '2025-12-04 04:26:09'),
(75, 2, 4, 1, 25000, 'Dikemas', 'Transfer Bank', '2025-12-04 04:27:26'),
(76, 2, 4, 1, 25000, 'Dibatalkan', 'Transfer Bank', '2025-12-04 04:34:36'),
(77, 2, 4, 1, 350000, 'Dibatalkan', 'Transfer Bank', '2025-12-04 04:41:06');

-- --------------------------------------------------------

--
-- Table structure for table `produk`
--

CREATE TABLE `produk` (
  `id` int NOT NULL,
  `petani_id` int NOT NULL,
  `kategori_id` int DEFAULT NULL,
  `status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `nama_produk` varchar(255) NOT NULL,
  `keterangan` text,
  `stok_kg` int NOT NULL DEFAULT '0',
  `harga_kg` int NOT NULL DEFAULT '0',
  `foto` varchar(255) NOT NULL DEFAULT 'default.jpg'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `produk`
--

INSERT INTO `produk` (`id`, `petani_id`, `kategori_id`, `status`, `nama_produk`, `keterangan`, `stok_kg`, `harga_kg`, `foto`) VALUES
(4, 1, NULL, 'nonaktif', 'Sayur kangkug', 'aaaaaaaaaaaaaaa', 0, 350000, 'assets/img/produk/produk_691493f20c3994.06185443.jpeg'),
(6, 1, NULL, 'nonaktif', 'Sayur kangkug', 'qqqq', 91, 350000, 'assets/img/produk/produk_69154bc075e873.10109685.jpg'),
(8, 1, NULL, 'nonaktif', 'Sayur kangkug', 'qqqq', 0, 350000, 'assets/img/produk/produk_69154b99074c88.46326650.jpg'),
(12, 1, NULL, 'nonaktif', 'Buncis', 'berkualitas', 0, 100000, 'assets/img/produk/produk_6916239557c248.79064875.jpeg'),
(13, 1, NULL, 'nonaktif', 'bawang merah', 'berkualitas', 0, 100000, 'assets/img/produk/produk_691623ad1142d0.40525251.jpg'),
(14, 1, NULL, 'nonaktif', 'bawang campuran', 'berkualitas', 0, 100000, 'assets/img/produk/default.png'),
(15, 1, NULL, 'nonaktif', 'bawang campuran', 'berkualitas', 0, 100000, 'assets/img/produk/produk_691623c80acd62.93293761.jpeg'),
(16, 1, 3, 'aktif', 'Cabai', 'rica berkuahsbebjvbds', 9, 25000, 'assets/img/produk/produk_69171fa4404d83.48909501.jpg'),
(17, 5, NULL, 'aktif', 'rica', 'aaaa', 0, 25000, 'assets/img/produk/produk_6917276aecd931.46297572.jpg'),
(18, 1, NULL, 'nonaktif', 'bawang campuran', 'berkualitas', 0, 100000, 'assets/img/produk/produk_69186f76838649.51230095.jpeg'),
(19, 1, NULL, 'nonaktif', 'bawang campuran', 'berkualitas', 0, 100000, 'assets/img/produk/default.png'),
(20, 1, 1, 'nonaktif', 'Sayur kangkug', 'aaaaa', 0, 350000, 'assets/img/produk/produk_69189b64aa0179.84050133.jpeg'),
(21, 1, 4, 'aktif', 'Ubi Putih', 'Ubi lokal Khas Gorontalo', 225, 350000, 'assets/img/produk/produk_691e2c402439f0.88631020.jpg'),
(22, 1, 1, 'aktif', 'Kacang Panjang', 'Kacang Panjang yang rasanya sangat enak hasil dari imporan china', 5, 350000, 'assets/img/produk/produk_69198454a05762.44924411.jpeg'),
(23, 5, 2, 'aktif', 'Pisang Raja', 'Lezat besar dan bergizi', 6, 10000, 'assets/img/produk/produk_691aeaf3d46c59.90808646.jpg'),
(24, 13, 2, 'nonaktif', 'Pepaya', 'Pepaya Kalifornia , ukuran besar dan mmemiliki rasa yang enak', 3, 20000, 'assets/img/produk/produk_691fd67b961e00.15670806.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `transaksi_keuangan`
--

CREATE TABLE `transaksi_keuangan` (
  `id` int NOT NULL,
  `petani_id` int NOT NULL,
  `tipe_transaksi` varchar(50) NOT NULL,
  `jumlah` decimal(12,2) NOT NULL,
  `deskripsi` text,
  `referensi_id` int DEFAULT NULL,
  `tanggal_transaksi` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `transaksi_keuangan`
--

INSERT INTO `transaksi_keuangan` (`id`, `petani_id`, `tipe_transaksi`, `jumlah`, `deskripsi`, `referensi_id`, `tanggal_transaksi`) VALUES
(9, 1, 'Pemasukan', 350000.00, 'Pemasukan dari penjualan aaa (Pesanan ID: #40)', 40, '2025-11-16'),
(10, 1, 'Pemasukan', 350000.00, 'Pemasukan dari penjualan Sayur kangkug (Pesanan ID: #46)', 46, '2025-11-16'),
(11, 1, 'Pemasukan', 100000.00, 'Pemasukan dari penjualan Buncis (Pesanan ID: #48)', 48, '2025-11-16'),
(14, 1, 'Pemasukan', 25000.00, 'Pemasukan dari penjualan rica (Pesanan ID: #45)', 45, '2025-11-17'),
(15, 1, 'Pemasukan', 1050000.00, 'Pemasukan dari penjualan Sayur kangkug (Pesanan ID: #35)', 35, '2025-11-17'),
(16, 5, 'Pengeluaran', 10000.00, 'Biaya modal untuk tanam pisang di lahan Pisang (ID Lahan: 20)', 20, '2025-11-19'),
(17, 1, 'Pemasukan', 25000.00, 'Pemasukan dari penjualan rica (Pesanan ID: #60)', 60, '2025-11-18'),
(18, 1, 'Pemasukan', 350000.00, 'Pemasukan dari penjualan kangkunnng (Pesanan ID: #64)', 64, '2025-11-19'),
(19, 1, 'Pemasukan', 350000.00, 'Pemasukan dari penjualan kangkunnng (Pesanan ID: #69)', 69, '2025-11-20'),
(20, 1, 'Pemasukan', 350000.00, 'Pemasukan dari penjualan Sayur kangkug (Pesanan ID: #70)', 70, '2025-11-21'),
(21, 1, 'Pemasukan', 350000.00, 'Pemasukan dari penjualan Sayur kangkug (Pesanan ID: #57)', 57, '2025-11-21'),
(22, 1, 'Pemasukan', 350000.00, 'Pemasukan dari penjualan Sayur kangkug (Pesanan ID: #55)', 55, '2025-11-21'),
(24, 1, 'Pengeluaran', 3000000.00, 'Biaya modal untuk tanam Pisang di Lahan Pisang (ID Lahan: 22)', 22, '2025-11-13'),
(25, 1, 'Pengeluaran', 22000000.00, 'Biaya modal untuk tanam Cabai di lahan cabe (ID Lahan: 23)', 23, '2025-10-26'),
(26, 1, 'Pengeluaran', 22000000.00, 'Biaya modal untuk tanam pisang di Lahan Pisang (ID Lahan: 24)', 24, '2025-10-26'),
(27, 1, 'Pengeluaran', 2000000.00, 'Biaya modal untuk tanam cabai di lahan cabe (ID Lahan: 25)', 25, '2025-07-16');

-- --------------------------------------------------------

--
-- Table structure for table `ulasan`
--

CREATE TABLE `ulasan` (
  `id` int NOT NULL,
  `produk_id` int DEFAULT NULL,
  `pembeli_id` int NOT NULL,
  `detail_pesanan_id` int NOT NULL COMMENT 'Memastikan hanya pembeli item itu yang bisa review',
  `rating` tinyint(1) NOT NULL COMMENT 'Nilai 1-5',
  `komentar` text,
  `tgl_ulasan` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `ulasan`
--

INSERT INTO `ulasan` (`id`, `produk_id`, `pembeli_id`, `detail_pesanan_id`, `rating`, `komentar`, `tgl_ulasan`) VALUES
(1, 12, 2, 52, 5, 'buncis ini bagus', '2025-11-16 08:13:10'),
(2, 8, 2, 50, 3, 'sayur kangkung enak', '2025-11-16 08:14:15'),
(3, 22, 2, 70, 5, 'baguss', '2025-11-19 20:36:43');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `nama` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_token_expires_at` datetime DEFAULT NULL,
  `role` enum('petani','pembeli') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nama`, `email`, `password`, `reset_token`, `reset_token_expires_at`, `role`) VALUES
(1, 'Ilham TS BUllah', 'adik@gmail.com', 'ilham', NULL, NULL, 'petani'),
(2, 'ilham Bullah', 'kaka@gmail.com', 'ilham', NULL, NULL, 'pembeli'),
(3, 'ilham', 'kakaa@gmail.com', 'ilham', NULL, NULL, 'pembeli'),
(4, 'ayah', 'ayah@gmail.com', 'ayah', NULL, NULL, 'pembeli'),
(5, 'sepupu', 'sepupu@gmail.com', 'sepupu', NULL, NULL, 'petani'),
(6, 'ilham', 'ilham@gmailcom', 'ilham', NULL, NULL, 'petani'),
(7, 'sayang', 'sayang@gmail.com', 'yycell15', NULL, NULL, 'pembeli'),
(8, 'sayang', 'sayangg@gmail.com', 'ayang', NULL, NULL, 'pembeli'),
(9, 'Rangga Ibrahim', 'petani1@gmail.com', 'petani', NULL, NULL, 'petani'),
(10, 'Ayu Puspita Jelita', 'petani2@gmail.com', 'petani', NULL, NULL, 'petani'),
(11, 'Broklin Vert Zanuart ', 'petani3@gamil.com', 'petani', NULL, NULL, 'petani'),
(12, 'Hamdani Akbarr', 'petani4@gmail.com', 'ilham', NULL, NULL, 'petani'),
(13, 'Hamdani Akbarr', 'Hamdani@gmaill.com', 'hamdani', NULL, NULL, 'petani');

-- --------------------------------------------------------

--
-- Table structure for table `user_profiles`
--

CREATE TABLE `user_profiles` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `telepon` varchar(20) DEFAULT NULL,
  `bio` text,
  `foto_profil` varchar(255) DEFAULT 'assets/img/profil/default.png',
  `Jenis_Kelamin` enum('Laki-laki','Perempuan') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `user_profiles`
--

INSERT INTO `user_profiles` (`id`, `user_id`, `telepon`, `bio`, `foto_profil`, `Jenis_Kelamin`) VALUES
(3, 1, '0823491', 'saya seorang petani hebattt', 'assets/img/profil/profil_691429df98b275.45767396.jpg', 'Laki-laki'),
(45, 3, '082349166049', 'Pembeliii', 'assets/img/profil/profil_6914dd1fdc63f8.94072295.jpg', 'Laki-laki'),
(50, 2, '082349166049', 'ilham', 'assets/img/profil/profil_691a867b65fbc3.34882319.jpg', 'Laki-laki');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `alamat`
--
ALTER TABLE `alamat`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pembeli_id` (`pembeli_id`);

--
-- Indexes for table `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pesanan_id` (`pesanan_id`),
  ADD KEY `produk_id` (`produk_id`);

--
-- Indexes for table `kategori`
--
ALTER TABLE `kategori`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `keranjang`
--
ALTER TABLE `keranjang`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pembeli_produk` (`pembeli_id`,`produk_id`),
  ADD KEY `pembeli_id` (`pembeli_id`),
  ADD KEY `produk_id` (`produk_id`);

--
-- Indexes for table `lahan`
--
ALTER TABLE `lahan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `petani_id` (`petani_id`);

--
-- Indexes for table `manajemen_lahan`
--
ALTER TABLE `manajemen_lahan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pesanan_id_unik` (`pesanan_id`);

--
-- Indexes for table `pesanan`
--
ALTER TABLE `pesanan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pembeli_id` (`pembeli_id`),
  ADD KEY `alamat_id` (`alamat_id`);

--
-- Indexes for table `produk`
--
ALTER TABLE `produk`
  ADD PRIMARY KEY (`id`),
  ADD KEY `petani_id` (`petani_id`),
  ADD KEY `idx_kategori` (`kategori_id`);

--
-- Indexes for table `transaksi_keuangan`
--
ALTER TABLE `transaksi_keuangan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `petani_id` (`petani_id`);

--
-- Indexes for table `ulasan`
--
ALTER TABLE `ulasan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `detail_pesanan_unik` (`detail_pesanan_id`),
  ADD KEY `produk_id` (`produk_id`),
  ADD KEY `pembeli_id` (`pembeli_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `alamat`
--
ALTER TABLE `alamat`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT for table `kategori`
--
ALTER TABLE `kategori`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `keranjang`
--
ALTER TABLE `keranjang`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT for table `lahan`
--
ALTER TABLE `lahan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `manajemen_lahan`
--
ALTER TABLE `manajemen_lahan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `pembayaran`
--
ALTER TABLE `pembayaran`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `pesanan`
--
ALTER TABLE `pesanan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;

--
-- AUTO_INCREMENT for table `produk`
--
ALTER TABLE `produk`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `transaksi_keuangan`
--
ALTER TABLE `transaksi_keuangan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `ulasan`
--
ALTER TABLE `ulasan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `user_profiles`
--
ALTER TABLE `user_profiles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `alamat`
--
ALTER TABLE `alamat`
  ADD CONSTRAINT `alamat_ibfk_1` FOREIGN KEY (`pembeli_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  ADD CONSTRAINT `detail_pesanan_ibfk_1` FOREIGN KEY (`pesanan_id`) REFERENCES `pesanan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `detail_pesanan_ibfk_2` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `keranjang`
--
ALTER TABLE `keranjang`
  ADD CONSTRAINT `keranjang_ibfk_1` FOREIGN KEY (`pembeli_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `keranjang_ibfk_2` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `lahan`
--
ALTER TABLE `lahan`
  ADD CONSTRAINT `lahan_ibfk_1` FOREIGN KEY (`petani_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD CONSTRAINT `pembayaran_ibfk_1` FOREIGN KEY (`pesanan_id`) REFERENCES `pesanan` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pesanan`
--
ALTER TABLE `pesanan`
  ADD CONSTRAINT `pesanan_ibfk_1` FOREIGN KEY (`pembeli_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pesanan_ibfk_2` FOREIGN KEY (`alamat_id`) REFERENCES `alamat` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `produk`
--
ALTER TABLE `produk`
  ADD CONSTRAINT `produk_ibfk_1` FOREIGN KEY (`petani_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `produk_ibfk_2` FOREIGN KEY (`kategori_id`) REFERENCES `kategori` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `transaksi_keuangan`
--
ALTER TABLE `transaksi_keuangan`
  ADD CONSTRAINT `transaksi_keuangan_ibfk_1` FOREIGN KEY (`petani_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ulasan`
--
ALTER TABLE `ulasan`
  ADD CONSTRAINT `ulasan_ibfk_1` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `ulasan_ibfk_2` FOREIGN KEY (`pembeli_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ulasan_ibfk_3` FOREIGN KEY (`detail_pesanan_id`) REFERENCES `detail_pesanan` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `profile_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
