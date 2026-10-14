<?php
require_once 'includes/config.php';
$db = Database::getInstance();

$sql = "
CREATE TABLE IF NOT EXISTS `class_routines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `day_of_week` varchar(20) NOT NULL COMMENT 'Sunday, Monday, etc.',
  `class_code` varchar(50) NOT NULL,
  `class_title` varchar(100) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room` varchar(50) DEFAULT NULL,
  `instructor` varchar(100) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

try {
    $db->exec($sql);
    echo "Table 'class_routines' created successfully!";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
