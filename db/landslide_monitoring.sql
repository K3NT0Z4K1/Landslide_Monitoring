-- ============================================================
-- landslide_monitoring.sql
-- Import this in Hostinger → phpMyAdmin
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+08:00";

-- ── USERS ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(80)  NOT NULL,
  `password`   VARCHAR(255) NOT NULL COMMENT 'bcrypt hash — never store plaintext',
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin account
-- Username: admin   Password: admin123
-- !! CHANGE THIS PASSWORD IMMEDIATELY AFTER FIRST LOGIN !!
INSERT INTO `users` (`username`, `password`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- ── SENSOR NODES ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `sensor_nodes` (
  `id`        INT(11)      NOT NULL AUTO_INCREMENT,
  `node_name` VARCHAR(100) NOT NULL,
  `location`  VARCHAR(255) NOT NULL,
  `latitude`  DECIMAL(10,7) NOT NULL,
  `longitude` DECIMAL(10,7) NOT NULL,
  `status`    ENUM('ONLINE','OFFLINE') NOT NULL DEFAULT 'ONLINE',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed nodes (coordinates centred on Cagayan de Oro — adjust as needed)
INSERT INTO `sensor_nodes` (`node_name`, `location`, `latitude`, `longitude`, `status`) VALUES
('Node 1', 'Lower Slope A', 8.2495, 124.7541, 'ONLINE'),
('Node 2', 'Lower Slope B', 8.2505, 124.7555, 'ONLINE'),
('Node 3', 'Lower Slope C', 8.2480, 124.7530, 'ONLINE');

-- ── SENSOR READINGS ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `sensor_readings` (
  `id`            INT(11)        NOT NULL AUTO_INCREMENT,
  `node_id`       INT(11)        NOT NULL,
  `temperature`   DECIMAL(5,2)   NOT NULL,
  `humidity`      DECIMAL(5,2)   NOT NULL,
  `soil_moisture` DECIMAL(5,2)   NOT NULL,
  `rainfall`      DECIMAL(6,2)   NOT NULL,
  `status`        ENUM('SAFE','WARNING','DANGER') NOT NULL DEFAULT 'SAFE',
  `created_at`    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_node_time` (`node_id`, `created_at`),
  CONSTRAINT `fk_readings_node` FOREIGN KEY (`node_id`) REFERENCES `sensor_nodes`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ALERT HISTORY ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `alert_history` (
  `id`            INT(11)       NOT NULL AUTO_INCREMENT,
  `node_id`       INT(11)       NOT NULL,
  `soil_moisture` DECIMAL(5,2)  NOT NULL,
  `rainfall`      DECIMAL(6,2)  NOT NULL,
  `status`        ENUM('WARNING','DANGER') NOT NULL,
  `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_alert_node` (`node_id`),
  CONSTRAINT `fk_alert_node` FOREIGN KEY (`node_id`) REFERENCES `sensor_nodes`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
