<?php
/*
 * This file contains the SQL build for the config class. Do not edit.
 */

$sql['db_config'] = <<<DB_CONFIG
CREATE TABLE IF NOT EXISTS `%%?%%` (
  `id` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `group` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `key` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `value` text COLLATE utf8_unicode_ci NOT NULL,
  `json` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `group` (`group`),
  KEY `config` (`name`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DB_CONFIG;
