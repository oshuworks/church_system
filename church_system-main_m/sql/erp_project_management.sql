-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 04, 2026 at 10:24 AM
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
-- Database: `erp_project_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `budgets`
--

CREATE TABLE `budgets` (
  `budget_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `estimated_cost` decimal(15,2) DEFAULT NULL,
  `actual_cost` decimal(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `budget_categories`
--

CREATE TABLE `budget_categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `parent_category_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `budget_categories`
--

INSERT INTO `budget_categories` (`category_id`, `category_name`, `description`, `parent_category_id`, `created_at`) VALUES
(1, 'Labor', 'Wages and fees', NULL, '2026-01-03 13:38:55'),
(2, 'Equipment', 'Rentals and maintenance', NULL, '2026-01-03 13:38:55'),
(3, 'Materials', 'Supplies', NULL, '2026-01-03 13:38:55'),
(4, 'General Fund', 'Overall project budget', NULL, '2026-01-03 13:38:55');

-- --------------------------------------------------------

--
-- Table structure for table `budget_expenses`
--

CREATE TABLE `budget_expenses` (
  `expense_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `budget_id` int(11) NOT NULL,
  `task_id` int(11) DEFAULT NULL,
  `expense_description` varchar(255) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `expense_date` date NOT NULL,
  `expense_type` enum('labor','equipment','materials','services','other') NOT NULL,
  `approved_by` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `budget_expenses`
--

INSERT INTO `budget_expenses` (`expense_id`, `project_id`, `budget_id`, `task_id`, `expense_description`, `amount`, `expense_date`, `expense_type`, `approved_by`, `status`, `created_at`, `updated_at`) VALUES
(6, 2, 2, NULL, '67', 67.00, '2026-01-04', 'equipment', NULL, 'pending', '2026-01-04 04:34:35', '2026-01-04 04:34:35'),
(7, 3, 3, NULL, '123', 1213.00, '2026-01-04', 'other', NULL, 'pending', '2026-01-04 04:36:22', '2026-01-04 04:36:22');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `employee_id` int(11) NOT NULL,
  `employee_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` varchar(100) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `skills` text DEFAULT NULL,
  `hourly_rate` decimal(10,2) DEFAULT 0.00,
  `max_work_hours_per_day` decimal(4,2) DEFAULT 8.00,
  `current_workload` decimal(5,2) DEFAULT 0.00,
  `utilization_percentage` decimal(5,2) DEFAULT 0.00,
  `status` enum('active','inactive','on_leave') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`employee_id`, `employee_name`, `email`, `role`, `department`, `skills`, `hourly_rate`, `max_work_hours_per_day`, `current_workload`, `utilization_percentage`, `status`, `created_at`, `updated_at`) VALUES
(1, 'John Dela Cruz', 'john@kkb.com', 'Coordinator', 'Youth', NULL, 0.00, 8.00, 0.00, 0.00, 'active', '2026-01-03 13:38:55', '2026-01-03 13:38:55'),
(2, 'Maria Santos', 'maria@kkb.com', 'Leader', 'Youth', NULL, 0.00, 8.00, 0.00, 0.00, 'active', '2026-01-03 13:38:55', '2026-01-03 13:38:55');

-- --------------------------------------------------------

--
-- Table structure for table `equipment`
--

CREATE TABLE `equipment` (
  `equipment_id` int(11) NOT NULL,
  `equipment_name` varchar(255) NOT NULL,
  `equipment_type` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `hourly_rate` decimal(10,2) DEFAULT 0.00,
  `maintenance_cost` decimal(10,2) DEFAULT 0.00,
  `total_capacity_hours` decimal(8,2) DEFAULT 0.00,
  `used_hours` decimal(8,2) DEFAULT 0.00,
  `utilization_percentage` decimal(5,2) DEFAULT 0.00,
  `status` enum('available','in_use','maintenance','reserved','retired') DEFAULT 'available',
  `location` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipment`
--

INSERT INTO `equipment` (`equipment_id`, `equipment_name`, `equipment_type`, `description`, `hourly_rate`, `maintenance_cost`, `total_capacity_hours`, `used_hours`, `utilization_percentage`, `status`, `location`, `created_at`, `updated_at`) VALUES
(1, 'Sound System Set', 'Audio', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 'available', NULL, '2026-01-03 13:38:55', '2026-01-03 13:38:55'),
(2, 'Multimedia Laptop', 'IT', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 'available', NULL, '2026-01-03 13:38:55', '2026-01-03 13:38:55'),
(3, 'Projector and Screen', 'IT', NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 'available', NULL, '2026-01-03 13:38:55', '2026-01-03 13:38:55');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `project_id` int(11) NOT NULL,
  `project_name` varchar(255) NOT NULL,
  `project_description` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('planning','active','on_hold','completed','cancelled') DEFAULT 'planning',
  `priority` enum('low','medium','high','critical') DEFAULT 'medium',
  `budget_allocated` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`project_id`, `project_name`, `project_description`, `start_date`, `end_date`, `status`, `priority`, `budget_allocated`, `created_at`, `updated_at`) VALUES
(1, 'Youth Leadership Development Program', 'A training project aimed at equipping KKB members with leadership and organizational skills for church and community service.', '2025-01-10', '2025-03-30', 'active', 'high', 50000.00, '2026-01-03 13:38:55', '2026-01-03 13:38:55'),
(2, 'Community Outreach and Evangelism Drive', 'Monthly outreach activities organized by KKB to engage youth and local residents through evangelism, feeding, and volunteer programs.', '2025-02-01', '2025-06-01', 'active', 'medium', 80000.00, '2026-01-03 13:38:55', '2026-01-03 13:38:55'),
(3, 'KKB Worship Night 2025', 'An annual event gathering youth from nearby extensions for worship, fellowship, and talent showcases.', '2025-04-15', '2025-04-20', 'planning', 'high', 60000.00, '2026-01-03 13:38:55', '2026-01-03 13:38:55'),
(4, 'KKB Digital Ministry Upgrade', 'A project to enhance KKB online presence, including website development, multimedia equipment procurement, and livestream setup.', '2025-05-01', '2025-08-30', 'planning', 'medium', 120000.00, '2026-01-03 13:38:55', '2026-01-03 13:38:55');

-- --------------------------------------------------------

--
-- Table structure for table `project_budgets`
--

CREATE TABLE `project_budgets` (
  `budget_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `allocated_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `spent_amount` decimal(15,2) DEFAULT 0.00,
  `remaining_amount` decimal(15,2) GENERATED ALWAYS AS (`allocated_amount` - `spent_amount`) STORED,
  `fiscal_year` varchar(10) DEFAULT NULL,
  `status` enum('active','frozen','closed') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_budgets`
--

INSERT INTO `project_budgets` (`budget_id`, `project_id`, `category_id`, `allocated_amount`, `spent_amount`, `fiscal_year`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 4, 50000.00, 48510.00, NULL, 'active', '2026-01-03 13:38:55', '2026-01-04 05:47:38'),
(2, 2, 4, 80000.00, 75567.00, NULL, 'active', '2026-01-03 13:38:55', '2026-01-04 04:34:35'),
(3, 3, 4, 60000.00, 62213.00, NULL, 'active', '2026-01-03 13:38:55', '2026-01-04 04:36:22'),
(4, 4, 4, 120000.00, 118525.00, NULL, 'active', '2026-01-03 13:38:55', '2026-01-04 07:11:25');

-- --------------------------------------------------------

--
-- Table structure for table `resources`
--

CREATE TABLE `resources` (
  `resource_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `resource_type` enum('Human','Equipment','Financial') NOT NULL,
  `availability` decimal(5,2) DEFAULT 100.00,
  `cost_per_unit` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `resource_alerts`
--

CREATE TABLE `resource_alerts` (
  `alert_id` int(11) NOT NULL,
  `alert_type` enum('overallocation','underutilization','conflict','capacity_warning') NOT NULL,
  `resource_type` enum('employee','equipment','budget') NOT NULL,
  `resource_id` int(11) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `task_id` int(11) DEFAULT NULL,
  `severity` enum('low','medium','high','critical') NOT NULL,
  `message` text NOT NULL,
  `threshold_value` decimal(10,2) DEFAULT NULL,
  `current_value` decimal(10,2) DEFAULT NULL,
  `status` enum('active','acknowledged','resolved') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `resource_allocations`
--

CREATE TABLE `resource_allocations` (
  `allocation_id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `resource_id` int(11) NOT NULL,
  `allocation_percent` decimal(5,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `resource_utilization`
--

CREATE TABLE `resource_utilization` (
  `utilization_id` int(11) NOT NULL,
  `resource_type` enum('employee','equipment') NOT NULL,
  `resource_id` int(11) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `date` date NOT NULL,
  `total_hours_available` decimal(8,2) NOT NULL,
  `hours_used` decimal(8,2) DEFAULT 0.00,
  `utilization_percentage` decimal(5,2) DEFAULT 0.00,
  `workload_level` enum('underutilized','optimal','overloaded','critical') GENERATED ALWAYS AS (case when `utilization_percentage` < 60 then 'underutilized' when `utilization_percentage` >= 60 and `utilization_percentage` <= 85 then 'optimal' when `utilization_percentage` > 85 and `utilization_percentage` <= 100 then 'overloaded' when `utilization_percentage` > 100 then 'critical' end) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `task_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `task_name` varchar(255) NOT NULL,
  `task_description` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `dependency_task_id` int(11) DEFAULT NULL,
  `parent_task_id` int(11) DEFAULT NULL,
  `estimated_hours` decimal(8,2) DEFAULT 0.00,
  `actual_hours` decimal(8,2) DEFAULT 0.00,
  `status` enum('not_started','in_progress','completed','blocked','cancelled') DEFAULT 'not_started',
  `priority` enum('low','medium','high','critical') DEFAULT 'medium',
  `progress_percentage` decimal(5,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`task_id`, `project_id`, `task_name`, `task_description`, `start_date`, `end_date`, `dependency_task_id`, `parent_task_id`, `estimated_hours`, `actual_hours`, `status`, `priority`, `progress_percentage`, `created_at`, `updated_at`) VALUES
(1, 1, 'Recruitment and Orientation', 'Identify and onboard new KKB youth leaders.', '2025-01-10', '2025-01-20', NULL, NULL, 0.00, 0.00, 'completed', 'high', 100.00, '2026-01-03 13:38:55', '2026-01-03 13:38:55'),
(2, 1, 'Leadership Workshops', 'Conduct training sessions on servant leadership and project management.', '2025-01-21', '2025-03-15', NULL, NULL, 0.00, 0.00, 'in_progress', 'medium', 85.00, '2026-01-03 13:38:55', '2026-01-03 13:38:55'),
(3, 2, 'Barangay Evangelism', 'Coordinate outreach activities in nearby barangays.', '2025-02-01', '2025-04-30', NULL, NULL, 0.00, 0.00, 'in_progress', 'medium', 70.00, '2026-01-03 13:38:55', '2026-01-03 13:38:55'),
(4, 2, 'Feeding Program', 'Organize feeding drives for children and families.', '2025-03-01', '2025-05-30', NULL, NULL, 0.00, 0.00, 'in_progress', 'low', 60.00, '2026-01-03 13:38:55', '2026-01-03 13:38:55'),
(5, 3, 'Event Planning', 'Plan and coordinate the worship night logistics and program flow.', '2025-04-01', '2025-04-10', NULL, NULL, 0.00, 0.00, 'completed', 'high', 100.00, '2026-01-03 13:38:55', '2026-01-03 13:38:55'),
(6, 3, 'Stage and Technical Setup', 'Set up sound, lights, and multimedia equipment.', '2025-04-11', '2025-04-14', NULL, NULL, 0.00, 0.00, 'in_progress', 'medium', 90.00, '2026-01-03 13:38:55', '2026-01-03 13:38:55'),
(7, 4, 'Website Development', 'Develop the official KKB extension website.', '2025-05-01', '2025-06-30', NULL, NULL, 0.00, 0.00, 'in_progress', 'medium', 50.00, '2026-01-03 13:38:55', '2026-01-03 13:38:55'),
(8, 4, 'Livestream Setup', 'Set up new livestream system and test with ministry events.', '2025-07-01', '2025-08-15', NULL, NULL, 0.00, 0.00, 'not_started', 'low', 40.00, '2026-01-03 13:38:55', '2026-01-03 13:38:55');

-- --------------------------------------------------------

--
-- Table structure for table `task_assignments`
--

CREATE TABLE `task_assignments` (
  `assignment_id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `assignment_type` enum('employee','equipment','budget') NOT NULL,
  `resource_id` int(11) NOT NULL,
  `allocation_percentage` decimal(5,2) DEFAULT 100.00,
  `allocated_hours` decimal(8,2) DEFAULT 0.00,
  `actual_hours_used` decimal(8,2) DEFAULT 0.00,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('assigned','in_progress','completed','cancelled') DEFAULT 'assigned',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_assignments`
--

INSERT INTO `task_assignments` (`assignment_id`, `task_id`, `project_id`, `assignment_type`, `resource_id`, `allocation_percentage`, `allocated_hours`, `actual_hours_used`, `start_date`, `end_date`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'employee', 1, 50.00, 0.00, 0.00, '2025-01-10', '2025-01-20', 'assigned', NULL, '2026-01-03 13:38:55', '2026-01-03 13:38:55'),
(2, 2, 1, 'employee', 2, 60.00, 0.00, 0.00, '2025-01-21', '2025-03-15', 'assigned', NULL, '2026-01-03 13:38:55', '2026-01-03 13:38:55'),
(3, 3, 2, 'employee', 1, 40.00, 0.00, 0.00, '2025-02-01', '2025-04-30', 'assigned', NULL, '2026-01-03 13:38:55', '2026-01-03 13:38:55'),
(4, 6, 3, 'equipment', 1, 70.00, 0.00, 0.00, '2025-04-11', '2025-04-14', 'assigned', NULL, '2026-01-03 13:38:55', '2026-01-03 13:38:55'),
(5, 7, 4, 'equipment', 2, 60.00, 0.00, 0.00, '2025-05-01', '2025-06-30', 'assigned', NULL, '2026-01-03 13:38:55', '2026-01-03 13:38:55'),
(6, 5, 3, 'employee', 1, 100.00, 10.00, 0.00, '0000-00-00', '0000-00-00', 'assigned', '', '2026-01-04 04:31:24', '2026-01-04 04:31:24'),
(7, 3, 2, 'employee', 2, 100.00, 10.00, 0.00, '0000-00-00', '0000-00-00', 'assigned', '', '2026-01-04 04:32:15', '2026-01-04 04:32:15');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `budgets`
--
ALTER TABLE `budgets`
  ADD PRIMARY KEY (`budget_id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `budget_categories`
--
ALTER TABLE `budget_categories`
  ADD PRIMARY KEY (`category_id`),
  ADD KEY `parent_category_id` (`parent_category_id`);

--
-- Indexes for table `budget_expenses`
--
ALTER TABLE `budget_expenses`
  ADD PRIMARY KEY (`expense_id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `budget_id` (`budget_id`),
  ADD KEY `task_id` (`task_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`employee_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `equipment`
--
ALTER TABLE `equipment`
  ADD PRIMARY KEY (`equipment_id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`project_id`);

--
-- Indexes for table `project_budgets`
--
ALTER TABLE `project_budgets`
  ADD PRIMARY KEY (`budget_id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `resources`
--
ALTER TABLE `resources`
  ADD PRIMARY KEY (`resource_id`);

--
-- Indexes for table `resource_alerts`
--
ALTER TABLE `resource_alerts`
  ADD PRIMARY KEY (`alert_id`),
  ADD KEY `idx_resource_status` (`resource_type`,`resource_id`,`status`),
  ADD KEY `idx_severity_status` (`severity`,`status`);

--
-- Indexes for table `resource_allocations`
--
ALTER TABLE `resource_allocations`
  ADD PRIMARY KEY (`allocation_id`),
  ADD KEY `task_id` (`task_id`),
  ADD KEY `resource_id` (`resource_id`);

--
-- Indexes for table `resource_utilization`
--
ALTER TABLE `resource_utilization`
  ADD PRIMARY KEY (`utilization_id`),
  ADD KEY `idx_resource_date` (`resource_type`,`resource_id`,`date`),
  ADD KEY `idx_project_date` (`project_id`,`date`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`task_id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `dependency_task_id` (`dependency_task_id`),
  ADD KEY `parent_task_id` (`parent_task_id`);

--
-- Indexes for table `task_assignments`
--
ALTER TABLE `task_assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `task_id` (`task_id`),
  ADD KEY `project_id` (`project_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `budgets`
--
ALTER TABLE `budgets`
  MODIFY `budget_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `budget_categories`
--
ALTER TABLE `budget_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `budget_expenses`
--
ALTER TABLE `budget_expenses`
  MODIFY `expense_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `employee_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `equipment`
--
ALTER TABLE `equipment`
  MODIFY `equipment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `project_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `project_budgets`
--
ALTER TABLE `project_budgets`
  MODIFY `budget_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `resources`
--
ALTER TABLE `resources`
  MODIFY `resource_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `resource_alerts`
--
ALTER TABLE `resource_alerts`
  MODIFY `alert_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `resource_allocations`
--
ALTER TABLE `resource_allocations`
  MODIFY `allocation_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `resource_utilization`
--
ALTER TABLE `resource_utilization`
  MODIFY `utilization_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `task_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `task_assignments`
--
ALTER TABLE `task_assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `budgets`
--
ALTER TABLE `budgets`
  ADD CONSTRAINT `budgets_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`) ON DELETE CASCADE;

--
-- Constraints for table `budget_categories`
--
ALTER TABLE `budget_categories`
  ADD CONSTRAINT `budget_categories_ibfk_1` FOREIGN KEY (`parent_category_id`) REFERENCES `budget_categories` (`category_id`);

--
-- Constraints for table `budget_expenses`
--
ALTER TABLE `budget_expenses`
  ADD CONSTRAINT `budget_expenses_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `budget_expenses_ibfk_2` FOREIGN KEY (`budget_id`) REFERENCES `project_budgets` (`budget_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `budget_expenses_ibfk_3` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`task_id`) ON DELETE SET NULL;

--
-- Constraints for table `project_budgets`
--
ALTER TABLE `project_budgets`
  ADD CONSTRAINT `project_budgets_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_budgets_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `budget_categories` (`category_id`);

--
-- Constraints for table `resource_allocations`
--
ALTER TABLE `resource_allocations`
  ADD CONSTRAINT `resource_allocations_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`task_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `resource_allocations_ibfk_2` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`resource_id`) ON DELETE CASCADE;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`dependency_task_id`) REFERENCES `tasks` (`task_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tasks_ibfk_3` FOREIGN KEY (`parent_task_id`) REFERENCES `tasks` (`task_id`) ON DELETE CASCADE;

--
-- Constraints for table `task_assignments`
--
ALTER TABLE `task_assignments`
  ADD CONSTRAINT `task_assignments_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`task_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_assignments_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
