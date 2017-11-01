<?php
/*
 * This file contains the SQL build for the cron class. Do not edit.
 */

$build = <<<DB_CRON
CREATE TABLE IF NOT EXISTS `%%?%%` (
  `process` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `runmode` tinyint(2) unsigned NOT NULL DEFAULT '0',
  `script` varchar(256) COLLATE utf8_unicode_ci DEFAULT NULL,
  `options` varchar(128) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `schedule` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `output` varchar(256) COLLATE utf8_unicode_ci DEFAULT NULL,
  `email` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `timeout` int(8) unsigned NOT NULL DEFAULT '600',
  `lastrun` datetime NOT NULL,
  `nextrun` datetime NOT NULL,
  UNIQUE KEY `process` (`process`),
  KEY `enabled` (`enabled`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DB_CRON;
