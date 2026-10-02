<?php

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
