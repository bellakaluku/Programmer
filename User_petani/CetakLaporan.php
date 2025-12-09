<?php
session_start();
require_once __DIR__ . '/../public/pages/Connection.php'; 

// --- PENTING: Memanggil Library FPDF ---
// Pastikan file fpdf.php ada di folder yang sama dengan file ini
require('fpdf.php'); 
// ---------------------------------------

// Cek Login
if (!isset($_SESSION['user_id'])) { die("Akses Ditolak. Silakan Login."); }
$petani_id = (int) $_SESSION['user_id'];

// Ambil Parameter Filter dari URL
$type = $_GET['type'] ?? 'pemasukan';
$year = $_GET['year'] ?? date('Y'); // Default tahun ini jika tidak dipilih

class PDF extends FPDF
{
    // 1. HEADER HALAMAN (KOP SURAT)
    function Header()
    {
        // Logo (Opsional: Jika punya logo.png, uncomment baris bawah ini)
        // $this->Image('logo.png',10,6,30);
        
        // Nama Usaha (Bold, Ukuran 16)
        $this->SetFont('Arial','B',16);
        $this->Cell(0,10,'TANI MAJU INDONESIA',0,1,'C');
        
        // Alamat (Regular, Ukuran 10)
        $this->SetFont('Arial','',10);
        $this->Cell(0,5,'Jl. Pertanian No. 123, Desa Makmur, Gorontalo',0,1,'C');
        $this->Cell(0,5,'Email: admin@tanimaju.com | Telp: 0812-3456-7890',0,1,'C');
        
        // Garis Tebal Pembatas Header
        $this->SetLineWidth(0.5);
        $this->Line(10, 32, 200, 32);
        $this->SetLineWidth(0.2); // Kembalikan ke normal
        $this->Ln(10); // Spasi ke bawah
    }

    // 2. FOOTER HALAMAN
    function Footer()
    {
        $this->SetY(-15); // 1.5cm dari bawah
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,'Dicetak pada: ' . date('d-m-Y H:i') . ' | Halaman '.$this->PageNo().'/{nb}',0,0,'C');
    }
}

// Inisialisasi PDF
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage(); // Halaman Portrait A4

// --- LOGIKA DATA BERDASARKAN TIPE LAPORAN ---

if ($type == 'pemasukan') {
    // === LAPORAN PEMASUKAN ===
    
    // Judul Laporan
    $pdf->SetFont('Arial','B',14);
    $pdf->Cell(0,10,'LAPORAN PEMASUKAN TAHUN ' . $year, 0, 1, 'C');
    $pdf->Ln(5);

    // Header Tabel
    $pdf->SetFont('Arial','B',9);
    $pdf->SetFillColor(230,240,255); // Warna biru muda
    
    // Lebar Kolom Total: 190mm
    $pdf->Cell(10,10,'No',1,0,'C',true);
    $pdf->Cell(25,10,'Tanggal',1,0,'C',true);
    $pdf->Cell(55,10,'Nama Pembeli',1,0,'C',true); // Diperlebar
    $pdf->Cell(60,10,'Alamat Pengiriman',1,0,'C',true); // Diperlebar
    $pdf->Cell(40,10,'Total (Rp)',1,1,'C',true);

    // Isi Data
    $pdf->SetFont('Arial','',9);
    
    // Query Data
    $sql = "SELECT p.tanggal_pesan, p.total_harga, a.nama_penerima, a.kota, a.alamat_lengkap
            FROM pesanan p
            LEFT JOIN alamat a ON p.alamat_id = a.id
            WHERE p.petani_id = ? 
            AND (p.status_pesanan = 'Dikirim' OR p.status_pesanan = 'Selesai')
            AND YEAR(p.tanggal_pesan) = ? 
            ORDER BY p.tanggal_pesan DESC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $petani_id, $year);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $no = 1;
    $total_semua = 0;

    if ($res->num_rows > 0) {
        while($row = $res->fetch_assoc()){
            $pdf->Cell(10,8,$no++,1,0,'C');
            $pdf->Cell(25,8,date('d/m/Y', strtotime($row['tanggal_pesan'])),1,0,'C');
            
            // Potong teks jika terlalu panjang agar tidak merusak tabel
            $nama = substr($row['nama_penerima'], 0, 25);
            $alamat = substr($row['alamat_lengkap'], 0, 30) . '...';

            $pdf->Cell(55,8,$nama,1,0,'L');
            $pdf->Cell(60,8,$alamat,1,0,'L');
            $pdf->Cell(40,8,number_format($row['total_harga'],0,',','.'),1,1,'R');
            
            $total_semua += $row['total_harga'];
        }
    } else {
        $pdf->Cell(190,10,'Tidak ada data transaksi pada tahun ' . $year, 1, 1, 'C');
    }
    
    // Baris Total
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(150,10,'TOTAL PEMASUKAN TAHUN '.$year,1,0,'C');
    $pdf->Cell(40,10,'Rp '.number_format($total_semua,0,',','.'),1,1,'R');

} else {
    // === LAPORAN PENGELUARAN ===
    
    // Judul Laporan
    $pdf->SetFont('Arial','B',14);
    $pdf->Cell(0,10,'LAPORAN PENGELUARAN (MODAL) TAHUN ' . $year, 0, 1, 'C');
    $pdf->Ln(5);

    // Header Tabel
    $pdf->SetFont('Arial','B',9);
    $pdf->SetFillColor(255,230,230); // Warna merah muda
    
    $pdf->Cell(10,10,'No',1,0,'C',true);
    $pdf->Cell(30,10,'Tgl Tanam',1,0,'C',true);
    $pdf->Cell(50,10,'Nama Lahan',1,0,'C',true);
    $pdf->Cell(30,10,'Tanaman',1,0,'C',true);
    $pdf->Cell(30,10,'Est. Panen',1,0,'C',true);
    $pdf->Cell(40,10,'Biaya (Rp)',1,1,'C',true);

    $pdf->SetFont('Arial','',9);

    $sql = "SELECT * FROM lahan 
            WHERE petani_id = ? 
            AND biaya_modal > 0 
            AND YEAR(mulai_tanam) = ? 
            ORDER BY mulai_tanam DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $petani_id, $year);
    $stmt->execute();
    $res = $stmt->get_result();

    $no = 1;
    $total_semua = 0;

    if ($res->num_rows > 0) {
        while($row = $res->fetch_assoc()){
            $pdf->Cell(10,8,$no++,1,0,'C');
            $pdf->Cell(30,8,date('d/m/Y', strtotime($row['mulai_tanam'])),1,0,'C');
            $pdf->Cell(50,8,substr($row['nama_lahan'], 0, 25),1,0,'L');
            $pdf->Cell(30,8,$row['jenis_tanaman'],1,0,'C');
            
            $tgl_panen = ($row['tanggal_panen']) ? date('d/m/Y', strtotime($row['tanggal_panen'])) : '-';
            $pdf->Cell(30,8,$tgl_panen,1,0,'C');
            
            $pdf->Cell(40,8,number_format($row['biaya_modal'],0,',','.'),1,1,'R');
            $total_semua += $row['biaya_modal'];
        }
    } else {
        $pdf->Cell(190,10,'Tidak ada data pengeluaran modal pada tahun ' . $year, 1, 1, 'C');
    }

    // Baris Total
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(150,10,'TOTAL PENGELUARAN TAHUN '.$year,1,0,'C');
    $pdf->Cell(40,10,'Rp '.number_format($total_semua,0,',','.'),1,1,'R');
}

// --- 3. BAGIAN TANDA TANGAN (FORMAT RESMI) ---
$pdf->Ln(15); // Spasi ke bawah

$pdf->SetFont('Arial','',10);

// Atur posisi X agar tanda tangan ada di kanan (sekitar 130mm dari kiri)
$startX = 130;

// Lokasi dan Tanggal
$pdf->SetX($startX); 
$pdf->Cell(60, 5, 'Gorontalo, ' . date('d F Y'), 0, 1, 'C');

// Jabatan
$pdf->SetX($startX);
$pdf->Cell(60, 5, 'Mengetahui, Pemilik', 0, 1, 'C');

// Ruang untuk tanda tangan (spasi kosong)
$pdf->Ln(25); 

// Nama Pemilik (Garis Bawah)
$pdf->SetFont('Arial','BU',10); // Bold + Underline
$pdf->SetX($startX);
// Anda bisa mengambil nama petani dari database session jika mau, di sini manual dulu
$pdf->Cell(60, 5, '( ....................................... )', 0, 1, 'C');


// Output PDF ke Browser
$pdf->Output();
?>