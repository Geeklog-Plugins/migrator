-- Migrator fixture: glFusion
-- Synthetic test data only.

CREATE TABLE gf_plugins (
  pi_name varchar(30) NOT NULL,
  pi_version varchar(20) NOT NULL DEFAULT '',
  pi_enabled tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (pi_name)
);

CREATE TABLE gf_users (
  uid mediumint(8) NOT NULL,
  username varchar(16) NOT NULL,
  fullname varchar(80) DEFAULT NULL,
  passwd varchar(128) NOT NULL DEFAULT '',
  email varchar(96) DEFAULT NULL,
  homepage varchar(96) DEFAULT NULL,
  sig varchar(160) NOT NULL DEFAULT '',
  regdate datetime DEFAULT NULL,
  status smallint(5) unsigned NOT NULL DEFAULT 3,
  postmode varchar(10) NOT NULL DEFAULT 'html',
  PRIMARY KEY (uid)
);

CREATE TABLE gf_topics (
  tid varchar(75) NOT NULL,
  topic varchar(75) DEFAULT NULL,
  title varchar(128) DEFAULT NULL,
  sortnum smallint(3) DEFAULT NULL,
  owner_id mediumint(8) unsigned NOT NULL DEFAULT 2,
  group_id mediumint(8) unsigned NOT NULL DEFAULT 1,
  perm_owner tinyint(1) unsigned NOT NULL DEFAULT 3,
  perm_group tinyint(1) unsigned NOT NULL DEFAULT 2,
  perm_members tinyint(1) unsigned NOT NULL DEFAULT 2,
  perm_anon tinyint(1) unsigned NOT NULL DEFAULT 2,
  PRIMARY KEY (tid)
);

CREATE TABLE gf_stories (
  sid varchar(128) NOT NULL,
  uid mediumint(8) NOT NULL DEFAULT 2,
  tid varchar(75) NOT NULL DEFAULT '',
  draft_flag tinyint(1) unsigned NOT NULL DEFAULT 0,
  date datetime DEFAULT NULL,
  title varchar(128) DEFAULT NULL,
  introtext text,
  bodytext text,
  commentcode tinyint(4) NOT NULL DEFAULT 0,
  postmode varchar(10) NOT NULL DEFAULT 'html',
  owner_id mediumint(8) NOT NULL DEFAULT 2,
  group_id mediumint(8) NOT NULL DEFAULT 2,
  perm_owner tinyint(1) unsigned NOT NULL DEFAULT 3,
  perm_group tinyint(1) unsigned NOT NULL DEFAULT 2,
  perm_members tinyint(1) unsigned NOT NULL DEFAULT 2,
  perm_anon tinyint(1) unsigned NOT NULL DEFAULT 2,
  PRIMARY KEY (sid)
);

CREATE TABLE gf_comments (
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

CREATE TABLE gf_staticpage (
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

CREATE TABLE gf_ff_categories (
  id int(11) NOT NULL,
  cat_name varchar(255) NOT NULL DEFAULT '',
  cat_order int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
);

CREATE TABLE gf_ff_forums (
  forum_id int(11) NOT NULL,
  forum_cat int(11) NOT NULL DEFAULT 0,
  forum_name varchar(255) NOT NULL DEFAULT '',
  forum_dscp text,
  forum_order int(11) NOT NULL DEFAULT 0,
  forum_topics int(11) NOT NULL DEFAULT 0,
  forum_posts int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (forum_id)
);

CREATE TABLE gf_ff_topic (
  id int(11) NOT NULL,
  forum int(11) NOT NULL DEFAULT 0,
  uid mediumint(8) NOT NULL DEFAULT 1,
  name varchar(64) NOT NULL DEFAULT '',
  subject varchar(255) NOT NULL DEFAULT '',
  comment text,
  date bigint(20) NOT NULL DEFAULT 0,
  pid int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
);

CREATE TABLE gf_ff_userprefs (
  uid mediumint(8) NOT NULL,
  topicsperpage int(11) NOT NULL DEFAULT 10,
  postsperpage int(11) NOT NULL DEFAULT 10,
  PRIMARY KEY (uid)
);

CREATE TABLE gf_ff_attachments (
  id int(11) NOT NULL,
  topic_id int(11) NOT NULL DEFAULT 0,
  repository_id int(11) DEFAULT NULL,
  filename varchar(255) NOT NULL DEFAULT '',
  tempfile tinyint(1) NOT NULL DEFAULT 0,
  show_inline tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (id)
);

CREATE TABLE gf_filemgmt_filedetail (
  lid int(11) unsigned NOT NULL,
  cid int(5) unsigned NOT NULL DEFAULT 0,
  title varchar(100) NOT NULL DEFAULT '',
  url varchar(250) NOT NULL DEFAULT '',
  size int(8) NOT NULL DEFAULT 0,
  submitter int(11) NOT NULL DEFAULT 0,
  status tinyint(2) NOT NULL DEFAULT 1,
  date int(10) NOT NULL DEFAULT 0,
  PRIMARY KEY (lid)
);

CREATE TABLE gf_filemgmt_filedesc (
  lid int(11) unsigned NOT NULL DEFAULT 0,
  description text NOT NULL
);

CREATE TABLE gf_mg_albums (
  album_id int(11) NOT NULL,
  album_title varchar(255) NOT NULL DEFAULT '',
  album_desc text,
  album_parent int(11) NOT NULL DEFAULT 0,
  owner_id mediumint(8) NOT NULL DEFAULT 2,
  group_id mediumint(8) NOT NULL DEFAULT 1,
  perm_owner tinyint(1) NOT NULL DEFAULT 3,
  perm_group tinyint(1) NOT NULL DEFAULT 2,
  perm_members tinyint(1) NOT NULL DEFAULT 2,
  perm_anon tinyint(1) NOT NULL DEFAULT 2,
  opacity int(11) NOT NULL DEFAULT 10,
  PRIMARY KEY (album_id)
);

CREATE TABLE gf_mg_media (
  media_id varchar(40) NOT NULL,
  media_filename varchar(255) NOT NULL DEFAULT '',
  media_original_filename varchar(255) NOT NULL DEFAULT '',
  media_mime_ext varchar(255) NOT NULL DEFAULT '',
  mime_type varchar(255) NOT NULL DEFAULT '',
  media_title varchar(255) NOT NULL DEFAULT '',
  media_desc text,
  media_time int(11) NOT NULL DEFAULT 0,
  media_views int(11) NOT NULL DEFAULT 0,
  media_user_id mediumint(8) NOT NULL DEFAULT 2,
  media_approval tinyint(3) NOT NULL DEFAULT 0,
  media_type tinyint(4) NOT NULL DEFAULT 0,
  media_upload_time int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (media_id)
);

CREATE TABLE gf_mg_media_albums (
  album_id int(11) NOT NULL DEFAULT 0,
  media_id varchar(40) NOT NULL DEFAULT '',
  media_order int(11) NOT NULL DEFAULT 0
);

INSERT INTO gf_plugins VALUES
('staticpages','1.6.3',1),
('forum','2.9.0',1),
('mediagallery','1.8.0',1);

INSERT INTO gf_users VALUES
(1,'Anonymous','Anonymous','',NULL,NULL,'','2016-01-01 00:00:00',3,'html'),
(2,'Admin','glFusion Admin','21232f297a57a5a743894a0e4a801fc3','admin@example.test',NULL,'','2016-01-01 00:00:00',3,'html'),
(3,'carol','Carol glFusion','f97c5d29941bfb1b2fdab0874906ab82','carol@example.test',NULL,'','2017-05-10 09:00:00',3,'html');

INSERT INTO gf_topics VALUES
('general','General','General',10,2,1,3,2,2,2);

INSERT INTO gf_stories VALUES
('glfusion-story-1',3,'general',0,'2018-06-01 10:00:00','glFusion story','<p>glFusion intro.</p>','<p>glFusion body.</p>',0,'html',3,2,3,2,2,2);

INSERT INTO gf_comments VALUES
(20,'article','glfusion-story-1','2018-06-02 10:00:00','glFusion comment','A test comment.',0,3);

INSERT INTO gf_staticpage VALUES
('glfusion-page',3,'glFusion Page','<p>Static content from glFusion.</p>','2018-06-01 00:00:00','html',0,3,1,3,2,2,2,'html');

INSERT INTO gf_ff_categories VALUES
(1,'General Forum',10);

INSERT INTO gf_ff_forums VALUES
(1,1,'Migration Test Forum','Synthetic forum for Migrator testing.',10,1,2);

INSERT INTO gf_ff_topic VALUES
(100,1,3,'carol','Welcome to the test forum','First forum post.',1530525600,0),
(101,1,3,'carol','Re: Welcome to the test forum','Forum reply.',1530529200,100);

INSERT INTO gf_ff_userprefs VALUES
(3,20,20);

INSERT INTO gf_ff_attachments VALUES
(1,100,NULL,'forum001.pdf:legacy-attachment.pdf',0,0),
(2,101,77,'filemgmt-guide.pdf:filemgmt-guide.pdf',0,0);

INSERT INTO gf_filemgmt_filedetail VALUES
(77,1,'FileMgmt Guide','filemgmt-guide.pdf',4242,3,1,1530529200);

INSERT INTO gf_filemgmt_filedesc VALUES
(77,'Attachment stored through glFusion FileMgmt.');

INSERT INTO gf_mg_albums VALUES
(1,'Test Album','Synthetic MediaGallery album.',0,3,1,3,2,2,2,25);

INSERT INTO gf_mg_media VALUES
('media001','test-image','test-image.jpg','jpg','image/jpeg','Test Image','Synthetic MediaGallery image.',1530525600,12,3,0,0,1530525600),
('media002','test-document','test-document.pdf','pdf','application/pdf','Test Document','Synthetic MediaGallery document.',1530529200,4,3,0,4,1530529200);

INSERT INTO gf_mg_media_albums VALUES
(1,'media001',10),
(1,'media002',20);
