-- phpMyAdmin SQL Dump
-- version 4.4.3
-- http://www.phpmyadmin.net
--
-- Хост: localhost
-- Время создания: Апр 10 2016 г., 21:49
-- Версия сервера: 5.6.24
-- Версия PHP: 5.6.8

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- База данных: `eripapi`
--
CREATE DATABASE IF NOT EXISTS `eripapi` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `eripapi`;

-- --------------------------------------------------------

--
-- Структура таблицы `bills`
--

CREATE TABLE IF NOT EXISTS `bills` (
  `id` int(10) unsigned NOT NULL,
  `user` int(10) unsigned NOT NULL,
  `erip_id` int(10) unsigned NOT NULL,
  `personal_acc_num` varchar(30) NOT NULL,
  `amount` float NOT NULL,
  `period` date DEFAULT NULL,
  `currency_code` char(3) NOT NULL,
  `customer_fullname` varchar(99) DEFAULT NULL,
  `customer_address` varchar(99) DEFAULT NULL,
  `additional_info` varchar(500) NOT NULL,
  `additional_data` varchar(255) NOT NULL,
  `meters` int(10) unsigned DEFAULT NULL,
  `status` int(1) NOT NULL DEFAULT '1',
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- СВЯЗИ ТАБЛИЦЫ `bills`:
--   `user`
--       `users` -> `id`
--

-- --------------------------------------------------------

--
-- Структура таблицы `erip_requisites`
--

CREATE TABLE IF NOT EXISTS `erip_requisites` (
  `id` int(10) unsigned NOT NULL,
  `user` int(10) unsigned NOT NULL,
  `ftp_host` varchar(255) NOT NULL,
  `ftp_user` varchar(255) NOT NULL,
  `ftp_password` varchar(255) NOT NULL,
  `subcriber_code` int(8) unsigned NOT NULL,
  `unp` int(9) unsigned NOT NULL,
  `bank_code` int(3) unsigned NOT NULL,
  `bank_account` int(13) unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- СВЯЗИ ТАБЛИЦЫ `erip_requisites`:
--   `user`
--       `users` -> `id`
--

-- --------------------------------------------------------

--
-- Структура таблицы `operations_history`
--

CREATE TABLE IF NOT EXISTS `operations_history` (
  `id` int(10) unsigned NOT NULL,
  `username` varchar(64) NOT NULL,
  `operation_type` varchar(64) NOT NULL,
  `operation_desc` varchar(255) NOT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `end_status` tinyint(1) NOT NULL COMMENT 'ОК (1)/ ERR (0)',
  `additional_info` mediumtext
) ENGINE=ARCHIVE DEFAULT CHARSET=utf8;

--
-- СВЯЗИ ТАБЛИЦЫ `operations_history`:
--

-- --------------------------------------------------------

--
-- Структура таблицы `operations_types`
--

CREATE TABLE IF NOT EXISTS `operations_types` (
  `id` int(10) unsigned NOT NULL,
  `name` varchar(64) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- СВЯЗИ ТАБЛИЦЫ `operations_types`:
--

-- --------------------------------------------------------

--
-- Структура таблицы `payments`
--

CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(10) unsigned NOT NULL,
  `bill` int(10) unsigned NOT NULL,
  `amount` float NOT NULL,
  `fine_amount` float NOT NULL,
  `period` date DEFAULT NULL COMMENT 'месяц, указанный в дате',
  `currency_code` char(3) NOT NULL,
  `customer_fullname` varchar(99) DEFAULT NULL,
  `customer_address` varchar(99) DEFAULT NULL,
  `erip_op_num` int(11) unsigned NOT NULL,
  `agent_op_num` int(11) unsigned DEFAULT NULL,
  `device_id` varchar(30) NOT NULL,
  `authorization_way` varchar(10) DEFAULT NULL,
  `additional_info` varchar(255) DEFAULT NULL,
  `additional_data` varchar(500) DEFAULT NULL,
  `agent_bank_code` int(3) unsigned NOT NULL,
  `agent_acc_num` int(13) unsigned NOT NULL,
  `budget_payment_code` int(5) unsigned NOT NULL,
  `authorization_way_id` varchar(30) DEFAULT NULL,
  `device_type_code` int(2) DEFAULT NULL,
  `meters` int(10) unsigned DEFAULT NULL,
  `status` int(1) NOT NULL,
  `payment_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `transfer_timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `reversal_timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- СВЯЗИ ТАБЛИЦЫ `payments`:
--   `bill`
--       `bills` -> `id`
--

-- --------------------------------------------------------

--
-- Структура таблицы `running_operations`
--

CREATE TABLE IF NOT EXISTS `running_operations` (
  `id` int(10) unsigned NOT NULL,
  `owner` int(10) unsigned NOT NULL,
  `type` int(10) unsigned NOT NULL,
  `start_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- СВЯЗИ ТАБЛИЦЫ `running_operations`:
--   `type`
--       `operations_types` -> `id`
--   `owner`
--       `users` -> `id`
--

-- --------------------------------------------------------

--
-- Структура таблицы `runops_custom_params`
--

CREATE TABLE IF NOT EXISTS `runops_custom_params` (
  `id` int(10) unsigned NOT NULL,
  `operation` int(10) unsigned NOT NULL,
  `param_name` varchar(64) NOT NULL,
  `value` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- СВЯЗИ ТАБЛИЦЫ `runops_custom_params`:
--   `operation`
--       `running_operations` -> `id`
--

-- --------------------------------------------------------

--
-- Структура таблицы `users`
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
-- СВЯЗИ ТАБЛИЦЫ `users`:
--

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `bills`
--
ALTER TABLE `bills`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_bill_user` (`user`);

--
-- Индексы таблицы `erip_requisites`
--
ALTER TABLE `erip_requisites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_user` (`user`);

--
-- Индексы таблицы `operations_types`
--
ALTER TABLE `operations_types`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_bill` (`bill`);

--
-- Индексы таблицы `running_operations`
--
ALTER TABLE `running_operations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `owner` (`owner`),
  ADD KEY `type` (`type`);

--
-- Индексы таблицы `runops_custom_params`
--
ALTER TABLE `runops_custom_params`
  ADD PRIMARY KEY (`id`),
  ADD KEY `operation` (`operation`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `bills`
--
ALTER TABLE `bills`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT для таблицы `erip_requisites`
--
ALTER TABLE `erip_requisites`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT для таблицы `operations_types`
--
ALTER TABLE `operations_types`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT для таблицы `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT для таблицы `running_operations`
--
ALTER TABLE `running_operations`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT для таблицы `runops_custom_params`
--
ALTER TABLE `runops_custom_params`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;
--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `bills`
--
ALTER TABLE `bills`
  ADD CONSTRAINT `fk_bill_user` FOREIGN KEY (`user`) REFERENCES `users` (`id`);

--
-- Ограничения внешнего ключа таблицы `erip_requisites`
--
ALTER TABLE `erip_requisites`
  ADD CONSTRAINT `fk_user` FOREIGN KEY (`user`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_bill` FOREIGN KEY (`bill`) REFERENCES `bills` (`id`);

--
-- Ограничения внешнего ключа таблицы `running_operations`
--
ALTER TABLE `running_operations`
  ADD CONSTRAINT `fk_type` FOREIGN KEY (`type`) REFERENCES `operations_types` (`id`),
  ADD CONSTRAINT `running_operations_ibfk_1` FOREIGN KEY (`owner`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `runops_custom_params`
--
ALTER TABLE `runops_custom_params`
  ADD CONSTRAINT `fk_operation` FOREIGN KEY (`operation`) REFERENCES `running_operations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
