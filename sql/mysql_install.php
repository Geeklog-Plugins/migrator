<?php
// +--------------------------------------------------------------------------+
// | Migrator Plugin - Geeklog                                                |
// +--------------------------------------------------------------------------+
// | mysql_install.php                                                        |
// |                                                                          |
// | MySQL table definitions used during plugin installation.                 |
// +--------------------------------------------------------------------------+
// | Copyright (C) 2026 by the following authors:                             |
// |                                                                          |
// | ::Ben         hostellerie.org  AT gmail DOT com                          |
// +--------------------------------------------------------------------------+
// |                                                                          |
// | This program is free software; you can redistribute it and/or            |
// | modify it under the terms of the GNU General Public License              |
// | as published by the Free Software Foundation; either version 2           |
// | of the License, or (at your option) any later version.                   |
// |                                                                          |
// | This program is distributed in the hope that it will be useful,          |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of           |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the            |
// | GNU General Public License for more details.                             |
// |                                                                          |
// | You should have received a copy of the GNU General Public License        |
// | along with this program; if not, write to the Free Software Foundation,  |
// | Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.          |
// |                                                                          |
// +--------------------------------------------------------------------------+

$_SQL[] = "
CREATE TABLE {$_TABLES['migrator_jobs']} (
  job_id int unsigned NOT NULL AUTO_INCREMENT,
  source_cms varchar(32) NOT NULL DEFAULT '',
  source_version varchar(64) NOT NULL DEFAULT '',
  source_file varchar(255) NOT NULL DEFAULT '',
  source_prefix varchar(64) NOT NULL DEFAULT '',
  status varchar(32) NOT NULL DEFAULT 'new',
  table_map mediumtext NULL,
  report mediumtext NULL,
  created datetime NOT NULL,
  modified datetime NOT NULL,
  PRIMARY KEY (job_id),
  KEY migrator_jobs_status (status)
) ENGINE=InnoDB
";

$_SQL[] = "
CREATE TABLE {$_TABLES['migrator_id_map']} (
  map_id bigint unsigned NOT NULL AUTO_INCREMENT,
  job_id int unsigned NOT NULL,
  entity_type varchar(64) NOT NULL DEFAULT '',
  source_id varchar(191) NOT NULL DEFAULT '',
  target_id varchar(191) NOT NULL DEFAULT '',
  status varchar(32) NOT NULL DEFAULT '',
  PRIMARY KEY (map_id),
  UNIQUE KEY migrator_map_unique (job_id, entity_type, source_id),
  KEY migrator_map_target (entity_type, target_id)
) ENGINE=InnoDB
";

$_SQL[] = "
CREATE TABLE {$_TABLES['migrator_log']} (
  log_id bigint unsigned NOT NULL AUTO_INCREMENT,
  job_id int unsigned NOT NULL DEFAULT 0,
  level varchar(16) NOT NULL DEFAULT 'info',
  entity_type varchar(64) NOT NULL DEFAULT '',
  source_id varchar(191) NOT NULL DEFAULT '',
  message text NOT NULL,
  created datetime NOT NULL,
  PRIMARY KEY (log_id),
  KEY migrator_log_job (job_id, log_id),
  KEY migrator_log_level (level)
) ENGINE=InnoDB
";
