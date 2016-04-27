-- phpMyAdmin SQL Dump
-- version 4.4.3
-- http://www.phpmyadmin.net
--
-- Хост: localhost
-- Время создания: Апр 27 2016 г., 20:49
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
  `error_msg` varchar(500) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8;

--
-- Дамп данных таблицы `bills`
--

INSERT INTO `bills` (`id`, `user`, `erip_id`, `personal_acc_num`, `amount`, `period`, `currency_code`, `customer_fullname`, `customer_address`, `additional_info`, `additional_data`, `meters`, `status`, `error_msg`, `timestamp`) VALUES
(7, 3, 12312312, '1312sadf12', 100, NULL, '1', NULL, NULL, '', '', NULL, 1, NULL, '2016-04-24 15:45:27');

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
  `subscriber_code` int(8) unsigned NOT NULL,
  `unp` int(9) unsigned NOT NULL,
  `bank_code` int(3) unsigned NOT NULL,
  `bank_account` bigint(13) unsigned NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

--
-- Дамп данных таблицы `erip_requisites`
--

INSERT INTO `erip_requisites` (`id`, `user`, `ftp_host`, `ftp_user`, `ftp_password`, `subscriber_code`, `unp`, `bank_code`, `bank_account`) VALUES
(1, 3, '127.0.0.1', 'kiskin', '1', 12345678, 123456789, 123, 1234567890123);

-- --------------------------------------------------------

--
-- Структура таблицы `operations_history`
--

CREATE TABLE IF NOT EXISTS `operations_history` (
  `id` int(10) unsigned NOT NULL,
  `username` varchar(64) NOT NULL,
  `operation_type_name` varchar(64) NOT NULL,
  `operation_desc` varchar(255) DEFAULT NULL,
  `start_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `end_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `end_status` tinyint(1) NOT NULL COMMENT 'ОК (1)/ ERR (0)',
  `additional_info` mediumtext
) ENGINE=ARCHIVE DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Структура таблицы `operations_types`
--

CREATE TABLE IF NOT EXISTS `operations_types` (
  `id` int(10) unsigned NOT NULL,
  `name` varchar(64) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

--
-- Дамп данных таблицы `operations_types`
--

INSERT INTO `operations_types` (`id`, `name`, `description`) VALUES
(1, 'Мониторинг статуса счета', NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `payments`
--

CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(10) unsigned NOT NULL,
  `bill` int(10) unsigned DEFAULT NULL,
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
  `agent_bank_code` int(3) unsigned DEFAULT NULL,
  `agent_acc_num` int(13) unsigned DEFAULT NULL,
  `budget_payment_code` int(5) unsigned DEFAULT NULL,
  `authorization_way_id` varchar(30) DEFAULT NULL,
  `device_type_code` int(2) DEFAULT NULL,
  `meters` int(10) unsigned DEFAULT NULL,
  `status` int(1) NOT NULL,
  `payment_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `transfer_timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `reversal_timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Структура таблицы `running_operations`
--

CREATE TABLE IF NOT EXISTS `running_operations` (
  `id` int(10) unsigned NOT NULL,
  `owner` int(10) unsigned NOT NULL,
  `type` int(10) unsigned NOT NULL,
  `start_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;

--
-- Дамп данных таблицы `running_operations`
--

INSERT INTO `running_operations` (`id`, `owner`, `type`, `start_timestamp`) VALUES
(4, 3, 1, '2016-04-24 15:45:27'),
(5, 3, 1, '2016-04-24 19:46:53');

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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `name`, `password`, `secret_key`, `state`, `op_count`, `creation_datetime`) VALUES
(3, 'user', '$2y$10$rvZO4.Hli2z8PhGhiBVj2.HyqoVw3vhz2.3GBGE.CoFvswRWi8WEG', '481d9edcd29b3953a94b329b79a34eb9044ce01e42cc7010b884b7b1bdc241e6dd7881cbb0425b09c490135f7bc7dbd6af4f4b3776c238c79429e515dfb1f876', 1, 0, '2016-04-24 14:10:01');

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
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=8;
--
-- AUTO_INCREMENT для таблицы `erip_requisites`
--
ALTER TABLE `erip_requisites`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=2;
--
-- AUTO_INCREMENT для таблицы `operations_types`
--
ALTER TABLE `operations_types`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=2;
--
-- AUTO_INCREMENT для таблицы `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT для таблицы `running_operations`
--
ALTER TABLE `running_operations`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=6;
--
-- AUTO_INCREMENT для таблицы `runops_custom_params`
--
ALTER TABLE `runops_custom_params`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=4;
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
