-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 08, 2025 at 10:19 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

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
-- Table structure for table `data_nilai`
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
-- Dumping data for table `data_nilai`
--

INSERT INTO `data_nilai` (`id`, `mahasiswa_id`, `jadwal_id`, `nilai_tugas`, `nilai_uts`, `nilai_uas`, `created_by`, `created_at`, `updated_at`) VALUES
(18, 1, 16, 99.00, 99.00, 99.00, NULL, '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
(19, 1, 20, 99.00, 99.00, 99.00, 2, '2025-05-22 05:00:42', '2025-05-22 05:00:42'),
(23, 1, 19, 99.00, 99.00, 99.00, 2, '2025-05-22 05:02:10', '2025-05-22 05:02:10'),
(24, 1, 21, 99.00, 99.00, 99.00, 2, '2025-05-22 05:02:27', '2025-05-22 05:02:27'),
(25, 1, 22, 100.00, 100.00, 100.00, 2, '2025-05-22 05:02:40', '2025-05-23 01:05:09'),
(27, 1, 26, 100.00, 100.00, 100.00, 2, '2025-05-22 10:53:09', '2025-05-28 05:40:31'),
(28, 1, 28, 99.00, 99.00, 99.00, NULL, '2025-05-22 12:32:26', '2025-05-22 12:32:26'),
(29, 1, 29, 99.00, 99.00, 99.00, NULL, '2025-05-22 12:33:11', '2025-05-22 12:33:11'),
(39, 1, 17, 99.00, 99.00, 99.00, 27, '2025-05-25 12:23:53', '2025-05-25 12:23:53'),
(40, 124, 19, 0.00, 0.00, 0.00, 28, '2025-05-26 06:08:19', '2025-05-26 06:08:19');

-- --------------------------------------------------------

--
-- Table structure for table `dosen`
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
  `user_id` int(11) DEFAULT NULL,
  `foto_profil` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dosen`
--

INSERT INTO `dosen` (`nip`, `nama`, `bidang_keahlian`, `pangkat`, `alamat`, `nohp`, `email`, `prodi`, `user_id`, `foto_profil`, `created_at`, `updated_at`) VALUES
('1', 'dedi', 'computing', 'dosen tetap', 'marelan', '081234567891', 'dedi@gmail.com', 'si', 24, NULL, '2025-05-22 15:49:43', '2025-05-25 05:21:28'),
('10', 'D E X', 'roam amplas', 'dosen tetap', 'gatsu', '8776764262634', 'dex@gmail.com', 'si', 68, NULL, '2025-05-27 22:34:10', '2025-05-27 22:34:10'),
('2', 'raissa amanda, M.Kom', 'komputer', 'dosen tetap', 'johor', '08766767677635', 'raissa@gmail.com', 'si', 22, 'uploads/profil_dosen/dosen_2_1748437265.png', '2025-05-22 15:49:43', '2025-05-28 05:06:39'),
('3', 'adnan buyung', 'segalanya', 'dosen tetap', 'gatsu', '8776764262634', 'adnan@gmail.com', 'mm', 23, 'uploads/profil_dosen/dosen_3_1748486100.png', '2025-05-22 15:49:43', '2025-05-28 19:35:00'),
('4', 'gibran', 'emas antham', 'dosen tetap', 'gatsu', '8776764262634', 'gibran@gmail.com', 'ilkom', 26, NULL, '2025-05-22 15:49:43', '2025-05-25 05:21:28'),
('5', 'affifudin M.Kom', 'ngasih tugas', 'dosen tidak tetap', 'madiun', '8776764262634', 'affifudin@gmail.com', 'si', 20, 'uploads/profil_dosen/dosen_5_1748438568.png', '2025-05-22 15:47:26', '2025-10-27 07:04:44'),
('6', 'fathiya', 'aset informasi', 'dosen tetap', 'gatsu', '8776764262634', 'fathiya@gmail.com', 'si', 27, NULL, '2025-05-22 15:49:43', '2025-05-25 05:21:28'),
('7', 'dosen chil', 'dongeng', 'dosen tetap', 'gatsu', '8776764262634', 'dosenchil@gmail.com', 'si', 28, NULL, '2025-05-22 15:50:35', '2025-05-25 05:21:28'),
('8', 'dosen mm', 'matematika', 'dosen tetap', 'gatsu', '8776764262634', 'dosenmm@gmail.com', 'mm', 25, NULL, '2025-05-22 15:49:43', '2025-05-25 05:21:28');

-- --------------------------------------------------------

--
-- Table structure for table `jadwal_kuliah`
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
  `tahun_ajaran` varchar(9) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jadwal_kuliah`
--

INSERT INTO `jadwal_kuliah` (`id`, `hari`, `waktu_mulai`, `waktu_selesai`, `kode_matkul`, `ruangan`, `dosen_nip`, `semester`, `tahun_ajaran`, `created_at`, `updated_at`) VALUES
(16, 'Selasa', '13:30:00', '15:45:00', 'apsi', 'COMP-F', '1', 'Genap', '2024/2025', '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
(17, 'Rabu', '09:00:00', '10:30:00', 'KAI', 'FST-110', '6', 'Genap', '2024/2025', '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
(19, 'Rabu', '13:40:00', '15:00:00', 'stl', 'ADT-FST', '7', 'Genap', '2024/2025', '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
(20, 'Kamis', '12:00:00', '13:00:00', 'erp', 'FST-305', '3', 'Genap', '2024/2025', '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
(21, 'Kamis', '13:30:00', '15:00:00', 'MM', 'FST-304', '8', 'Genap', '2024/2025', '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
(22, 'Jumat', '14:30:00', '16:00:00', 'KWU', 'FST-303', '4', 'Genap', '2024/2025', '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
(26, 'Rabu', '12:00:00', '13:30:00', 'tkti', 'FST-303', '2', 'Genap', '2024/2025', '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
(28, 'Senin', '10:30:00', '12:45:00', 'pbo', 'COMP-F', '5', 'Genap', '2024/2025', '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
(29, 'Senin', '13:30:00', '15:45:00', 'pwd', 'COMP-D', '5', 'Genap', '2024/2025', '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
(30, 'Jumat', '12:00:00', '13:00:00', 'apsi', '09', '10', 'Genap', '2029/2030', '2025-10-19 23:58:51', '2025-10-19 23:58:51');

-- --------------------------------------------------------

--
-- Table structure for table `krs`
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
-- Dumping data for table `krs`
--

INSERT INTO `krs` (`id`, `mahasiswa_id`, `jadwal_id`, `tahun_ajaran`, `semester`, `status`, `created_at`) VALUES
(45, 1, 28, '2024/2025', 'Genap', 'aktif', '2025-05-30 02:03:14'),
(46, 1, 29, '2024/2025', 'Genap', 'aktif', '2025-05-30 02:03:20'),
(47, 1, 16, '2024/2025', 'Genap', 'aktif', '2025-05-30 02:03:26'),
(48, 1, 17, '2024/2025', 'Genap', 'aktif', '2025-05-30 02:03:34'),
(49, 1, 26, '2024/2025', 'Genap', 'aktif', '2025-05-30 02:03:41'),
(50, 1, 19, '2024/2025', 'Genap', 'aktif', '2025-05-30 02:04:32'),
(51, 1, 20, '2024/2025', 'Genap', 'aktif', '2025-05-30 02:04:38'),
(52, 1, 21, '2024/2025', 'Genap', 'aktif', '2025-05-30 02:04:46'),
(53, 1, 22, '2024/2025', 'Genap', 'aktif', '2025-05-30 02:04:51'),
(54, 1, 28, '2025/2026', 'Ganjil', 'aktif', '2025-10-19 23:58:51');

-- --------------------------------------------------------

--
-- Table structure for table `mahasiswa`
--

CREATE TABLE `mahasiswa` (
  `id` int(11) NOT NULL,
  `nim` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `prodi` varchar(10) NOT NULL,
  `alamat` text DEFAULT NULL,
  `nohp` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `foto_profil` varchar(255) DEFAULT NULL,
  `semester_aktif` int(11) DEFAULT 1 COMMENT 'Semester aktif mahasiswa',
  `golongan_ukt` enum('I','II','III','IV','V','VI','VII','VIII') DEFAULT 'I',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mahasiswa`
--

INSERT INTO `mahasiswa` (`id`, `nim`, `nama`, `prodi`, `alamat`, `nohp`, `email`, `user_id`, `foto_profil`, `semester_aktif`, `golongan_ukt`, `created_at`, `updated_at`) VALUES
(1, '0702231046', 'DZAKWAN ABBAS', 'si', 'Marendal jalan sumber amal', '081932025028', 'dzakwanabbas018@gmail.com', 55, 'uploads/profil_mahasiswa/mahasiswa_0702231046_1761549151.png', 4, 'III', '2025-05-22 19:14:28', '2025-10-27 07:05:56'),
(105, '0702232115', 'SEPIRA YUNDA', 'si', 'Jl. Merdeka No. 15, Jakarta', '081234567890', 'sepira.yunda@student.example.ac.id', 34, NULL, 1, 'I', '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(106, '0702232128', 'MUHAMMAD NOVAL REVALDI', 'si', 'Jl. Sudirman No. 28, Bandung', '081234567891', 'noval.revaldi@student.example.ac.id', 35, NULL, 1, 'II', '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(107, '0702232116', 'AMIRA SALSABILA', 'si', 'Jl. Gatot Subroto No. 16, Surabaya', '081234567892', 'amira.salsabila@student.example.ac.id', 36, NULL, 1, 'I', '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(108, '0702232129', 'MUHAMMAD FARUQI ADRI', 'si', 'Jl. Thamrin No. 29, Medan', '081234567893', 'faruqi.adri@student.example.ac.id', 37, NULL, 1, 'IV', '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(110, '0702232122', 'CINDY ANGGRIANI', 'si', 'Jl. Pahlawan No. 22, Yogyakarta', '081234567895', 'cindy.anggriani@student.example.ac.id', 39, NULL, 1, 'III', '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(111, '0702233161', 'MUCH NUR SYAMS SIMAJA', 'si', 'Jl. Diponegoro No. 61, Semarang', '081234567896', 'much.syams@student.example.ac.id', 40, NULL, 1, 'II', '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(112, '0702231040', 'INDAH DWI PANCARI', 'si', 'Jl. Ahmad Yani No. 40, Malang', '081234567897', 'indah.pancari@student.example.ac.id', 41, NULL, 1, 'V', '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(113, '0702232118', 'MHD. FARRAZ FATH', 'si', 'Jl. Sisingamangaraja No. 18, Bekasi', '081234567898', 'farraz.fath@student.example.ac.id', 42, NULL, 1, 'I', '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(114, '0702232117', 'NASRULLAH GUNAWAN', 'si', 'Jl. Hayam Wuruk No. 17, Depok', '081234567899', 'nasrullah.gunawan@student.example.ac.id', 43, NULL, 1, 'VI', '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(115, '0702231041', 'NOVILYA MUSFIRA BAHRI', 'si', 'Jl. Juanda No. 41, Tangerang', '081234567800', 'novilya.bahri@student.example.ac.id', 44, NULL, 1, 'III', '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(116, '0702232119', 'HAMZA DWI AULIA WARHANA', 'si', 'Jl. Teuku Umar No. 19, Bali', '081234567801', 'hamza.warhana@student.example.ac.id', 45, NULL, 1, 'II', '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(117, '0702231048', 'LAILA AZIZAH', 'si', 'Jl. Imam Bonjol No. 48, Makassar', '081234567802', 'laila.azizah@student.example.ac.id', 46, NULL, 1, 'VII', '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(118, '0702232113', 'WILONA RAMADHANI K', 'si', 'Jl. Cihampelas No. 13, Bandung', '081234567803', 'wilona.ramadhani@student.example.ac.id', 47, NULL, 1, 'IV', '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(119, '0702232126', 'SHERLY PITALOKA', 'si', 'Jl. Dago No. 26, Bandung', '081234567804', 'sherly.pitaloka@student.example.ac.id', 48, NULL, 1, 'I', '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(120, '0702233165', 'MAHARANI BR. SARAGIH', 'si', 'Jl. Siliwangi No. 65, Bogor', '081234567805', 'maharani.saragih@student.example.ac.id', 49, NULL, 1, 'V', '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(121, '0702231047', 'ARFINI TRI AGUSTINA', 'si', 'Jl. Cikapundung No. 47, Bandung', '081234567806', 'arfini.agustina@student.example.ac.id', 50, NULL, 1, 'II', '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(122, '0702232125', 'EKA JUANDA. H', 'si', 'Jl. Braga No. 25, Bandung', '081234567807', 'eka.juanda@student.example.ac.id', 51, NULL, 1, 'VIII', '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(124, '0702232114', 'MUHAMMAD ALVIN NURRAHMAN', 'si', 'Jl. Jawa No. 14, Surabaya', '081234567809', 'alvin.nurrahman@student.example.ac.id', 53, NULL, 1, 'III', '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(125, '0702232127', 'DEFT SANJAYA', 'si', 'Jl. Kalimantan No. 27, Balikpapan', '081234567810', 'deft.sanjaya@student.example.ac.id', 54, NULL, 1, 'IV', '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(126, '0702232124', 'PUTRI SYAYUZA', 'si', 'Jl. Papua No. 24, Jayapura', '081234567812', 'putri.syayuza@student.example.ac.id', 56, NULL, 1, 'II', '2025-05-22 19:14:28', '2025-05-22 19:56:00'),
(127, '0702233163', 'RIZAL AMRI KHOIRUL HAKIM RITONGA', 'si', 'Jl. Nusantara No. 63, Jakarta', '081234567813', 'rizal.ritonga@student.example.ac.id', 57, NULL, 1, 'VI', '2025-05-22 19:14:28', '2025-05-22 19:57:20'),
(129, '0702232120', 'MUTIA HERMAN', 'si', 'Jl. Mawar No. 20, Malang', '081234567815', 'mutia.herman@student.example.ac.id', 59, NULL, 1, 'I', '2025-05-22 19:14:28', '2025-05-22 19:57:20'),
(130, '0702233159', 'MHD. AYUB ARDI', 'si', 'Jl. Anggrek No. 59, Palembang', '081234567816', 'ayub.ardi@student.example.ac.id', 60, NULL, 1, 'V', '2025-05-22 19:14:28', '2025-05-22 19:57:20'),
(131, '0702231045', 'MEIRANDA SIREGAR', 'si', 'Jl. Melati No. 45, Medan', '081234567817', 'meiranda.siregar@student.example.ac.id', 61, NULL, 1, 'III', '2025-05-22 19:14:28', '2025-05-22 19:57:20'),
(132, '0702232123', 'MUHAMMAD MUSH\'AB UMAIR DAULAY', 'si', 'Jl. Flamboyan No. 23, Aceh', '081234567818', 'mushab.daulay@student.example.ac.id', 62, NULL, 1, 'VII', '2025-05-22 19:14:28', '2025-05-22 19:57:20'),
(134, '0702231043', 'MUHAMMAD RICHIE HADIANSAH', 'si', 'jl perdangangan gg manusia', '081234567820', 'richie.hadiansah@student.example.ac.id', 64, 'uploads/profil_mahasiswa/mahasiswa_0702231043_1748491940.jpg', 4, 'IV', '2025-05-22 19:14:28', '2025-05-28 19:37:55'),
(135, '0702232121', 'IDHAM TIOFANDY HASIBUAN', 'si', 'Jl. Dahlia No. 21, Medan', '081234567821', 'idham.hasibuan@student.example.ac.id', 65, NULL, 1, 'II', '2025-05-22 19:14:28', '2025-05-22 19:57:20'),
(136, '0702233160', 'NABIL AFIQ', 'si', 'Jl. Teratai No. 60, Bandung', '081234567822', 'nabil.afiq@student.example.ac.id', 66, NULL, 1, 'I', '2025-05-22 19:14:28', '2025-05-22 19:57:20');

-- --------------------------------------------------------

--
-- Table structure for table `mata_kuliah`
--

CREATE TABLE `mata_kuliah` (
  `kode_matkul` varchar(20) NOT NULL,
  `nama_matkul` varchar(100) NOT NULL,
  `sks` tinyint(4) NOT NULL,
  `semester` tinyint(4) NOT NULL,
  `prodi` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mata_kuliah`
--

INSERT INTO `mata_kuliah` (`kode_matkul`, `nama_matkul`, `sks`, `semester`, `prodi`, `created_at`, `updated_at`) VALUES
('apsi', 'analisis proses sistem informasi', 3, 4, 'si', '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
('erp', 'enterprise resource planning', 2, 4, 'si', '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
('KAI', 'Keamanan Sistem Informasi', 2, 4, 'si', '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
('KWU', 'Technopreneurship', 2, 1, 'si', '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
('MM', 'statistika', 2, 4, 'bio', '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
('pbo', 'pemrograman berbasis objek', 3, 4, 'si', '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
('pwd', 'pemrograman web dasar', 3, 4, 'si', '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
('stl', 'saints dan teknologi lingkungan', 2, 4, 'bio', '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
('tkti', 'tata kelola teknologi infomasi', 2, 4, 'si', '2025-05-20 03:14:10', '2025-05-20 03:14:10');

-- --------------------------------------------------------

--
-- Table structure for table `materi_perkuliahan`
--

CREATE TABLE `materi_perkuliahan` (
  `id` int(11) NOT NULL,
  `judul` varchar(255) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `kode_matkul` varchar(20) NOT NULL,
  `dosen_nip` varchar(20) NOT NULL,
  `pertemuan_ke` int(11) NOT NULL,
  `jenis_materi` enum('Slide','Dokumen','Video','Lainnya') NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `semester` enum('Ganjil','Genap') NOT NULL,
  `tahun_ajaran` varchar(9) NOT NULL,
  `tanggal_upload` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `materi_perkuliahan`
--

INSERT INTO `materi_perkuliahan` (`id`, `judul`, `deskripsi`, `kode_matkul`, `dosen_nip`, `pertemuan_ke`, `jenis_materi`, `file_path`, `file_name`, `file_size`, `semester`, `tahun_ajaran`, `tanggal_upload`) VALUES
(1, 'buat portalsia', '', 'pwd', '5', 14, 'Lainnya', '../../../uploads/materi/5_pwd_1747982274.pdf', '5', 124248, 'Genap', '2024/2025', '2025-05-23 06:37:54'),
(3, 'erp', '', 'erp', '3', 10, 'Dokumen', '../../../uploads/materi/3_erp_1748490603.pdf', '3_erp_1748490603.pdf', 188816, 'Genap', '2024/2025', '2025-05-26 03:41:59'),
(4, 'apsi', 'buat perusahaan', 'pbo', '5', 10, 'Dokumen', '../../../uploads/materi/5_pbo_1748410638.pdf', '5', 197049, 'Genap', '2024/2025', '2025-05-28 05:37:18');

-- --------------------------------------------------------

--
-- Table structure for table `pembayaran_ukt`
--

CREATE TABLE `pembayaran_ukt` (
  `id` int(11) NOT NULL,
  `mahasiswa_id` int(11) NOT NULL,
  `tahun_ajaran` varchar(9) NOT NULL,
  `semester` enum('Ganjil','Genap') NOT NULL,
  `golongan_ukt` enum('I','II','III','IV','V','VI','VII','VIII') NOT NULL,
  `nominal` decimal(12,2) NOT NULL,
  `status` enum('pending','paid','failed','expired') DEFAULT 'pending',
  `metode_pembayaran` enum('transfer','virtual_account','qris','cash') DEFAULT NULL,
  `bukti_pembayaran` varchar(255) DEFAULT NULL,
  `tanggal_bayar` datetime DEFAULT NULL,
  `batas_pembayaran` datetime NOT NULL,
  `kode_pembayaran` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pembayaran_ukt`
--

INSERT INTO `pembayaran_ukt` (`id`, `mahasiswa_id`, `tahun_ajaran`, `semester`, `golongan_ukt`, `nominal`, `status`, `metode_pembayaran`, `bukti_pembayaran`, `tanggal_bayar`, `batas_pembayaran`, `kode_pembayaran`, `created_at`, `updated_at`) VALUES
(1, 1, '2024/2025', 'Genap', 'III', 1500000.00, 'paid', 'transfer', 'uploads/bukti_bayar/ukt_1_1748491940.jpg', '2025-05-28 19:37:55', '2025-06-30 23:59:59', 'UKT24050001', '2025-05-20 03:14:10', '2025-05-28 19:37:55'),
(2, 134, '2024/2025', 'Genap', 'IV', 2000000.00, 'paid', 'qris', 'uploads/bukti_bayar/ukt_134_1748491941.png', '2025-05-28 19:37:55', '2025-06-30 23:59:59', 'UKT24050002', '2025-05-20 03:14:10', '2025-05-28 19:37:55'),
(3, 105, '2024/2025', 'Genap', 'I', 500000.00, 'pending', NULL, NULL, NULL, '2025-06-30 23:59:59', 'UKT24050003', '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
(4, 106, '2024/2025', 'Genap', 'II', 1000000.00, 'paid', 'virtual_account', NULL, '2025-05-25 12:23:53', '2025-06-30 23:59:59', 'UKT24050004', '2025-05-20 03:14:10', '2025-05-25 12:23:53'),
(5, 1, '2025/2026', 'Ganjil', 'III', 1500000.00, 'pending', 'qris', NULL, NULL, '2025-11-12 20:01:18', 'UKT202511050001298', '2025-11-05 19:01:18', '2025-11-05 19:01:38'),
(6, 134, '2025/2026', 'Ganjil', 'IV', 2000000.00, 'pending', 'virtual_account', NULL, NULL, '2025-11-15 09:54:09', 'UKT202511080134616', '2025-11-08 08:54:09', '2025-11-08 08:54:26');

-- --------------------------------------------------------

--
-- Table structure for table `program_studi`
--

CREATE TABLE `program_studi` (
  `kode_prodi` varchar(10) NOT NULL,
  `nama_prodi` varchar(100) NOT NULL,
  `fakultas` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `program_studi`
--

INSERT INTO `program_studi` (`kode_prodi`, `nama_prodi`, `fakultas`, `created_at`, `updated_at`) VALUES
('bio', 'biologi', 'sainst & teknonogi', '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
('fsk', 'fisika', 'sainst & teknonogi', '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
('ilkom', 'ilmu komputer', 'sainst & teknonogi', '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
('mm', 'matematika', 'sainst & teknonogi', '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
('si', 'sistem informasi', 'sainst & teknonogi', '2025-05-20 03:14:10', '2025-05-20 03:14:10');

-- --------------------------------------------------------

--
-- Table structure for table `transaksi_pembayaran`
--

CREATE TABLE `transaksi_pembayaran` (
  `id` int(11) NOT NULL,
  `pembayaran_id` int(11) NOT NULL,
  `external_id` varchar(50) DEFAULT NULL,
  `channel` varchar(50) DEFAULT NULL,
  `status` enum('pending','success','failed','expired') DEFAULT 'pending',
  `amount` decimal(12,2) NOT NULL,
  `paid_amount` decimal(12,2) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'IDR',
  `created_time` datetime DEFAULT NULL,
  `paid_time` datetime DEFAULT NULL,
  `expire_time` datetime DEFAULT NULL,
  `payment_details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ukt_tarif`
--

CREATE TABLE `ukt_tarif` (
  `id` int(11) NOT NULL,
  `prodi` varchar(10) NOT NULL,
  `golongan` enum('I','II','III','IV','V','VI','VII','VIII') NOT NULL,
  `nominal` decimal(12,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ukt_tarif`
--

INSERT INTO `ukt_tarif` (`id`, `prodi`, `golongan`, `nominal`, `created_at`, `updated_at`) VALUES
(1, 'si', 'I', 500000.00, '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
(2, 'si', 'II', 1000000.00, '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
(3, 'si', 'III', 1500000.00, '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
(4, 'si', 'IV', 2000000.00, '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
(5, 'si', 'V', 2500000.00, '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
(6, 'si', 'VI', 3000000.00, '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
(7, 'si', 'VII', 3500000.00, '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
(8, 'si', 'VIII', 4000000.00, '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
(9, 'ilkom', 'I', 550000.00, '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
(10, 'ilkom', 'II', 1100000.00, '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
(11, 'ilkom', 'III', 1650000.00, '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
(12, 'ilkom', 'IV', 2200000.00, '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
(13, 'mm', 'I', 450000.00, '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
(14, 'mm', 'II', 900000.00, '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
(15, 'mm', 'III', 1350000.00, '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
(16, 'bio', 'I', 400000.00, '2025-05-20 03:14:10', '2025-05-20 03:14:10'),
(17, 'bio', 'II', 800000.00, '2025-05-20 03:14:10', '2025-05-20 03:14:10');

-- --------------------------------------------------------

--
-- Table structure for table `users`
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
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `nama`, `prodi`, `created_at`, `updated_at`) VALUES
(2, 'mahasiswa', '$2y$10$5QFwkqG3XJtJAsP3qWPU3.EyjHPGEDXpWUjYWvz/WvgAqJIFUnFpW', 'mahasiswa', 'Mahasiswa Demo', 'si', '2025-05-16 23:54:01', '2025-10-27 07:05:56'),
(20, 'afif', '$2y$10$T72X0IPWl4d92B44jNxlOu1Zm5YKPFfvHnVubONFqbT/oxg0i2Z8u', 'dosen', 'affifudin M.Kom', NULL, '2025-05-22 15:47:26', '2025-10-27 07:04:44'),
(22, 'raissa', 'raissa', 'dosen', 'raissa amanda, M.Kom', NULL, '2025-05-22 15:49:43', '2025-05-28 05:06:39'),
(23, 'adnan', 'adnan', 'dosen', 'adnan buyung', NULL, '2025-05-22 15:49:43', '2025-05-28 19:35:00'),
(24, 'dedi', 'dedi', 'dosen', 'dedi', NULL, '2025-05-22 15:49:43', '2025-05-25 05:21:28'),
(25, 'dosenmm', 'dosenmm', 'dosen', 'dosen mm', NULL, '2025-05-22 15:49:43', '2025-05-25 05:21:28'),
(26, 'gibran', 'gibran', 'dosen', 'gibran', NULL, '2025-05-22 15:49:43', '2025-05-25 05:21:28'),
(27, 'fathiya', 'fathiya', 'dosen', 'fathiya', NULL, '2025-05-22 15:49:43', '2025-05-25 05:21:28'),
(28, 'dosenchil', 'dosenchil', 'dosen', 'dosen chil', NULL, '2025-05-22 15:50:35', '2025-05-25 05:21:28'),
(30, 'mahsiswa1', 'mahasiswa1', 'mahasiswa', 'Mahasiswa 1', NULL, '2025-05-22 16:03:59', '2025-05-22 16:03:59'),
(32, 'butet', '$2y$10$Lfr31F9nG5xMn9EJbiKWBueB/bfv9KAA.GoGyBI.dgiZapxnezWEe', 'mahasiswa', 'butet', NULL, '2025-05-22 18:43:18', '2025-10-26 16:02:50'),
(34, '0702232115', '0702232115', 'mahasiswa', 'SEPIRA YUNDA', NULL, '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(35, '0702232128', '0702232128', 'mahasiswa', 'MUHAMMAD NOVAL REVALDI', NULL, '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(36, '0702232116', '0702232116', 'mahasiswa', 'AMIRA SALSABILA', NULL, '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(37, '0702232129', '0702232129', 'mahasiswa', 'MUHAMMAD FARUQI ADRI', NULL, '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(39, '0702232122', '0702232122', 'mahasiswa', 'CINDY ANGGRIANI', NULL, '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(40, '0702233161', '0702233161', 'mahasiswa', 'MUCH NUR SYAMS SIMAJA', NULL, '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(41, '0702231040', '0702231040', 'mahasiswa', 'INDAH DWI PANCARI', NULL, '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(42, '0702232118', '0702232118', 'mahasiswa', 'MHD. FARRAZ FATH', NULL, '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(43, '0702232117', '0702232117', 'mahasiswa', 'NASRULLAH GUNAWAN', NULL, '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(44, '0702231041', '0702231041', 'mahasiswa', 'NOVILYA MUSFIRA BAHRI', NULL, '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(45, '0702232119', '0702232119', 'mahasiswa', 'HAMZA DWI AULIA WARHANA', NULL, '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(46, '0702231048', '0702231048', 'mahasiswa', 'LAILA AZIZAH', NULL, '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(47, '0702232113', '0702232113', 'mahasiswa', 'WILONA RAMADHANI K', NULL, '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(48, '0702232126', '0702232126', 'mahasiswa', 'SHERLY PITALOKA', NULL, '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(49, '0702233165', '0702233165', 'mahasiswa', 'MAHARANI BR. SARAGIH', NULL, '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(50, '0702231047', '0702231047', 'mahasiswa', 'ARFINI TRI AGUSTINA', NULL, '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(51, '0702232125', '0702232125', 'mahasiswa', 'EKA JUANDA. H', NULL, '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(53, '0702232114', '0702232114', 'mahasiswa', 'MUHAMMAD ALVIN NURRAHMAN', NULL, '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(54, '0702232127', '0702232127', 'mahasiswa', 'DEFT SANJAYA', NULL, '2025-05-22 19:14:28', '2025-05-22 19:55:59'),
(55, '0702231046', '$2y$10$5QFwkqG3XJtJAsP3qWPU3.EyjHPGEDXpWUjYWvz/WvgAqJIFUnFpW', 'mahasiswa', 'DZAKWAN ABBAS', NULL, '2025-05-22 19:14:28', '2025-11-08 09:01:48'),
(56, '0702232124', '0702232124', 'mahasiswa', 'PUTRI SYAYUZA', NULL, '2025-05-22 19:14:28', '2025-05-22 19:56:00'),
(57, '0702233163', '0702233163', 'mahasiswa', 'RIZAL AMRI KHOIRUL HAKIM RITONGA', NULL, '2025-05-22 19:14:28', '2025-05-22 19:57:20'),
(59, '0702232120', '0702232120', 'mahasiswa', 'MUTIA HERMAN', NULL, '2025-05-22 19:14:28', '2025-05-22 19:57:20'),
(60, '0702233159', '0702233159', 'mahasiswa', 'MHD. AYUB ARDI', NULL, '2025-05-22 19:14:28', '2025-05-22 19:57:20'),
(61, '0702231045', '0702231045', 'mahasiswa', 'MEIRANDA SIREGAR', NULL, '2025-05-22 19:14:28', '2025-05-22 19:57:20'),
(62, '0702232123', '0702232123', 'mahasiswa', 'MUHAMMAD MUSH\'AB UMAIR DAULAY', NULL, '2025-05-22 19:14:28', '2025-05-22 19:57:20'),
(64, '0702231043', '$2y$10$WGxTdGMs0S1XfMnAhTQ/x.Ocxpq8s.Xb2iW7m5y99a75RZuIw9XE2', 'mahasiswa', 'MUHAMMAD RICHIE HADIANSAH', 'sistem informasi', '2025-05-22 19:14:28', '2025-11-08 08:54:00'),
(65, '0702232121', '0702232121', 'mahasiswa', 'IDHAM TIOFANDY HASIBUAN', NULL, '2025-05-22 19:14:28', '2025-05-22 19:57:20'),
(66, '0702233160', '0702233160', 'mahasiswa', 'NABIL AFIQ', NULL, '2025-05-22 19:14:28', '2025-05-22 19:57:20'),
(67, 'admin1', '$2y$10$Dc0P2tqrYphi.9wSIvT9UO0VVEe5u1gul/hexlSjIrK791/U20Umu', 'admin', 'admin1', '', '2025-05-24 19:32:45', '2025-10-27 06:59:14'),
(68, 'dex', 'dex', 'dosen', 'D E X', NULL, '2025-05-27 22:34:10', '2025-05-27 22:34:10');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `data_nilai`
--
ALTER TABLE `data_nilai`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mahasiswa_id` (`mahasiswa_id`),
  ADD KEY `jadwal_id` (`jadwal_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_nilai_mahasiswa` (`mahasiswa_id`,`jadwal_id`);

--
-- Indexes for table `dosen`
--
ALTER TABLE `dosen`
  ADD PRIMARY KEY (`nip`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `prodi` (`prodi`);

--
-- Indexes for table `jadwal_kuliah`
--
ALTER TABLE `jadwal_kuliah`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kode_matkul` (`kode_matkul`),
  ADD KEY `dosen_nip` (`dosen_nip`),
  ADD KEY `idx_jadwal_semester` (`semester`,`tahun_ajaran`);

--
-- Indexes for table `krs`
--
ALTER TABLE `krs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_krs` (`mahasiswa_id`,`jadwal_id`,`tahun_ajaran`),
  ADD KEY `jadwal_id` (`jadwal_id`),
  ADD KEY `idx_krs_status` (`status`),
  ADD KEY `idx_krs_tahun` (`tahun_ajaran`,`semester`);

--
-- Indexes for table `mahasiswa`
--
ALTER TABLE `mahasiswa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nim` (`nim`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `prodi` (`prodi`),
  ADD KEY `idx_mahasiswa_prodi` (`prodi`,`semester_aktif`);

--
-- Indexes for table `mata_kuliah`
--
ALTER TABLE `mata_kuliah`
  ADD PRIMARY KEY (`kode_matkul`),
  ADD KEY `prodi` (`prodi`),
  ADD KEY `idx_matkul_semester` (`semester`,`prodi`);

--
-- Indexes for table `materi_perkuliahan`
--
ALTER TABLE `materi_perkuliahan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kode_matkul` (`kode_matkul`),
  ADD KEY `dosen_nip` (`dosen_nip`),
  ADD KEY `idx_materi_semester` (`semester`,`tahun_ajaran`);

--
-- Indexes for table `pembayaran_ukt`
--
ALTER TABLE `pembayaran_ukt`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_pembayaran` (`kode_pembayaran`),
  ADD UNIQUE KEY `unique_pembayaran` (`mahasiswa_id`,`tahun_ajaran`,`semester`),
  ADD KEY `mahasiswa_id` (`mahasiswa_id`),
  ADD KEY `idx_pembayaran_status` (`status`),
  ADD KEY `idx_pembayaran_tanggal` (`tanggal_bayar`),
  ADD KEY `idx_pembayaran_batas` (`batas_pembayaran`);

--
-- Indexes for table `program_studi`
--
ALTER TABLE `program_studi`
  ADD PRIMARY KEY (`kode_prodi`);

--
-- Indexes for table `transaksi_pembayaran`
--
ALTER TABLE `transaksi_pembayaran`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `external_id` (`external_id`),
  ADD KEY `pembayaran_id` (`pembayaran_id`),
  ADD KEY `idx_transaksi_status` (`status`),
  ADD KEY `idx_transaksi_waktu` (`created_time`);

--
-- Indexes for table `ukt_tarif`
--
ALTER TABLE `ukt_tarif`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tarif` (`prodi`,`golongan`),
  ADD KEY `prodi` (`prodi`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_user_role` (`role`),
  ADD KEY `idx_user_prodi` (`prodi`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `pembayaran_ukt`
--
ALTER TABLE `pembayaran_ukt`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `transaksi_pembayaran`
--
ALTER TABLE `transaksi_pembayaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ukt_tarif`
--
ALTER TABLE `ukt_tarif`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
