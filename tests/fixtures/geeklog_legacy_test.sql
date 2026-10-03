-- Migrator fixture: legacy Geeklog
-- Synthetic test data only.

CREATE TABLE gl_plugins (
  pi_name varchar(30) NOT NULL,
  pi_version varchar(20) NOT NULL DEFAULT '',
  pi_gl_version varchar(20) NOT NULL DEFAULT '',
  pi_enabled tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (pi_name)
);

CREATE TABLE gl_users (
  uid mediumint(8) NOT NULL,
  username varchar(16) NOT NULL,
  fullname varchar(80) DEFAULT NULL,
  passwd varchar(128) NOT NULL DEFAULT '',
  salt varchar(64) NOT NULL DEFAULT '',
  algorithm tinyint(3) NOT NULL DEFAULT 0,
  stretch int(8) unsigned NOT NULL DEFAULT 1,
  email varchar(96) DEFAULT NULL,
  homepage varchar(96) DEFAULT NULL,
  sig varchar(160) NOT NULL DEFAULT '',
  regdate datetime DEFAULT NULL,
  status smallint(5) unsigned NOT NULL DEFAULT 3,
  postmode varchar(10) NOT NULL DEFAULT 'html',
  PRIMARY KEY (uid)
);

CREATE TABLE gl_userprefs (
  uid mediumint(8) NOT NULL,
  noicons tinyint(1) NOT NULL DEFAULT 0,
  willing tinyint(1) NOT NULL DEFAULT 1,
  dfid tinyint(3) NOT NULL DEFAULT 0,
  tzid varchar(125) NOT NULL DEFAULT 'UTC',
  PRIMARY KEY (uid)
);

CREATE TABLE gl_userinfo (
  uid mediumint(8) NOT NULL,
  about text,
  location varchar(96) NOT NULL DEFAULT '',
  pgpkey text,
  PRIMARY KEY (uid)
);

CREATE TABLE gl_userindex (
  uid mediumint(8) NOT NULL,
  etids text,
  noboxes tinyint(4) NOT NULL DEFAULT 0,
  maxstories tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (uid)
);

CREATE TABLE gl_usercomment (
  uid mediumint(8) NOT NULL,
  commentmode varchar(10) NOT NULL DEFAULT 'nested',
  commentorder varchar(4) NOT NULL DEFAULT 'ASC',
  commentlimit mediumint(8) unsigned NOT NULL DEFAULT 100,
  PRIMARY KEY (uid)
);

CREATE TABLE gl_topics (
  tid varchar(75) NOT NULL,
  topic varchar(75) DEFAULT NULL,
  title varchar(128) DEFAULT NULL,
  sortnum smallint(3) DEFAULT NULL,
  limitnews tinyint(3) DEFAULT NULL,
  owner_id mediumint(8) unsigned NOT NULL DEFAULT 2,
  group_id mediumint(8) unsigned NOT NULL DEFAULT 1,
  perm_owner tinyint(1) unsigned NOT NULL DEFAULT 3,
  perm_group tinyint(1) unsigned NOT NULL DEFAULT 2,
  perm_members tinyint(1) unsigned NOT NULL DEFAULT 2,
  perm_anon tinyint(1) unsigned NOT NULL DEFAULT 2,
  PRIMARY KEY (tid)
);

CREATE TABLE gl_stories (
  sid varchar(128) NOT NULL,
  uid mediumint(8) NOT NULL DEFAULT 2,
  tid varchar(75) NOT NULL DEFAULT '',
  draft_flag tinyint(1) unsigned NOT NULL DEFAULT 0,
  date datetime DEFAULT NULL,
  modified datetime DEFAULT NULL,
  title varchar(128) DEFAULT NULL,
  introtext text,
  bodytext text,
  commentcode tinyint(4) NOT NULL DEFAULT 0,
  statuscode tinyint(4) NOT NULL DEFAULT 0,
  postmode varchar(10) NOT NULL DEFAULT 'html',
  frontpage tinyint(1) unsigned NOT NULL DEFAULT 1,
  owner_id mediumint(8) NOT NULL DEFAULT 2,
  group_id mediumint(8) NOT NULL DEFAULT 2,
  perm_owner tinyint(1) unsigned NOT NULL DEFAULT 3,
  perm_group tinyint(1) unsigned NOT NULL DEFAULT 2,
  perm_members tinyint(1) unsigned NOT NULL DEFAULT 2,
  perm_anon tinyint(1) unsigned NOT NULL DEFAULT 2,
  PRIMARY KEY (sid)
);

CREATE TABLE gl_comments (
  cid int(10) unsigned NOT NULL,
  type varchar(30) NOT NULL DEFAULT 'article',
  sid varchar(128) NOT NULL,
  date datetime DEFAULT NULL,
  title varchar(128) DEFAULT NULL,
  comment text,
  pid int(10) unsigned NOT NULL DEFAULT 0,
  uid mediumint(8) NOT NULL DEFAULT 1,
  PRIMARY KEY (cid)
);

CREATE TABLE gl_staticpage (
  sp_id varchar(128) NOT NULL,
  sp_uid mediumint(8) NOT NULL DEFAULT 2,
  sp_title varchar(128) NOT NULL DEFAULT '',
  sp_content text NOT NULL,
  sp_date datetime NOT NULL,
  sp_format varchar(20) NOT NULL DEFAULT 'html',
  commentcode tinyint(4) NOT NULL DEFAULT 0,
  owner_id mediumint(8) unsigned NOT NULL DEFAULT 2,
  group_id mediumint(8) unsigned NOT NULL DEFAULT 1,
  perm_owner tinyint(1) unsigned NOT NULL DEFAULT 3,
  perm_group tinyint(1) unsigned NOT NULL DEFAULT 2,
  perm_members tinyint(1) unsigned NOT NULL DEFAULT 2,
  perm_anon tinyint(1) unsigned NOT NULL DEFAULT 2,
  postmode varchar(16) NOT NULL DEFAULT 'html',
  PRIMARY KEY (sp_id)
);

INSERT INTO gl_plugins VALUES
('staticpages','1.6.9','2.1.3',1);

INSERT INTO gl_users VALUES
(1,'Anonymous','Anonymous','','',0,1,NULL,NULL,'','2018-01-01 00:00:00',3,'html'),
(2,'Admin','Test Administrator','21232f297a57a5a743894a0e4a801fc3','',0,1,'admin@example.test',NULL,'','2018-01-01 00:00:00',3,'html'),
(3,'alice','Alice Legacy','15da1f78ad7d474862865bab1aab4d51','',0,1,'alice@example.test','https://example.test/alice','Legacy Geeklog user','2019-05-12 10:00:00',3,'html'),
(4,'bob','Bob Legacy','7c3c0d5c9d721a2a2b7c6951cdb25ac2','',0,1,'bob@example.test',NULL,'','2020-03-07 11:30:00',3,'html');

INSERT INTO gl_userprefs VALUES
(3,0,1,0,'Europe/Paris'),
(4,0,1,0,'Asia/Bangkok');

INSERT INTO gl_userinfo VALUES
(3,'Alice migrated from a legacy Geeklog fixture.','Paris',''),
(4,'Bob migrated from a legacy Geeklog fixture.','Bangkok','');

INSERT INTO gl_userindex VALUES
(3,'',0,25),
(4,'',0,20);

INSERT INTO gl_usercomment VALUES
(3,'nested','ASC',100),
(4,'nested','ASC',100);

INSERT INTO gl_topics VALUES
('news','News','News',10,10,2,1,3,2,2,2),
('travel','Travel','Travel',20,10,2,1,3,2,2,2);

INSERT INTO gl_stories VALUES
('legacy-story-1',3,'news',0,'2021-01-10 12:00:00','2021-01-11 12:00:00','Legacy story one','<p>Intro for legacy story one.</p>','<p>Body with a semicolon; inside the text.</p>',0,0,'html',1,3,2,3,2,2,2),
('legacy-story-2',4,'travel',0,'2021-02-15 08:30:00','2021-02-15 08:30:00','Legacy story two','<p>Travel intro.</p>','<p>Travel body.</p>',0,0,'html',1,4,2,3,2,2,2);

INSERT INTO gl_comments VALUES
(10,'article','legacy-story-1','2021-01-12 09:00:00','First comment','Great migration fixture.',0,4),
(11,'article','legacy-story-1','2021-01-12 10:00:00','Reply','Nested legacy reply.',10,3);

INSERT INTO gl_staticpage VALUES
('legacy-about',3,'Legacy About','<p>This is a legacy Static Page.</p>','2021-01-01 00:00:00','html',0,3,1,3,2,2,2,'html');
