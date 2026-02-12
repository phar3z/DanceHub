-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 30, 2025 at 02:41 PM
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
-- Database: `dance_hub`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_settings`
--

CREATE TABLE `admin_settings` (
  `id` int(11) NOT NULL,
  `maintenance_mode` tinyint(1) DEFAULT 0,
  `registration_enabled` tinyint(1) DEFAULT 1,
  `analytics_enabled` tinyint(1) DEFAULT 1,
  `email_verification_required` tinyint(1) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_settings`
--

INSERT INTO `admin_settings` (`id`, `maintenance_mode`, `registration_enabled`, `analytics_enabled`, `email_verification_required`, `updated_at`) VALUES
(1, 0, 1, 1, 0, '2025-07-30 11:21:10');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` varchar(50) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `image` varchar(100) NOT NULL,
  `difficulty` varchar(50) NOT NULL,
  `lessons` int(11) NOT NULL,
  `rating` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `title`, `description`, `image`, `difficulty`, `lessons`, `rating`) VALUES
('african', 'African Dance', 'Ancestral energy flows through these movements. Dance is our living history.', 'african.jpg', 'Intermediate', 12, '4.7★'),
('flamenco', 'Flamenco Basics', 'Master the passionate rhythms of Spanish Flamenco', 'flamenco.jpg', 'All Levels', 10, '4.9★'),
('irish', 'Irish Basics', 'Proof that joy can be measured in taps per minute!', 'irish.jpg', 'All Levels', 10, '4.9★'),
('kathak', 'Kathak Essentials', 'Discover the storytelling art of North Indian classical dance', 'kathak.jpg', 'Beginner', 6, '4.6★'),
('salsa', 'Salsa Fundamentals', 'Learn basic steps, turns, and partner work in this vibrant Latin dance style', 'salsa.jpg', 'Beginner', 8, '4.8★');

-- --------------------------------------------------------

--
-- Table structure for table `lessons`
--

CREATE TABLE `lessons` (
  `id` int(11) NOT NULL,
  `course_id` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `cultural_notes` text DEFAULT NULL,
  `video_path` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lessons`
--

INSERT INTO `lessons` (`id`, `course_id`, `title`, `description`, `cultural_notes`, `video_path`, `created_at`) VALUES
(2, 'salsa', 'Salsa Dancing Beginner Basics Tutorial Video', 'Beginner Salsa Steps (No Partner Needed)\r\nIn this video, we will be going over all of the Basic Steps and Turns for Salsa to get your started dancing! :)', '', 'uploads/lessons/lesson_688935de7c7f7.mp4', '2025-07-29 20:58:06');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `avatar_id` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `is_admin`, `created_at`, `last_login`, `avatar_id`) VALUES
(1, 'test', 'test@gmail.com', '$2y$10$wetM1SuvIyLAV4I6Rn1rcuJpOIX.8mgzjqKq6h48w6NKyrvjnBCXe', 'user', 0, '2025-07-30 05:32:33', '2025-07-30 07:00:09', 2),
(2, 'test2', 'test2@gmail.com', '$2y$10$HtHVkBXSDN3IuA1cnD9J8eVt3sWR1uN/3m6rRDsxVgAZdl06slf3G', 'admin', 1, '2025-07-30 05:32:33', '2025-07-30 10:04:10', 1),
(3, 'Pharez', 'pharez@gmail.com', '$2y$10$orf.sr5VMKw1dN9CHXKxx.tGxZPEUmkQthmAuRQBB.6CwQzedomhC', 'user', 0, '2025-07-30 05:32:33', '2025-07-30 11:07:13', 1),
(4, 'admin', 'admin@gmail.com', '$2y$10$PVicGwyKIzcIv75UB9yay.ijIwRa4GzDQ2zTQioYV7I48SUrFXf2W', 'admin', 1, '2025-07-30 05:32:33', '2025-07-30 11:07:26', 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_courses`
--

CREATE TABLE `user_courses` (
  `id` int(11) NOT NULL,
  `user_email` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `course_id` varchar(50) NOT NULL,
  `progress` int(11) DEFAULT 0,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_courses`
--

INSERT INTO `user_courses` (`id`, `user_email`, `user_id`, `course_id`, `progress`, `enrolled_at`) VALUES
(20, 'pharez@gmail.com', 0, 'salsa', 0, '2025-07-29 20:40:15');

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

CREATE TABLE `user_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `theme` enum('light','dark','blue') DEFAULT 'light',
  `notifications_enabled` tinyint(1) DEFAULT 1,
  `email_notifications` tinyint(1) DEFAULT 1,
  `push_notifications` tinyint(1) DEFAULT 0,
  `language` varchar(10) DEFAULT 'en',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_settings`
--

INSERT INTO `user_settings` (`id`, `user_id`, `theme`, `notifications_enabled`, `email_notifications`, `push_notifications`, `language`, `created_at`, `updated_at`) VALUES
(1, 1, 'dark', 1, 1, 0, 'en', '2025-07-30 08:37:28', '2025-07-30 08:43:01'),
(2, 4, 'dark', 1, 1, 0, 'en', '2025-07-30 10:56:02', '2025-07-30 11:21:10');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_settings`
--
ALTER TABLE `admin_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lessons`
--
ALTER TABLE `lessons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_course_id` (`course_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email_unique` (`email`);

--
-- Indexes for table `user_courses`
--
ALTER TABLE `user_courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`course_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_settings`
--
ALTER TABLE `admin_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `lessons`
--
ALTER TABLE `lessons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user_courses`
--
ALTER TABLE `user_courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
