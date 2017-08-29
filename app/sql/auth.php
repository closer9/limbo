<?php
/*
 * This file contains the SQL build for the authentication class. Do not edit.
 */

$sql['db_users'] = <<<DB_USERS
CREATE TABLE IF NOT EXISTS `%%?%%` (
  `authid` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `username` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `group` smallint(5) unsigned NOT NULL DEFAULT '0',
  `rank` smallint(5) unsigned NOT NULL DEFAULT '1',
  `sections` text COLLATE utf8_unicode_ci NOT NULL,
  `last_ip` varchar(15) COLLATE utf8_unicode_ci NOT NULL,
  `last_login` datetime NOT NULL,
  `last_refresh` date NOT NULL,
  `try_count` smallint(3) NOT NULL DEFAULT '0',
  `try_last` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`authid`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO `%%?%%` (`username`, `password`, `group`) VALUES ('admin', '\$2a\$12\$bLkIZA2q1hSMh1qQUkNYkeF5.GEneTgg.qfokHW1eoWSjdsnRV7JG', 0);
DB_USERS;

$sql['db_audit'] = <<<DB_AUDIT
CREATE TABLE IF NOT EXISTS `%%?%%` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `authid` smallint(6) NOT NULL,
  `datestamp` datetime NOT NULL,
  `script` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `comment` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DB_AUDIT;

$sql['db_blocked'] = <<<DB_BLOCKED
CREATE TABLE IF NOT EXISTS `%%?%%` (
  `blocked` datetime NOT NULL,
  `adminid` int(10) NOT NULL,
  `keyname` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `reason` varchar(256) COLLATE utf8_unicode_ci NOT NULL,
  KEY `key` (`keyname`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DB_BLOCKED;

$sql['db_heartbeat'] = <<<DB_HEARTBEAT
CREATE TABLE IF NOT EXISTS `%%?%%` (
  `sessionid` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `authid` smallint(5) unsigned NOT NULL,
  `stamp` int(10) unsigned NOT NULL,
  `location` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `options` text COLLATE utf8_unicode_ci NOT NULL,
  KEY `session` (`sessionid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DB_HEARTBEAT;

$sql['db_logins'] = <<<DB_LOGINS
CREATE TABLE IF NOT EXISTS `%%?%%` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `authid` smallint(5) unsigned NOT NULL DEFAULT '0',
  `group` smallint(5) unsigned NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `reason` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `username` varchar(40) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `datestamp` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `address` varchar(15) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DB_LOGINS;

$sql['db_sessions'] = <<<DB_SESSIONS
CREATE TABLE IF NOT EXISTS `%%?%%` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `authid` smallint(5) unsigned NOT NULL DEFAULT '0',
  `sessionid` varchar(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `stamp` int(10) unsigned NOT NULL DEFAULT '0',
  `ip` varchar(15) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_id` (`sessionid`),
  KEY `auth_id` (`authid`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci PACK_KEYS=0;
DB_SESSIONS;

$sql['db_settings'] = <<<DB_SETTINGS
CREATE TABLE IF NOT EXISTS `%%?%%` (
  `authid` int(10) NOT NULL,
  `template` varchar(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'default',
  `refresh` tinyint(1) NOT NULL DEFAULT '1',
  `buttons` varchar(254) COLLATE utf8_unicode_ci NOT NULL,
  KEY `authid` (`authid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DB_SETTINGS;

$sql['db_info'] = <<<DB_INFO
CREATE TABLE IF NOT EXISTS `%%?%%` (
  `authid` int(10) unsigned NOT NULL,
  `registered` date NOT NULL,
  KEY `authid` (`authid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
DB_INFO;
