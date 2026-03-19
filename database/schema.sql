-- --------------------------------------------------------
-- DATABASE: lost_found_db
-- --------------------------------------------------------

SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- TABLE: users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `user_id`      INT(11)      NOT NULL AUTO_INCREMENT,
  `full_name`    VARCHAR(100) NOT NULL,
  `email`        VARCHAR(150) NOT NULL UNIQUE,
  `password`     VARCHAR(255) NOT NULL,
  `role`         ENUM('admin','user') NOT NULL DEFAULT 'user',
  `is_verified`  TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Seed default admin account
-- Password: Admin@1234 (bcrypt hashed)
-- --------------------------------------------------------
INSERT INTO `users` (`full_name`, `email`, `password`, `role`, `is_verified`) VALUES
('Administrator', 'admin@lostandfound.com', '$2y$10$UDeOSAKr7DFNqBkVpwi7OurXWVhN0x99ZbW3MAL9RItrObBaHxyOG', 'admin', 1);

-- --------------------------------------------------------
-- TABLE: items
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `items` (
  `item_id`       INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`       INT(11)      NOT NULL,
  `item_name`     VARCHAR(100) NOT NULL,
  `category`      ENUM('Electronics','Clothing','Documents','Accessories','Other') NOT NULL DEFAULT 'Other',
  `description`   TEXT         NOT NULL,
  `location`      VARCHAR(150) NOT NULL,
  `contact`       VARCHAR(100) NOT NULL,
  `status`        ENUM('lost','found','claimed','expired') NOT NULL DEFAULT 'lost',
  `image`         VARCHAR(255)          DEFAULT NULL,
  `is_deleted`    TINYINT(1)   NOT NULL DEFAULT 0,
  `date_reported` DATE         NOT NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`item_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- TABLE: claims
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `claims` (
  `claim_id`     INT(11)   NOT NULL AUTO_INCREMENT,
  `item_id`      INT(11)   NOT NULL,
  `claimant_id`  INT(11)   NOT NULL,
  `message`      TEXT      NOT NULL,
  `status`       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_remark` TEXT               DEFAULT NULL,
  `claimed_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`claim_id`),
  FOREIGN KEY (`item_id`)     REFERENCES `items`(`item_id`)  ON DELETE CASCADE,
  FOREIGN KEY (`claimant_id`) REFERENCES `users`(`user_id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- TABLE: activity_log
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_log` (
  `log_id`     INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11)               DEFAULT NULL,
  `action`     VARCHAR(255) NOT NULL,
  `entity`     VARCHAR(100)          DEFAULT NULL,
  `entity_id`  INT(11)               DEFAULT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
