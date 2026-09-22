-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql103.infinityfree.com
-- Generation Time: Jul 18, 2026 at 11:02 PM
-- Server version: 11.4.12-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_42310664_digishulk_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `rates`
--

CREATE TABLE `rates` (
  `id` int(11) NOT NULL,
  `stall_type` varchar(50) DEFAULT NULL,
  `price_per_sqft` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rates`
--

INSERT INTO `rates` (`id`, `stall_type`, `price_per_sqft`) VALUES
(1, 'Rekdi', '10.00'),
(2, 'Mandap', '20.00');

-- --------------------------------------------------------

--
-- Table structure for table `seizure_items`
--

CREATE TABLE `seizure_items` (
  `item_id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `godown_register_no` varchar(100) DEFAULT NULL,
  `item_details` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL,
  `owner_merchant_name` varchar(150) DEFAULT NULL,
  `seizure_location` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `seizure_items`
--

INSERT INTO `seizure_items` (`item_id`, `session_id`, `godown_register_no`, `item_details`, `quantity`, `owner_merchant_name`, `seizure_location`) VALUES
(1, 1, '12', 'rekdi', 12, 'sad', 'rrrr');

-- --------------------------------------------------------

--
-- Table structure for table `seizure_sessions`
--

CREATE TABLE `seizure_sessions` (
  `session_id` int(11) NOT NULL,
  `inspector_id` int(11) NOT NULL,
  `team_leader_name` varchar(150) NOT NULL,
  `zone` varchar(50) NOT NULL,
  `team_number` varchar(50) NOT NULL,
  `seizure_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `seizure_sessions`
--

INSERT INTO `seizure_sessions` (`session_id`, `inspector_id`, `team_leader_name`, `zone`, `team_number`, `seizure_date`, `created_at`) VALUES
(1, 7, 'sah', 'west', '12', '2026-07-18', '2026-07-18 04:53:04');

-- --------------------------------------------------------

--
-- Table structure for table `shops`
--

CREATE TABLE `shops` (
  `shop_id` int(11) NOT NULL,
  `shop_name` varchar(150) NOT NULL,
  `owner_name` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `zone` varchar(100) DEFAULT NULL,
  `stall_type` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_visit` date DEFAULT NULL,
  `last_amount` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shops`
--

INSERT INTO `shops` (`shop_id`, `shop_name`, `owner_name`, `phone`, `address`, `zone`, `stall_type`, `notes`, `latitude`, `longitude`, `created_at`, `updated_at`, `last_visit`, `last_amount`) VALUES
(7, 'bbb', NULL, '1234567897', 'bbb', NULL, 'Rekdi', NULL, NULL, NULL, '2026-07-16 05:55:39', '2026-07-16 05:55:39', NULL, NULL),
(10, 'as', NULL, '1231231231', 'as', NULL, 'Rekdi', NULL, NULL, NULL, '2026-07-17 07:54:00', '2026-07-17 07:54:00', NULL, NULL),
(12, 'asd', NULL, '111111111111', 'asd', NULL, 'Rekdi', NULL, NULL, NULL, '2026-07-18 08:24:32', '2026-07-18 08:24:32', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL,
  `inspector_id` int(11) DEFAULT NULL,
  `shop_id` int(11) DEFAULT NULL,
  `stall_type` varchar(50) DEFAULT NULL,
  `area_sqft` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','paid','failed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `shop_name` varchar(100) DEFAULT NULL,
  `shop_address` text DEFAULT NULL,
  `shopkeeper_phone` varchar(15) DEFAULT NULL,
  `payment_mode` varchar(20) DEFAULT 'cash',
  `payment_ref` varchar(100) DEFAULT NULL,
  `sms_log` text DEFAULT NULL,
  `receipt_number` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`transaction_id`, `inspector_id`, `shop_id`, `stall_type`, `area_sqft`, `total_amount`, `status`, `created_at`, `shop_name`, `shop_address`, `shopkeeper_phone`, `payment_mode`, `payment_ref`, `sms_log`, `receipt_number`) VALUES
(1, 7, NULL, 'Rekdi', 12, '2.00', 'paid', '2026-07-18 05:07:53', 'as', 'as', '1231231231', 'cash', NULL, NULL, 'RMC-20260717-0001'),
(2, 7, NULL, 'Rekdi', 12, '12.00', 'paid', '2026-07-18 08:24:32', 'asd', 'asd', '111111111111', 'cash', NULL, '{\"return\":false,\"status_code\":412,\"message\":\"Invalid Authentication, Check Authorization Key\"}', 'RMC-20260718-0002');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'inspector',
  `last_active` datetime NOT NULL DEFAULT current_timestamp(),
  `profile_photo` varchar(255) DEFAULT 'default.png',
  `full_name` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `role`, `last_active`, `profile_photo`, `full_name`) VALUES
(7, 'rmx', '$2y$10$IK7zd0FmzwZLOFNDPdgVy.aMssnX4ihNpmQrPeW77LpUBm8kqUutS', 'inspector', '2026-07-18 01:25:21', 'default.png', 'Sahdev'),
(8, 'ad', '$2y$10$1zUfOEuIbeh03anCPxyAg.w.VcA4KfJoIkFrqj4SpWEX/jKPjFrVq', 'admin', '2026-07-18 10:20:27', 'user_8.jpg', 'I am ADMIN'),
(10, 'qw', '$2y$10$BrGR5hgC7XXO5Hv9JqbEq.3HREGxuogN0nyqUSoAlM94czfi/l7Xa', 'inspector', '2026-07-16 11:38:47', 'default.png', NULL),
(11, 'zx', '$2y$10$4MsmyAUQmGEexYyZgvIrc.ssuDuhYfoy8g6YT/85onFC3.0U1loYe', 'inspector', '2026-07-11 23:08:53', 'default.png', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `rates`
--
ALTER TABLE `rates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `seizure_items`
--
ALTER TABLE `seizure_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `fk_item_session` (`session_id`);

--
-- Indexes for table `seizure_sessions`
--
ALTER TABLE `seizure_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `idx_sess_date` (`seizure_date`),
  ADD KEY `fk_sess_inspector` (`inspector_id`);

--
-- Indexes for table `shops`
--
ALTER TABLE `shops`
  ADD PRIMARY KEY (`shop_id`),
  ADD UNIQUE KEY `unique_phone` (`phone`),
  ADD UNIQUE KEY `uq_shop_phone` (`phone`),
  ADD KEY `idx_shop_name` (`shop_name`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `idx_txn_created` (`created_at`),
  ADD KEY `idx_txn_inspector` (`inspector_id`),
  ADD KEY `idx_txn_status` (`status`),
  ADD KEY `idx_txn_phone` (`shopkeeper_phone`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_last_active` (`last_active`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `rates`
--
ALTER TABLE `rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `seizure_items`
--
ALTER TABLE `seizure_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `seizure_sessions`
--
ALTER TABLE `seizure_sessions`
  MODIFY `session_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `shops`
--
ALTER TABLE `shops`
  MODIFY `shop_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `seizure_items`
--
ALTER TABLE `seizure_items`
  ADD CONSTRAINT `fk_item_session` FOREIGN KEY (`session_id`) REFERENCES `seizure_sessions` (`session_id`);

--
-- Constraints for table `seizure_sessions`
--
ALTER TABLE `seizure_sessions`
  ADD CONSTRAINT `fk_sess_inspector` FOREIGN KEY (`inspector_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_txn_inspector` FOREIGN KEY (`inspector_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
