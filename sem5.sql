-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 18, 2026 at 08:21 AM
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
-- Database: `sem5`
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
(1, 'Rekdi', 10.00),
(2, 'Mandap', 20.00);

-- --------------------------------------------------------

--
-- Table structure for table `rmc_seizures_old_unused`
--

CREATE TABLE `rmc_seizures_old_unused` (
  `id` int(11) NOT NULL,
  `inspector_id` int(11) NOT NULL,
  `team_leader_name` varchar(150) NOT NULL,
  `zone` varchar(50) NOT NULL,
  `team_number` varchar(50) NOT NULL,
  `godown_register_no` varchar(100) NOT NULL,
  `seized_item_details` text NOT NULL,
  `quantity_seized` int(11) NOT NULL,
  `owner_merchant_name` varchar(150) NOT NULL,
  `seizure_location` text NOT NULL,
  `seizure_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `seizure_items`
--

CREATE TABLE `seizure_items` (
  `id` int(11) NOT NULL,
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

INSERT INTO `seizure_items` (`id`, `session_id`, `godown_register_no`, `item_details`, `quantity`, `owner_merchant_name`, `seizure_location`) VALUES
(1, 1, '12', 'rekdi', 12, 'sad', 'rrrr');

-- --------------------------------------------------------

--
-- Table structure for table `seizure_sessions`
--

CREATE TABLE `seizure_sessions` (
  `id` int(11) NOT NULL,
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

INSERT INTO `seizure_sessions` (`id`, `inspector_id`, `team_leader_name`, `zone`, `team_number`, `seizure_date`, `created_at`) VALUES
(1, 7, 'sah', 'west', '12', '2026-07-18', '2026-07-18 04:53:04');

-- --------------------------------------------------------

--
-- Table structure for table `shops`
--

CREATE TABLE `shops` (
  `id` int(11) NOT NULL,
  `shop_name` varchar(150) NOT NULL,
  `owner_name` varchar(150) DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `zone` varchar(100) DEFAULT NULL,
  `stall_type` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shops`
--

INSERT INTO `shops` (`id`, `shop_name`, `owner_name`, `phone`, `address`, `zone`, `stall_type`, `notes`, `latitude`, `longitude`, `created_at`, `updated_at`) VALUES
(7, 'bbb', NULL, '1234567897', 'bbb', NULL, 'Rekdi', NULL, NULL, NULL, '2026-07-16 05:55:39', '2026-07-16 05:55:39'),
(10, 'as', NULL, '1231231231', 'as', NULL, 'Rekdi', NULL, NULL, NULL, '2026-07-17 07:54:00', '2026-07-17 07:54:00');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
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
  `sms_log` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `inspector_id`, `shop_id`, `stall_type`, `area_sqft`, `total_amount`, `status`, `created_at`, `shop_name`, `shop_address`, `shopkeeper_phone`, `payment_mode`, `payment_ref`, `sms_log`) VALUES
(1, 7, NULL, 'Rekdi', 12, 2.00, 'paid', '2026-07-18 05:07:53', 'as', 'as', '1231231231', 'cash', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
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

INSERT INTO `users` (`id`, `username`, `password`, `role`, `last_active`, `profile_photo`, `full_name`) VALUES
(7, 'rmx', '$2y$10$IK7zd0FmzwZLOFNDPdgVy.aMssnX4ihNpmQrPeW77LpUBm8kqUutS', 'inspector', '2026-07-18 11:48:31', 'default.png', 'Sahdev'),
(8, 'ad', '$2y$10$1zUfOEuIbeh03anCPxyAg.w.VcA4KfJoIkFrqj4SpWEX/jKPjFrVq', 'admin', '2026-07-18 11:47:52', 'user_8.jpg', 'I am ADMIN'),
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
-- Indexes for table `rmc_seizures_old_unused`
--
ALTER TABLE `rmc_seizures_old_unused`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `seizure_items`
--
ALTER TABLE `seizure_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `seizure_sessions`
--
ALTER TABLE `seizure_sessions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `shops`
--
ALTER TABLE `shops`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_phone` (`phone`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `rates`
--
ALTER TABLE `rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `rmc_seizures_old_unused`
--
ALTER TABLE `rmc_seizures_old_unused`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `seizure_items`
--
ALTER TABLE `seizure_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `seizure_sessions`
--
ALTER TABLE `seizure_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `shops`
--
ALTER TABLE `shops`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
