-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 10, 2025 at 09:34 AM
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
-- Database: `mcnp_isap_facility`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `facility_requests`
--

CREATE TABLE `facility_requests` (
  `id` int(11) NOT NULL,
  `control_number` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `requestor_name` varchar(255) NOT NULL,
  `department` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone_number` varchar(50) DEFAULT NULL,
  `event_type` varchar(255) NOT NULL,
  `request_date` date NOT NULL,
  `status` enum('Pending','Approved','Rejected','Cancelled','pending','approved','rejected','cancelled') DEFAULT 'Pending',
  `admin_remarks` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `facility_requests`
--

INSERT INTO `facility_requests` (`id`, `control_number`, `user_id`, `requestor_name`, `department`, `email`, `phone_number`, `event_type`, `request_date`, `status`, `admin_remarks`, `admin_notes`, `created_at`, `updated_at`) VALUES
(1, 'MCNP-20251006-0940', 6, 'Ivan Manalo', 'Medical Colleges of Northern Philippines', 'ivan.manalo205@gmail.com', '09610190400', 'Seminar', '0000-00-00', 'Pending', NULL, NULL, '2025-10-06 20:07:48', '2025-10-06 20:07:48'),
(2, 'MCNP-20251006-2119', 6, 'Ivan Manalo', 'Medical Colleges of Northern Philippines', 'ivan.manalo205@gmail.com', '09610190400', 'Seminar', '0000-00-00', 'Pending', NULL, NULL, '2025-10-06 20:15:00', '2025-10-06 20:15:00'),
(3, 'MCNP-20251006-5190', 6, 'Ivan Manalo', 'Medical Colleges of Northern Philippines', 'ivan.manalo205@gmail.com', '09610190400', 'Seminar', '0000-00-00', 'Pending', NULL, NULL, '2025-10-06 20:16:45', '2025-10-06 20:16:45'),
(4, 'MCNP-20251006-2306', 6, 'Ivan Manalo', 'Medical Colleges of Northern Philippines', 'ivan.manalo205@gmail.com', '09610190400', 'Seminar', '0000-00-00', 'Pending', NULL, NULL, '2025-10-06 20:19:41', '2025-10-06 20:19:41'),
(5, 'MCNP-20251006-4336', 6, 'Ivan Manalo', 'Medical Colleges of Northern Philippines', 'ivan.manalo205@gmail.com', '09610190400', 'Seminar', '0000-00-00', 'Pending', NULL, NULL, '2025-10-06 20:20:16', '2025-10-06 20:20:16'),
(6, 'MCNP-20251006-2241', 6, 'Ivan Manalo', 'Medical Colleges of Northern Philippines', 'ivan.manalo205@gmail.com', '09610190400', 'Seminar', '0000-00-00', 'Pending', NULL, NULL, '2025-10-06 20:31:48', '2025-10-06 20:31:48'),
(7, 'MCNP-20251006-9234', 6, 'Ariana', 'Medical Colleges of Northern Philippines', 'ivan.manalo205@gmail.com', '09610190400', 'Seminar', '0000-00-00', 'Pending', NULL, NULL, '2025-10-06 20:34:37', '2025-10-06 20:34:37'),
(8, 'MCNP-20251006-0547', 6, 'Ivan Manalo', 'Medical Colleges of Northern Philippines', 'ivan.manalo205@gmail.com', '09610190400', 'Seminar', '0000-00-00', 'Pending', NULL, NULL, '2025-10-06 20:40:43', '2025-10-06 20:40:43'),
(9, 'MCNP-20251006-6991', 6, 'Ivan Manalo', 'Medical Colleges of Northern Philippines', 'ivan.manalo205@gmail.com', '09610190400', 'Seminar', '0000-00-00', 'Pending', NULL, NULL, '2025-10-06 20:49:38', '2025-10-06 20:49:38'),
(10, 'MCNP-20251006-9089', 6, 'Ivan Manalo', 'Medical Colleges of Northern Philippines', 'ivan.manalo205@gmail.com', '09610190400', 'Seminar', '0000-00-00', 'Pending', NULL, NULL, '2025-10-06 20:52:08', '2025-10-06 20:52:08'),
(11, 'MCNP-20251006-7430', 6, 'Ivan Manalo', 'Medical Colleges of Northern Philippines', 'ivan.manalo205@gmail.com', '09610190400', 'Seminar', '0000-00-00', 'Pending', NULL, NULL, '2025-10-06 20:52:44', '2025-10-06 20:52:44'),
(12, 'MCNP-20251006-9751', 6, 'Ivan Manalo', 'Medical Colleges of Northern Philippines', 'ivan.manalo205@gmail.com', '09610190400', 'Seminar', '0000-00-00', 'Pending', NULL, NULL, '2025-10-06 20:55:00', '2025-10-06 20:55:00'),
(13, 'MCNP-20251006-8493', 6, 'Ivan Manalo', 'Medical Colleges of Northern Philippines', 'ivan.manalo205@gmail.com', '09610190400', '123435', '0000-00-00', 'Pending', NULL, NULL, '2025-10-06 20:57:24', '2025-10-06 20:57:24'),
(14, 'MCNP-20251006-2823', 6, 'Ivan Manalo', 'Medical Colleges of Northern Philippines', 'ivan.manalo205@gmail.com', '09610190400', 'Seminar', '0000-00-00', 'Pending', NULL, NULL, '2025-10-06 20:59:58', '2025-10-06 20:59:58'),
(15, 'MCNP-20251007-7292', 6, 'Ivan Manalo', 'Medical Colleges of Northern Philippines', 'ivan.manalo205@gmail.com', '+639553807986', 'dd', '0000-00-00', 'Pending', NULL, NULL, '2025-10-07 10:33:16', '2025-10-07 10:33:16'),
(16, 'MCNP-20251007-0775', 6, 'Ivan Manalo', 'Medical Colleges of Northern Philippines', 'ivan.manalo205@gmail.com', '+639553807986', 'dd', '0000-00-00', 'Pending', NULL, NULL, '2025-10-07 10:35:38', '2025-10-07 10:35:38'),
(17, 'MCNP-20251007-8697', 5, 'Aiana Manalo', 'International School of Asia and the Pacific', 'amae6554@gmail.com', '', 'Seminar', '0000-00-00', 'Pending', NULL, NULL, '2025-10-07 11:22:50', '2025-10-07 11:22:50'),
(18, 'MCNP-20251007-4825', 6, 'Ivan Manaloe', 'Medical Colleges of Northern Philippines', 'ivan.manalo205@gmail.com', '09610190400', 'Seminar', '0000-00-00', 'Pending', NULL, NULL, '2025-10-07 16:34:53', '2025-10-07 16:34:53'),
(19, 'MCNP-20251007-6616', 6, 'Ivan Manaloe', 'Medical Colleges of Northern Philippines', 'ivan.manalo205@gmail.com', '09610190400', '123435', '0000-00-00', 'Pending', NULL, NULL, '2025-10-07 16:36:50', '2025-10-07 16:36:50'),
(20, 'MCNP-20251007-8984', 6, 'Ivan Manaloe', 'Medical Colleges of Northern Philippines', 'ivan.manalo205@gmail.com', '09610190400', 'gghgh', '0000-00-00', 'Pending', NULL, NULL, '2025-10-07 16:37:26', '2025-10-07 16:37:26'),
(21, 'MCNP-20251007-0810', 6, 'Ivan Manaloe', 'Medical Colleges of Northern Philippines', 'ivan.manalo205@gmail.com', '09610190400', 'ivan', '0000-00-00', 'Pending', NULL, NULL, '2025-10-07 20:01:17', '2025-10-07 20:01:17'),
(22, 'MCNP-20251007-6107', 6, 'Ivan Manalo', 'Medical Colleges of Northern Philippines', 'ivan.manalo205@gmail.com', '+639553807986', 'rrrr', '0000-00-00', 'Pending', NULL, NULL, '2025-10-07 20:39:44', '2025-10-07 20:39:44'),
(23, 'MCNP-20251007-7086', 6, 'Ivan Manalo', 'Medical Colleges of Northern Philippines', 'ivan.manalo205@gmail.com', '09610190400', 'gghgh', '0000-00-00', 'Pending', NULL, NULL, '2025-10-07 20:42:46', '2025-10-07 20:42:46');

-- --------------------------------------------------------

--
-- Table structure for table `facility_request_details`
--

CREATE TABLE `facility_request_details` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `facility_name` varchar(255) NOT NULL,
  `date_needed` date NOT NULL,
  `time_needed` varchar(100) NOT NULL,
  `total_hours` decimal(5,2) NOT NULL,
  `total_participants` int(11) NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `facility_request_details`
--

INSERT INTO `facility_request_details` (`id`, `request_id`, `facility_name`, `date_needed`, `time_needed`, `total_hours`, `total_participants`, `remarks`, `created_at`) VALUES
(1, 1, 'Cabbo La Vista', '2025-10-10', '12:00-3:00', 2.00, 2, '12345', '2025-10-06 20:07:48'),
(2, 2, 'Cabbo La Vista', '2025-10-10', '12:00-3:00', 2.00, 2, '12345', '2025-10-06 20:15:00'),
(3, 3, 'Gymnasium', '2025-10-10', '12:00-3:00', 2.00, 2, '211211', '2025-10-06 20:16:45'),
(4, 4, 'AVR 2', '2025-10-03', '12:00-3:00', 3.00, 3, '3', '2025-10-06 20:19:41'),
(5, 5, 'AMPHI 2', '2025-10-22', '12:00-3:00', 2.00, 2, 'qfsff', '2025-10-06 20:20:16'),
(6, 6, 'AMPHI 2', '2025-10-22', '12:00-3:00', 2.00, 2, 'qfsff', '2025-10-06 20:31:48'),
(7, 7, 'Studio Room', '2025-10-11', '1234', 1.00, 4, 'qqq', '2025-10-06 20:34:37'),
(8, 8, 'TM Laboratory', '2025-10-25', '12:00-3:00', 1.00, 1, 'sddff', '2025-10-06 20:40:43'),
(9, 9, 'Gymnasium', '2025-10-14', '12:00-3:00', 1.00, 2, 'j', '2025-10-06 20:49:38'),
(10, 9, 'TM Laboratory', '2025-10-16', '12:00-3:00', 8.00, 45, 'll', '2025-10-06 20:49:38'),
(11, 10, 'AVR 3', '2025-10-15', '12', 2.00, 2, '111', '2025-10-06 20:52:08'),
(12, 11, 'Gymnasium', '2025-10-08', '12:00-3:00', 3.00, 12, '222', '2025-10-06 20:52:44'),
(13, 12, 'HM Laboratory', '2025-10-15', '12:00-3:00', 2.00, 2, 'sdddf', '2025-10-06 20:55:00'),
(14, 13, 'AMPHI 2', '2025-10-03', '12:00-3:00', 999.99, 12, 'sdfsd', '2025-10-06 20:57:24'),
(15, 14, 'AMPHI 1', '2025-10-17', '12:00-3:00', 3.00, 3, '3eff', '2025-10-06 20:59:58'),
(16, 15, 'Gymnasium', '2025-10-30', '2:00-3:00', 1.00, 1, 'w', '2025-10-07 10:33:16'),
(17, 16, 'Conference Hall', '2025-10-17', '2:00-3:00', 0.50, 1, 'vvv', '2025-10-07 10:35:38'),
(18, 17, 'AMPHI 1', '2025-10-21', '12:00-3:00', 0.50, 1, 'gfgfg', '2025-10-07 11:22:50'),
(19, 18, 'Cabbo La Vista', '2025-10-21', '12:00-3:00', 1.00, 3, 'dff', '2025-10-07 16:34:53'),
(20, 19, 'AVR 1', '2025-10-16', '12:00-3:00', 3.00, 3, 'r', '2025-10-07 16:36:50'),
(21, 20, 'AVR 2', '2025-10-30', '12:00-3:00', 4.00, 4, 'fhf', '2025-10-07 16:37:26'),
(22, 21, 'Conference Hall', '2025-10-09', '7:00-8:00', 5.00, 28, 'Wow', '2025-10-07 20:01:17'),
(23, 22, 'Reading Area', '2025-10-16', '8:00-7:00 PM', 2.00, 20, 'ivan', '2025-10-07 20:39:44'),
(24, 23, 'HM Laboratory', '2025-10-03', '12:00-3', 4.00, 5, 'fgfg', '2025-10-07 20:42:46');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 6, 'Profile Updated', 'Your profile information has been successfully updated.', 'profile_update', 1, '2025-10-03 19:06:53'),
(2, 5, 'Profile Updated', 'Your profile information has been successfully updated.', 'profile_update', 1, '2025-10-03 19:08:10'),
(3, 5, 'Profile Updated', 'Your profile information has been successfully updated.', 'profile_update', 1, '2025-10-03 19:08:38'),
(4, 6, 'Profile Updated', 'Your profile information has been successfully updated.', 'profile_update', 1, '2025-10-04 11:01:28'),
(5, 6, 'Profile Updated', 'Your profile information has been successfully updated.', 'profile_update', 1, '2025-10-04 11:01:48'),
(6, 6, 'Profile Updated', 'Your profile information has been successfully updated.', 'profile_update', 1, '2025-10-04 11:01:59'),
(7, 6, 'Profile Updated', 'Your profile information has been successfully updated.', 'profile_update', 1, '2025-10-07 10:32:53'),
(12, 6, 'Profile Updated', 'Your profile information has been successfully updated.', 'profile_update', 0, '2025-10-07 16:09:33'),
(13, 6, 'Profile Updated', 'Your profile information has been updated successfully.', 'profile', 0, '2025-10-07 16:19:30'),
(14, 6, 'Phone Number Updated', 'Your phone number has been updated successfully.', 'profile', 0, '2025-10-07 16:19:44'),
(15, 6, 'Profile Updated', 'Your profile information has been updated successfully.', 'profile', 0, '2025-10-07 16:19:44'),
(16, 6, 'Facility Request Submitted', 'Your facility request has been submitted. Control Number: MCNP-20251007-4825', 'request', 0, '2025-10-07 16:34:53'),
(17, 6, 'Facility Request Submitted', 'Your facility request has been submitted. Control Number: MCNP-20251007-8984', 'request', 0, '2025-10-07 16:37:26'),
(18, 6, 'Facility Request Submitted', 'Your facility request has been submitted. Control Number: MCNP-20251007-0810', 'request', 0, '2025-10-07 20:01:17'),
(19, 6, 'Profile Updated', 'Your profile information has been updated successfully.', 'profile', 0, '2025-10-07 20:27:20'),
(20, 6, 'Facility Request Submitted', 'Your facility request has been submitted. Control Number: MCNP-20251007-6107', 'request', 0, '2025-10-07 20:39:44'),
(21, 6, 'Facility Request Submitted', 'Your facility request has been submitted. Control Number: MCNP-20251007-7086', 'request', 0, '2025-10-07 20:42:46');

-- --------------------------------------------------------

--
-- Table structure for table `request_status_history`
--

CREATE TABLE `request_status_history` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `department` varchar(255) NOT NULL,
  `program` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('Student','Faculty','Staff','Admin') NOT NULL,
  `verification_code` varchar(10) DEFAULT NULL,
  `reset_code` varchar(10) DEFAULT NULL,
  `reset_code_expires` datetime DEFAULT NULL,
  `verified` tinyint(1) DEFAULT 0,
  `approved` tinyint(1) DEFAULT 0,
  `profile_picture` varchar(255) DEFAULT NULL,
  `phone_number` varchar(50) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `department`, `program`, `password`, `user_type`, `verification_code`, `reset_code`, `reset_code_expires`, `verified`, `approved`, `profile_picture`, `phone_number`, `bio`, `created_at`, `updated_at`) VALUES
(5, 'Aiana Manalo', 'amae6554@gmail.com', 'International School of Asia and the Pacific', 'BS Information Technology', '$2y$10$ALqI.6cGfd88tpox/GDZxea5ynye1p7YZM1aytUxs9atIJlwm2iJG', 'Student', NULL, NULL, NULL, 1, 1, 'uploads/profiles/profile_5_1759518488.jpg', '+639553807986', '', '2025-10-04 03:00:05', '2025-10-03 19:08:38'),
(6, 'Ivan Manalo', 'ivan.manalo205@gmail.com', 'Medical Colleges of Northern Philippines', 'BS Nursing', '$2y$10$3KDXc9JD2u67TbaNBuiiDekLooSMg.qA8fc4dGzpuivaTwJUs6D2e', 'Student', NULL, NULL, NULL, 1, 1, 'uploads/profiles/profile_6_1759845945.png', '+6395538079855', '', '2025-10-04 03:05:41', '2025-10-07 20:27:20'),
(7, 'System Administrator', 'admin@mcnp.edu.ph', 'General Services Office', 'Property Custodian', '$2y$10$r3BpS2k1XqL9wZc8vN7mEeHjK4tY6uI0oP3aM5bN7cV1xZ8yA2', 'Admin', NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, '2025-10-04 03:14:04', '2025-10-03 19:16:01'),
(14, 'Super Admin', 'superadmin@gmail.com', 'Medical Colleges of Northern Philippines', 'BS Radiologic Technology', '$2y$10$.3FuDlkTNpoEqV0ZWeQtheYv91lfAcJgVRX7pHfPiVqDG1Wf3gFdG', 'Admin', '344257', NULL, NULL, 1, 1, NULL, NULL, NULL, '2025-10-04 03:22:48', '2025-10-03 19:22:48'),
(16, 'Ivana Grande', 'ivanbuenaventura182@gmail.com', 'International School of Asia and the Pacific', 'BS Business Administration', '$2y$10$vi/n2oHqC0vnSqchkwgOyO.TRi8aHB/IORHtiXbLOG/Mo2nMlxmlq', 'Student', NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, '2025-10-04 08:07:07', '2025-10-04 00:08:47'),
(20, 'Katy Perry', 'katymonicc@gmail.com', 'Medical Colleges of Northern Philippines', 'BS 2-year Dental Technology', '$2y$10$6nJFwGUN8y0/.ckWO9zgk.sB995U97DvqfQRjBAmSauC0AldsJNZ.', 'Student', NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, '2025-10-05 16:35:02', '2025-10-06 21:09:57');

-- --------------------------------------------------------

--
-- Table structure for table `user_preferences`
--

CREATE TABLE `user_preferences` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `theme` varchar(20) DEFAULT 'light',
  `email_notifications` tinyint(1) DEFAULT 1,
  `request_notifications` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_preferences`
--

INSERT INTO `user_preferences` (`id`, `user_id`, `theme`, `email_notifications`, `request_notifications`, `created_at`, `updated_at`) VALUES
(2, 5, 'dark', 0, 0, '2025-10-03 19:09:04', '2025-10-07 11:16:04'),
(3, 6, 'light', 0, 0, '2025-10-03 22:04:57', '2025-10-07 20:27:30');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activity_user` (`user_id`);

--
-- Indexes for table `facility_requests`
--
ALTER TABLE `facility_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `control_number` (`control_number`),
  ADD KEY `idx_request_status` (`status`),
  ADD KEY `idx_request_user` (`user_id`),
  ADD KEY `idx_request_date` (`created_at`);

--
-- Indexes for table `facility_request_details`
--
ALTER TABLE `facility_request_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_detail_request` (`request_id`),
  ADD KEY `idx_detail_date` (`date_needed`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_user` (`user_id`),
  ADD KEY `idx_notifications_read` (`is_read`);

--
-- Indexes for table `request_status_history`
--
ALTER TABLE `request_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`),
  ADD KEY `changed_by` (`changed_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_pref` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `facility_requests`
--
ALTER TABLE `facility_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `facility_request_details`
--
ALTER TABLE `facility_request_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `request_status_history`
--
ALTER TABLE `request_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `user_preferences`
--
ALTER TABLE `user_preferences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `facility_requests`
--
ALTER TABLE `facility_requests`
  ADD CONSTRAINT `facility_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `facility_request_details`
--
ALTER TABLE `facility_request_details`
  ADD CONSTRAINT `facility_request_details_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `facility_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `request_status_history`
--
ALTER TABLE `request_status_history`
  ADD CONSTRAINT `request_status_history_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `facility_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `request_status_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD CONSTRAINT `user_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
