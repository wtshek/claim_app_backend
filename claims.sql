-- phpMyAdmin SQL Dump
-- version 5.0.4
-- https://www.phpmyadmin.net/
--
-- 主机： 127.0.0.1
-- 生成日期： 2021-03-18 04:29:10
-- 服务器版本： 10.4.17-MariaDB
-- PHP 版本： 7.4.15

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 数据库： `c1force100demo`
--

-- --------------------------------------------------------

--
-- 表的结构 `claims`
--

CREATE TABLE `claims` (
  `typeOfPT` text NOT NULL,
  `fare` text NOT NULL,
  `routeIndex` int(11) NOT NULL,
  `onboardStopId` int(11) NOT NULL,
  `alightStopId` int(11) NOT NULL,
  `bounds` text NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` text NOT NULL DEFAULT '\'\\\'pending\\\'\''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
