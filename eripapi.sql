-- phpMyAdmin SQL Dump
-- version 4.4.3
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Aug 12, 2016 at 05:31 PM
-- Server version: 5.6.24
-- PHP Version: 5.6.8

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `eripapi`
--

-- --------------------------------------------------------

--
-- Table structure for table `bills`
--

CREATE TABLE IF NOT EXISTS `bills` (
  `id` int(10) unsigned NOT NULL,
  `user` int(10) unsigned NOT NULL,
  `erip_id` int(10) unsigned NOT NULL,
  `personal_acc_num` varchar(30) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `period` char(7) DEFAULT NULL,
  `currency_code` char(3) NOT NULL,
  `customer_fullname` varchar(99) DEFAULT NULL,
  `customer_address` varchar(99) DEFAULT NULL,
  `additional_info` varchar(500) NOT NULL,
  `additional_data` varchar(255) NOT NULL,
  `meters` int(10) unsigned DEFAULT NULL,
  `status` int(1) NOT NULL DEFAULT '0' COMMENT '0 - в обработке; 1-активен, ожидает оплаты; 2-истек; 3 - ошибочен',
  `error_msg` varchar(500) DEFAULT NULL,
  `datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Triggers `bills`
--
DELIMITER $$
CREATE TRIGGER `delete_run_op_trigger` AFTER DELETE ON `bills`
 FOR EACH ROW DELETE FROM RO USING running_operations RO JOIN runops_custom_params ROP ON RO.id = ROP.operation WHERE param_name LIKE 'bill' AND value = old.id
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `erip_requisites`
--

CREATE TABLE IF NOT EXISTS `erip_requisites` (
  `id` int(10) unsigned NOT NULL,
  `user` int(10) unsigned NOT NULL,
  `ftp_host` varchar(255) NOT NULL,
  `ftp_user` varchar(255) NOT NULL,
  `ftp_password` varchar(255) NOT NULL,
  `subscriber_code` int(8) unsigned NOT NULL,
  `unp` int(9) unsigned NOT NULL,
  `bank_code` int(3) unsigned NOT NULL,
  `bank_account` decimal(13,0) unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `operations_history`
--

CREATE TABLE IF NOT EXISTS `operations_history` (
  `id` int(10) unsigned NOT NULL,
  `operation_id` int(10) unsigned NOT NULL,
  `username` varchar(64) NOT NULL,
  `operation_type_name` varchar(64) NOT NULL,
  `operation_desc` varchar(255) DEFAULT NULL,
  `start_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `end_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `end_status` tinyint(1) NOT NULL COMMENT 'ОК (1)/ ERR (0)',
  `additional_info` mediumtext
) ENGINE=ARCHIVE DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `operations_types`
--

CREATE TABLE IF NOT EXISTS `operations_types` (
  `id` int(10) unsigned NOT NULL,
  `name` varchar(64) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(10) unsigned NOT NULL,
  `user` int(10) unsigned NOT NULL,
  `bill` int(10) unsigned DEFAULT NULL,
  `erip_id` int(10) NOT NULL,
  `personal_acc_num` varchar(30) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `fine_amount` decimal(10,2) NOT NULL,
  `transfer_amount` decimal(10,2) DEFAULT NULL,
  `period` char(7) DEFAULT NULL COMMENT 'месяц, указанный в дате',
  `currency_code` char(3) NOT NULL,
  `customer_fullname` varchar(99) DEFAULT NULL,
  `customer_address` varchar(99) DEFAULT NULL,
  `erip_op_num` bigint(11) unsigned NOT NULL,
  `agent_op_num` bigint(11) unsigned DEFAULT NULL,
  `device_id` varchar(30) NOT NULL,
  `authorization_way` varchar(10) DEFAULT NULL,
  `additional_info` varchar(255) DEFAULT NULL,
  `additional_data` varchar(500) DEFAULT NULL,
  `agent_bank_code` int(3) unsigned DEFAULT NULL,
  `agent_acc_num` bigint(13) unsigned DEFAULT NULL,
  `budget_payment_code` int(5) unsigned DEFAULT NULL,
  `authorization_way_id` varchar(30) DEFAULT NULL,
  `device_type_code` int(2) DEFAULT NULL,
  `meters` int(10) unsigned DEFAULT NULL,
  `status` int(1) NOT NULL,
  `payment_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `transfer_datetime` timestamp NULL DEFAULT NULL,
  `reversal_datetime` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `running_operations`
--

CREATE TABLE IF NOT EXISTS `running_operations` (
  `id` int(10) unsigned NOT NULL,
  `owner` int(10) unsigned NOT NULL,
  `type` int(10) unsigned NOT NULL,
  `start_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `runops_custom_params`
--

CREATE TABLE IF NOT EXISTS `runops_custom_params` (
  `id` int(10) unsigned NOT NULL,
  `operation` int(10) unsigned NOT NULL,
  `param_name` varchar(64) NOT NULL,
  `value` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) unsigned NOT NULL,
  `name` varchar(32) NOT NULL,
  `password` char(72) NOT NULL,
  `secret_key` char(128) NOT NULL,
  `state` int(1) unsigned NOT NULL DEFAULT '1' COMMENT '1 - активен. 2 - неактивен. 3 - удален',
  `op_count` int(10) unsigned NOT NULL DEFAULT '0',
  `creation_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bills`
--
ALTER TABLE `bills`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_bill_user` (`user`);

--
-- Indexes for table `erip_requisites`
--
ALTER TABLE `erip_requisites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_user` (`user`);

--
-- Indexes for table `operations_history`
--
ALTER TABLE `operations_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `operations_types`
--
ALTER TABLE `operations_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ind_unique_erip_op_num` (`erip_op_num`),
  ADD KEY `fk_bill` (`bill`),
  ADD KEY `fk_payment_user` (`user`);

--
-- Indexes for table `running_operations`
--
ALTER TABLE `running_operations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `owner` (`owner`),
  ADD KEY `type` (`type`);

--
-- Indexes for table `runops_custom_params`
--
ALTER TABLE `runops_custom_params`
  ADD PRIMARY KEY (`id`),
  ADD KEY `operation` (`operation`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bills`
--
ALTER TABLE `bills`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `erip_requisites`
--
ALTER TABLE `erip_requisites`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `operations_history`
--
ALTER TABLE `operations_history`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `operations_types`
--
ALTER TABLE `operations_types`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `running_operations`
--
ALTER TABLE `running_operations`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `runops_custom_params`
--
ALTER TABLE `runops_custom_params`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;
--
-- Constraints for dumped tables
--

--
-- Constraints for table `bills`
--
ALTER TABLE `bills`
  ADD CONSTRAINT `fk_bill_user` FOREIGN KEY (`user`) REFERENCES `users` (`id`);

--
-- Constraints for table `erip_requisites`
--
ALTER TABLE `erip_requisites`
  ADD CONSTRAINT `fk_user` FOREIGN KEY (`user`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_bill` FOREIGN KEY (`bill`) REFERENCES `bills` (`id`),
  ADD CONSTRAINT `fk_payment_user` FOREIGN KEY (`user`) REFERENCES `users` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE;

--
-- Constraints for table `running_operations`
--
ALTER TABLE `running_operations`
  ADD CONSTRAINT `fk_type` FOREIGN KEY (`type`) REFERENCES `operations_types` (`id`),
  ADD CONSTRAINT `running_operations_ibfk_1` FOREIGN KEY (`owner`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `runops_custom_params`
--
ALTER TABLE `runops_custom_params`
  ADD CONSTRAINT `fk_operation` FOREIGN KEY (`operation`) REFERENCES `running_operations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
