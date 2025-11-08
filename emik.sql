-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 22 Bulan Mei 2025 pada 11.31
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `emik`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `data_nilai`
--

CREATE TABLE `data_nilai` (
  `id` int(11) NOT NULL,
  `mahasiswa_id` int(11) NOT NULL,
  `jadwal_id` int(11) NOT NULL,
  `nilai_tugas` decimal(5,2) DEFAULT 0.00,
  `nilai_uts` decimal(5,2) DEFAULT 0.00,
  `nilai_uas` decimal(5,2) DEFAULT 0.00,
  `nilai_akhir` decimal(5,2) GENERATED ALWAYS AS (`nilai_tugas` * 0.3 + `nilai_uts` * 0.3 + `nilai_uas` * 0.4) STORED,
  `grade` char(2) GENERATED ALWAYS AS (case when `nilai_akhir` >= 85 then 'A' when `nilai_akhir` >= 80 then 'A-' when `nilai_akhir` >= 75 then 'B+' when `nilai_akhir` >= 70 then 'B' when `nilai_akhir` >= 65 then 'B-' when `nilai_akhir` >= 60 then 'C+' when `nilai_akhir` >= 55 then 'C' when `nilai_akhir` >= 40 then 'D' else 'E' end) STORED,
  `created_by` int(11) DEFAULT NULL COMMENT 'User yang menginput nilai',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `data_nilai`
--

INSERT INTO `data_nilai` (`id`, `mahasiswa_id`, `jadwal_id`, `nilai_tugas`, `nilai_uts`, `nilai_uas`, `created_by`, `created_at`, `updated_at`) VALUES
(18, 1, 16, 99.00, 99.00, 99.00, NULL, '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
(19, 1, 20, 99.00, 99.00, 99.00, 2, '2025-05-22 05:00:42', '2025-05-22 05:00:42'),
(20, 1, 17, 99.00, 99.00, 99.00, 2, '2025-05-22 05:00:53', '2025-05-22 05:00:53'),
(21, 1, 14, 99.00, 99.00, 99.00, 2, '2025-05-22 05:01:29', '2025-05-22 05:01:29'),
(22, 1, 15, 99.00, 99.00, 99.00, 2, '2025-05-22 05:01:44', '2025-05-22 05:01:44'),
(23, 1, 19, 99.00, 99.00, 99.00, 2, '2025-05-22 05:02:10', '2025-05-22 05:02:10'),
(24, 1, 21, 99.00, 99.00, 99.00, 2, '2025-05-22 05:02:27', '2025-05-22 05:02:27'),
(25, 1, 22, 99.00, 99.00, 99.00, 2, '2025-05-22 05:02:40', '2025-05-22 05:02:40');

-- --------------------------------------------------------

--
-- Struktur dari tabel `dosen`
--

CREATE TABLE `dosen` (
  `nip` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `bidang_keahlian` varchar(100) DEFAULT NULL,
  `pangkat` varchar(50) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `nohp` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `prodi` varchar(10) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `dosen`
--

INSERT INTO `dosen` (`nip`, `nama`, `bidang_keahlian`, `pangkat`, `alamat`, `nohp`, `email`, `prodi`, `user_id`) VALUES
('1', 'dedi', 'computing', 'dosen tetap', 'marelan', '081234567891', 'afif@gmail.com', 'si', NULL),
('2', 'raissa', 'komputer', 'dosen tetap', 'johor', '08766767677635', 'raissa@gmail.com', 'si', NULL),
('3', 'adnan buyung', 'segalanya', 'dosen tetap', 'gatsu', '8776764262634', 'ABABB@gmail.com', 'si', NULL),
('4', 'gibran', 'emas antham', 'dosen tetap', 'gatsu', '8776764262634', 'ABABB@gmail.com', 'ilkom', NULL),
('5', 'afif', 'ngasih tugas', 'dosen tetap', 'gatsu', '8776764262634', 'ABABB@gmail.com', 'si', 13),
('6', 'fatiah', 'aset informasi', 'dosen tetap', 'gatsu', '8776764262634', 'ABABB@gmail.com', 'si', NULL),
('7', 'dosen chil', 'dongeng', 'dosen tetap', 'gatsu', '8776764262634', 'ABABB@gmail.com', 'si', NULL),
('8', 'dosen mm', 'matematika', 'dosen tetap', 'gatsu', '8776764262634', 'ABABB@gmail.com', 'mm', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `jadwal_kuliah`
--

CREATE TABLE `jadwal_kuliah` (
  `id` int(11) NOT NULL,
  `hari` enum('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu') NOT NULL,
  `waktu_mulai` time NOT NULL,
  `waktu_selesai` time NOT NULL,
  `kode_matkul` varchar(20) NOT NULL,
  `ruangan` varchar(20) DEFAULT NULL,
  `dosen_nip` varchar(20) NOT NULL,
  `semester` enum('Ganjil','Genap') NOT NULL,
  `tahun_ajaran` varchar(9) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `jadwal_kuliah`
--

INSERT INTO `jadwal_kuliah` (`id`, `hari`, `waktu_mulai`, `waktu_selesai`, `kode_matkul`, `ruangan`, `dosen_nip`, `semester`, `tahun_ajaran`) VALUES
(14, 'Senin', '10:30:00', '12:45:00', 'pbo', 'COMP-F', '5', 'Genap', '2024/2025'),
(15, 'Senin', '13:30:00', '15:45:00', 'pwd', 'COMP-D', '5', 'Genap', '2024/2025'),
(16, 'Selasa', '13:30:00', '15:45:00', 'apsi', 'COMP-F', '1', 'Genap', '2024/2025'),
(17, 'Rabu', '09:00:00', '10:30:00', 'KAI', 'FST-110', '6', 'Genap', '2024/2025'),
(19, 'Rabu', '13:40:00', '15:00:00', 'stl', 'ADT-FST', '7', 'Genap', '2024/2025'),
(20, 'Kamis', '12:00:00', '13:00:00', 'erp', 'FST-305', '3', 'Genap', '2024/2025'),
(21, 'Kamis', '13:30:00', '15:00:00', 'MM', 'FST-304', '8', 'Genap', '2024/2025'),
(22, 'Jumat', '14:30:00', '16:00:00', 'KWU', 'FST-303', '4', 'Genap', '2024/2025'),
(26, 'Rabu', '12:00:00', '13:00:00', 'tkti', 'FST-303', '2', 'Genap', '2024/2025');

-- --------------------------------------------------------

--
-- Struktur dari tabel `krs`
--

CREATE TABLE `krs` (
  `id` int(11) NOT NULL,
  `mahasiswa_id` int(11) NOT NULL,
  `jadwal_id` int(11) NOT NULL,
  `tahun_ajaran` varchar(9) NOT NULL,
  `semester` enum('Ganjil','Genap') NOT NULL,
  `status` enum('aktif','batal') DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `krs`
--

INSERT INTO `krs` (`id`, `mahasiswa_id`, `jadwal_id`, `tahun_ajaran`, `semester`, `status`, `created_at`) VALUES
(14, 1, 14, '2024/2025', 'Genap', 'aktif', '2025-05-20 02:46:59'),
(15, 1, 15, '2024/2025', 'Genap', 'aktif', '2025-05-20 02:47:18'),
(16, 1, 16, '2024/2025', 'Genap', 'aktif', '2025-05-20 02:47:24'),
(17, 1, 17, '2024/2025', 'Genap', 'aktif', '2025-05-20 02:47:38'),
(19, 1, 19, '2024/2025', 'Genap', 'aktif', '2025-05-20 02:48:46'),
(20, 1, 20, '2024/2025', 'Genap', 'aktif', '2025-05-20 02:48:52'),
(21, 1, 21, '2024/2025', 'Genap', 'aktif', '2025-05-20 02:48:56'),
(22, 1, 22, '2024/2025', 'Genap', 'aktif', '2025-05-20 02:49:01');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mahasiswa`
--

CREATE TABLE `mahasiswa` (
  `id` int(11) NOT NULL,
  `nim` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `prodi` varchar(10) NOT NULL,
  `alamat` text DEFAULT NULL,
  `nohp` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `mahasiswa`
--

INSERT INTO `mahasiswa` (`id`, `nim`, `nama`, `prodi`, `alamat`, `nohp`, `email`, `user_id`) VALUES
(1, '0702231046', 'Dzakwan Abbas', 'si', 'Marendal jalan sumber amal', '081932025028', 'dzakwanabbas018@gmail.com', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `mata_kuliah`
--

CREATE TABLE `mata_kuliah` (
  `kode_matkul` varchar(20) NOT NULL,
  `nama_matkul` varchar(100) NOT NULL,
  `sks` tinyint(4) NOT NULL,
  `semester` tinyint(4) NOT NULL,
  `prodi` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `mata_kuliah`
--

INSERT INTO `mata_kuliah` (`kode_matkul`, `nama_matkul`, `sks`, `semester`, `prodi`) VALUES
('apsi', 'analisis proses sistem informasi', 3, 4, 'si'),
('erp', 'enterprise resource planning', 2, 4, 'si'),
('KAI', 'Keamanan Sistem Informasi', 2, 4, 'si'),
('KWU', 'Technopreneurship', 1, 1, 'bio'),
('MM', 'statistika', 2, 4, 'bio'),
('pbo', 'pemrograman berbasis objek', 3, 4, 'si'),
('pwd', 'pemrograman web dasar', 3, 4, 'si'),
('stl', 'saints dan teknologi lingkungan', 2, 4, 'bio'),
('tkti', 'tata kelola teknologi infomasi', 2, 4, 'si');

-- --------------------------------------------------------

--
-- Struktur dari tabel `program_studi`
--

CREATE TABLE `program_studi` (
  `kode_prodi` varchar(10) NOT NULL,
  `nama_prodi` varchar(100) NOT NULL,
  `fakultas` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `program_studi`
--

INSERT INTO `program_studi` (`kode_prodi`, `nama_prodi`, `fakultas`) VALUES
('bio', 'biologi', 'sainst & teknonogi'),
('fsk', 'fisika', 'sainst & teknonogi'),
('ilkom', 'ilmu komputer', 'sainst & teknonogi'),
('mm', 'matematika', 'sainst & teknonogi'),
('si', 'sistem informasi', 'sainst & teknonogi');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','dosen','mahasiswa') NOT NULL,
  `nama` varchar(100) NOT NULL,
  `prodi` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `nama`, `prodi`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin', 'admin', '', NULL, '2025-05-09 15:08:08', '2025-05-09 15:08:08'),
(2, 'mahasiswa', 'mahasiswa', 'mahasiswa', '', NULL, '2025-05-17 06:54:01', '2025-05-17 06:54:01'),
(13, 'dosen', 'dosen', 'dosen', '', NULL, '2025-05-22 08:55:32', '2025-05-22 08:55:32');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `data_nilai`
--
ALTER TABLE `data_nilai`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_nilai_mahasiswa` (`mahasiswa_id`,`jadwal_id`),
  ADD KEY `jadwal_id` (`jadwal_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indeks untuk tabel `dosen`
--
ALTER TABLE `dosen`
  ADD PRIMARY KEY (`nip`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `prodi` (`prodi`);

--
-- Indeks untuk tabel `jadwal_kuliah`
--
ALTER TABLE `jadwal_kuliah`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kode_matkul` (`kode_matkul`),
  ADD KEY `dosen_nip` (`dosen_nip`);

--
-- Indeks untuk tabel `krs`
--
ALTER TABLE `krs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_krs` (`mahasiswa_id`,`jadwal_id`,`tahun_ajaran`,`semester`),
  ADD KEY `jadwal_id` (`jadwal_id`);

--
-- Indeks untuk tabel `mahasiswa`
--
ALTER TABLE `mahasiswa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nim` (`nim`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `prodi` (`prodi`);

--
-- Indeks untuk tabel `mata_kuliah`
--
ALTER TABLE `mata_kuliah`
  ADD PRIMARY KEY (`kode_matkul`),
  ADD KEY `prodi` (`prodi`);

--
-- Indeks untuk tabel `program_studi`
--
ALTER TABLE `program_studi`
  ADD PRIMARY KEY (`kode_prodi`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `data_nilai`
--
ALTER TABLE `data_nilai`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT untuk tabel `jadwal_kuliah`
--
ALTER TABLE `jadwal_kuliah`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT untuk tabel `krs`
--
ALTER TABLE `krs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT untuk tabel `mahasiswa`
--
ALTER TABLE `mahasiswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `data_nilai`
--
ALTER TABLE `data_nilai`
  ADD CONSTRAINT `data_nilai_ibfk_1` FOREIGN KEY (`mahasiswa_id`) REFERENCES `mahasiswa` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `data_nilai_ibfk_2` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal_kuliah` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `data_nilai_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `dosen`
--
ALTER TABLE `dosen`
  ADD CONSTRAINT `dosen_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `dosen_ibfk_2` FOREIGN KEY (`prodi`) REFERENCES `program_studi` (`kode_prodi`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `jadwal_kuliah`
--
ALTER TABLE `jadwal_kuliah`
  ADD CONSTRAINT `jadwal_kuliah_ibfk_1` FOREIGN KEY (`kode_matkul`) REFERENCES `mata_kuliah` (`kode_matkul`) ON UPDATE CASCADE,
  ADD CONSTRAINT `jadwal_kuliah_ibfk_2` FOREIGN KEY (`dosen_nip`) REFERENCES `dosen` (`nip`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `krs`
--
ALTER TABLE `krs`
  ADD CONSTRAINT `krs_ibfk_1` FOREIGN KEY (`mahasiswa_id`) REFERENCES `mahasiswa` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `krs_ibfk_2` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal_kuliah` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mahasiswa`
--
ALTER TABLE `mahasiswa`
  ADD CONSTRAINT `mahasiswa_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `mahasiswa_ibfk_2` FOREIGN KEY (`prodi`) REFERENCES `program_studi` (`kode_prodi`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mata_kuliah`
--
ALTER TABLE `mata_kuliah`
  ADD CONSTRAINT `mata_kuliah_ibfk_1` FOREIGN KEY (`prodi`) REFERENCES `program_studi` (`kode_prodi`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
