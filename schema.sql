CREATE TABLE `files` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `date` date NOT NULL,
  `created_by` varchar(255) NOT NULL,
  `hash` varchar(255) NOT NULL,
  `path` varchar(255) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `description` text NULL,
  `page_template` varchar(255) NOT NULL DEFAULT 'default.twig'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `files` ADD INDEX (hash), ADD INDEX (filename);
