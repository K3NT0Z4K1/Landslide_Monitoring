-- ============================================================
-- SlopeGuard — Database Schema
-- Import this file in phpMyAdmin or via MySQL CLI
-- ============================================================

CREATE DATABASE IF NOT EXISTS `slopeguard`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE `slopeguard`;

-- ============================================================
-- SENSOR NODES
-- ============================================================
CREATE TABLE `sensor_nodes` (
  `id`        int(11)       NOT NULL AUTO_INCREMENT,
  `node_name` varchar(50)   DEFAULT NULL,
  `latitude`  decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `location`  varchar(100)  DEFAULT NULL,
  `status`    enum('ACTIVE','OFFLINE') DEFAULT 'OFFLINE',
  `alert`     varchar(20)   DEFAULT 'SAFE',
  `last_seen` timestamp     NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `sensor_nodes`
  (`id`, `node_name`, `latitude`, `longitude`, `location`, `status`, `alert`, `last_seen`)
VALUES
  (1, 'Node 1', 8.2489000, 124.7532000, 'Lower Slope A', 'OFFLINE', 'SAFE', NULL),
  (2, 'Node 2', 8.2495000, 124.7541000, 'Lower Slope B', 'OFFLINE', 'SAFE', NULL),
  (3, 'Node 3', 8.2501000, 124.7550000, 'Lower Slope C', 'OFFLINE', 'SAFE', NULL);

-- ============================================================
-- SENSOR READINGS
-- ============================================================
CREATE TABLE `sensor_readings` (
  `id`           int(11)    NOT NULL AUTO_INCREMENT,
  `node_id`      int(11)    DEFAULT NULL,
  `temperature`  float      DEFAULT NULL,
  `humidity`     float      DEFAULT NULL,
  `soil_moisture`int(11)    DEFAULT NULL COMMENT 'percentage 0-100',
  `rainfall`     float      DEFAULT NULL COMMENT 'mm per hour',
  `status`       varchar(20)DEFAULT 'SAFE',
  `created_at`   timestamp  NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `node_id` (`node_id`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `sensor_readings_ibfk_1`
    FOREIGN KEY (`node_id`) REFERENCES `sensor_nodes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- ALERT HISTORY
-- Only WARNING and DANGER events are written here
-- ============================================================
CREATE TABLE `alert_history` (
  `id`           int(11)    NOT NULL AUTO_INCREMENT,
  `node_id`      int(11)    DEFAULT NULL,
  `soil_moisture`int(11)    DEFAULT NULL COMMENT 'percentage at time of alert',
  `rainfall`     float      DEFAULT NULL COMMENT 'mm per hour at time of alert',
  `status`       varchar(20)DEFAULT NULL COMMENT 'WARNING or DANGER only',
  `created_at`   timestamp  NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `node_id` (`node_id`),
  KEY `created_at` (`created_at`),
  KEY `status` (`status`),
  CONSTRAINT `alert_history_ibfk_1`
    FOREIGN KEY (`node_id`) REFERENCES `sensor_nodes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
