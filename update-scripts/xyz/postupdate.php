<?php

$db = \Pimcore\Db::get();

$db->query('
CREATE TABLE `importconfigs` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`ownerId` INT(11) NULL,
	`classId` INT(11) NULL,
	`name` VARCHAR(50) NULL,
	`config` LONGTEXT NULL,
    `description` LONGTEXT NULL,	
	`creationDate` INT(11) NULL,
	`modificationDate` INT(11) NULL,
	PRIMARY KEY (`id`),
	INDEX `ownerId` (`ownerId`),
	INDEX `classId` (`classId`)
)
DEFAULT CHARSET=utf8mb4;
;
');
