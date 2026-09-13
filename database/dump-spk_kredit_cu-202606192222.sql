-- MySQL dump 10.13  Distrib 8.0.33, for macos13 (arm64)
--
-- Host: 127.0.0.1    Database: spk_kredit_cu
-- ------------------------------------------------------
-- Server version	9.6.0

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
SET @MYSQLDUMP_TEMP_LOG_BIN = @@SESSION.SQL_LOG_BIN;
SET @@SESSION.SQL_LOG_BIN= 0;

--
-- GTID state at the beginning of the backup 
--

SET @@GLOBAL.GTID_PURGED=/*!80000 '+'*/ '15c3f30e-49e8-11f1-90f5-9d86c5481bb0:1-4556';

--
-- Table structure for table `anggota`
--

DROP TABLE IF EXISTS `anggota`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `anggota` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `nomor_anggota` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alamat` text COLLATE utf8mb4_unicode_ci,
  `no_hp` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_anggota_nomor` (`nomor_anggota`),
  UNIQUE KEY `uq_anggota_user` (`user_id`),
  KEY `idx_anggota_user` (`user_id`),
  CONSTRAINT `fk_anggota_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `anggota`
--

LOCK TABLES `anggota` WRITE;
/*!40000 ALTER TABLE `anggota` DISABLE KEYS */;
INSERT INTO `anggota` VALUES (5,7,'ANG-0005','Muhammad Fatkhul Alfi','Jl.adisucipto','08215177283','mfalfi02@gmail.com','2002-06-19','2026-06-19 09:31:28'),(7,9,'ANG-0007','epang','jl.kemuning','086534667','spkkreditcu@gmail.com','2000-06-12','2026-06-19 10:26:43');
/*!40000 ALTER TABLE `anggota` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dokumen`
--

DROP TABLE IF EXISTS `dokumen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dokumen` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `pengajuan_id` int unsigned NOT NULL,
  `nama_file` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path_file` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jenis` enum('ktp','slip_gaji','surat_kerja','kartu_pelajar','kartu_keluarga','jaminan','foto_usaha','izin_usaha','laporan_usaha') COLLATE utf8mb4_unicode_ci NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dokumen_pengajuan` (`pengajuan_id`),
  CONSTRAINT `fk_dokumen_pengajuan` FOREIGN KEY (`pengajuan_id`) REFERENCES `pengajuan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dokumen`
--

LOCK TABLES `dokumen` WRITE;
/*!40000 ALTER TABLE `dokumen` DISABLE KEYS */;
/*!40000 ALTER TABLE `dokumen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifikasi`
--

DROP TABLE IF EXISTS `notifikasi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifikasi` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `anggota_id` int unsigned NOT NULL,
  `pengajuan_id` int unsigned NOT NULL,
  `judul` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pesan` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `deadline_at` datetime NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notifikasi_pengajuan` (`pengajuan_id`),
  KEY `idx_notifikasi_anggota` (`anggota_id`),
  KEY `idx_notifikasi_deadline` (`deadline_at`),
  KEY `idx_notifikasi_is_read` (`is_read`),
  CONSTRAINT `fk_notifikasi_anggota` FOREIGN KEY (`anggota_id`) REFERENCES `anggota` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_notifikasi_pengajuan` FOREIGN KEY (`pengajuan_id`) REFERENCES `pengajuan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifikasi`
--

LOCK TABLES `notifikasi` WRITE;
/*!40000 ALTER TABLE `notifikasi` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifikasi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hasil_saw`
--

DROP TABLE IF EXISTS `hasil_saw`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hasil_saw` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `pengajuan_id` int unsigned NOT NULL,
  `jenis_kredit` enum('KTA','KUR') COLLATE utf8mb4_unicode_ci NOT NULL,
  `skor_normalisasi` decimal(10,4) DEFAULT NULL,
  `skor_terbobot` decimal(10,4) DEFAULT NULL,
  `persentase_saw` decimal(6,2) DEFAULT NULL,
  `kelayakan` enum('layak','tidak_layak') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ranking` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hasil_saw_pengajuan` (`pengajuan_id`),
  KEY `idx_hasil_saw_pengajuan` (`pengajuan_id`),
  KEY `idx_hasil_saw_jenis` (`jenis_kredit`),
  CONSTRAINT `fk_hasil_saw_pengajuan` FOREIGN KEY (`pengajuan_id`) REFERENCES `pengajuan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hasil_saw`
--

LOCK TABLES `hasil_saw` WRITE;
/*!40000 ALTER TABLE `hasil_saw` DISABLE KEYS */;
/*!40000 ALTER TABLE `hasil_saw` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kriteria`
--

DROP TABLE IF EXISTS `kriteria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kriteria` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bobot` decimal(5,2) NOT NULL,
  `jenis` enum('benefit','cost') COLLATE utf8mb4_unicode_ci NOT NULL,
  `jenis_kredit` enum('KTA','KUR','BOTH') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'BOTH',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_kriteria_jenis_kredit` (`jenis_kredit`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kriteria`
--

LOCK TABLES `kriteria` WRITE;
/*!40000 ALTER TABLE `kriteria` DISABLE KEYS */;
INSERT INTO `kriteria` VALUES (1,'Pendapatan',0.30,'benefit','KTA','2026-06-19 08:44:49'),(2,'Riwayat Pinjaman',0.20,'benefit','KTA','2026-06-19 08:44:49'),(3,'Usia',0.15,'benefit','KTA','2026-06-19 08:44:49'),(4,'Stabilitas Pekerjaan',0.20,'benefit','KTA','2026-06-19 08:44:49'),(5,'Beban Cicilan',0.15,'cost','KTA','2026-06-19 08:44:49'),(6,'Lama Usaha',0.20,'benefit','KUR','2026-06-19 08:44:49'),(7,'Omzet Usaha',0.25,'benefit','KUR','2026-06-19 08:44:49'),(8,'Laba Bersih',0.25,'benefit','KUR','2026-06-19 08:44:49'),(9,'Legalitas Usaha',0.15,'benefit','KUR','2026-06-19 08:44:49'),(10,'Jumlah Karyawan',0.15,'benefit','KUR','2026-06-19 08:44:49');
/*!40000 ALTER TABLE `kriteria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `laporan`
--

DROP TABLE IF EXISTS `laporan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `laporan` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `generated_by` int unsigned NOT NULL,
  `jenis_laporan` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_laporan_user` (`generated_by`),
  CONSTRAINT `fk_laporan_user` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `laporan`
--

LOCK TABLES `laporan` WRITE;
/*!40000 ALTER TABLE `laporan` DISABLE KEYS */;
/*!40000 ALTER TABLE `laporan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pengajuan`
--

DROP TABLE IF EXISTS `pengajuan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pengajuan` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `anggota_id` int unsigned NOT NULL,
  `jenis_kredit` enum('KTA','KUR') COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah_pinjaman` decimal(15,2) NOT NULL,
  `detail_pinjaman` json DEFAULT NULL,
  `status` enum('pending','verified','document_rejected','accepted','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pengajuan_anggota` (`anggota_id`),
  KEY `idx_pengajuan_status` (`status`),
  KEY `idx_pengajuan_jenis` (`jenis_kredit`),
  CONSTRAINT `fk_pengajuan_anggota` FOREIGN KEY (`anggota_id`) REFERENCES `anggota` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pengajuan`
--

LOCK TABLES `pengajuan` WRITE;
/*!40000 ALTER TABLE `pengajuan` DISABLE KEYS */;
/*!40000 ALTER TABLE `pengajuan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `penilaian`
--

DROP TABLE IF EXISTS `penilaian`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `penilaian` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `pengajuan_id` int unsigned NOT NULL,
  `kriteria_id` int unsigned NOT NULL,
  `nilai` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_penilaian_pengajuan_kriteria` (`pengajuan_id`,`kriteria_id`),
  KEY `idx_penilaian_pengajuan` (`pengajuan_id`),
  KEY `idx_penilaian_kriteria` (`kriteria_id`),
  CONSTRAINT `fk_penilaian_kriteria` FOREIGN KEY (`kriteria_id`) REFERENCES `kriteria` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_penilaian_pengajuan` FOREIGN KEY (`pengajuan_id`) REFERENCES `pengajuan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `penilaian`
--

LOCK TABLES `penilaian` WRITE;
/*!40000 ALTER TABLE `penilaian` DISABLE KEYS */;
/*!40000 ALTER TABLE `penilaian` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `riwayat_pengajuan`
--

DROP TABLE IF EXISTS `riwayat_pengajuan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `riwayat_pengajuan` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `pengajuan_id` int unsigned NOT NULL,
  `aksi` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dilakukan_oleh` int unsigned NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_riwayat_pengajuan` (`pengajuan_id`),
  KEY `idx_riwayat_user` (`dilakukan_oleh`),
  CONSTRAINT `fk_riwayat_pengajuan` FOREIGN KEY (`pengajuan_id`) REFERENCES `pengajuan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_riwayat_user` FOREIGN KEY (`dilakukan_oleh`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `riwayat_pengajuan`
--

LOCK TABLES `riwayat_pengajuan` WRITE;
/*!40000 ALTER TABLE `riwayat_pengajuan` DISABLE KEYS */;
/*!40000 ALTER TABLE `riwayat_pengajuan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'admin'),(3,'anggota'),(2,'petugas');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role_id` int unsigned NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `verification_token_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verification_code_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verification_expires_at` datetime DEFAULT NULL,
  `verification_sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  KEY `idx_users_role` (`role_id`),
  KEY `idx_users_email_verified_at` (`email_verified_at`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','$2y$10$h09xwVapY1JRS2DgIXsog.tznX67Q88RFx79e1wSDEe5/mMwSU.yW',1,'2026-06-19 09:07:23',NULL,NULL,NULL,NULL,'2026-06-19 08:43:22'),(2,'petugas','$2y$10$Mw9sY/pmnwqvuWhhC2gU8eqla.BJ94rWaBnni1rxjthZengffrWO6',2,'2026-06-19 08:43:35',NULL,NULL,NULL,NULL,'2026-06-19 08:43:35'),(7,'Alfi','$2y$10$I/45uHlLqDYYeaHy0wZWJeJoc5tTg0mxzT.fUmDv2vNIkpxLScSHm',3,'2026-06-19 10:05:21',NULL,NULL,NULL,NULL,'2026-06-19 09:31:28'),(9,'epang','$2y$10$ZHvQDBRQJyRBoOzjFx3EdulZcFAEncDwAA.zhvFTjTtsvtAg3Mf2q',3,'2026-06-19 10:27:46',NULL,NULL,NULL,NULL,'2026-06-19 10:26:43');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'spk_kredit_cu'
--
SET @@SESSION.SQL_LOG_BIN = @MYSQLDUMP_TEMP_LOG_BIN;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-19 22:22:19
