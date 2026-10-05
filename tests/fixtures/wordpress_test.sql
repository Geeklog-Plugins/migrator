-- Migrator fixture: WordPress
-- Synthetic test data only.
-- Custom table prefix intentionally used to test prefix detection.

CREATE TABLE demo_users (
  ID bigint unsigned NOT NULL,
  user_login varchar(60) NOT NULL,
  user_pass varchar(255) NOT NULL,
  user_nicename varchar(50) NOT NULL,
  user_email varchar(100) NOT NULL,
  user_url varchar(100) NOT NULL,
  user_registered datetime NOT NULL,
  user_status int NOT NULL DEFAULT 0,
  display_name varchar(250) NOT NULL,
  PRIMARY KEY (ID)
);

CREATE TABLE demo_options (
  option_id bigint unsigned NOT NULL,
  option_name varchar(191) NOT NULL,
  option_value longtext NOT NULL,
  autoload varchar(20) NOT NULL DEFAULT 'yes',
  PRIMARY KEY (option_id)
);

CREATE TABLE demo_posts (
  ID bigint unsigned NOT NULL,
  post_author bigint unsigned NOT NULL DEFAULT 0,
  post_date datetime NOT NULL,
  post_content longtext NOT NULL,
  post_title text NOT NULL,
  post_excerpt text NOT NULL,
  post_status varchar(20) NOT NULL DEFAULT 'publish',
  comment_status varchar(20) NOT NULL DEFAULT 'open',
  post_name varchar(200) NOT NULL DEFAULT '',
  post_modified datetime NOT NULL,
  post_parent bigint unsigned NOT NULL DEFAULT 0,
  guid varchar(255) NOT NULL DEFAULT '',
  post_type varchar(20) NOT NULL DEFAULT 'post',
  PRIMARY KEY (ID)
);

CREATE TABLE demo_comments (
  comment_ID bigint unsigned NOT NULL,
  comment_post_ID bigint unsigned NOT NULL DEFAULT 0,
  comment_author tinytext NOT NULL,
  comment_author_email varchar(100) NOT NULL DEFAULT '',
  comment_date datetime NOT NULL,
  comment_content text NOT NULL,
  comment_approved varchar(20) NOT NULL DEFAULT '1',
  comment_type varchar(20) NOT NULL DEFAULT 'comment',
  comment_parent bigint unsigned NOT NULL DEFAULT 0,
  user_id bigint unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (comment_ID)
);

CREATE TABLE demo_terms (
  term_id bigint unsigned NOT NULL,
  name varchar(200) NOT NULL,
  slug varchar(200) NOT NULL,
  PRIMARY KEY (term_id)
);

CREATE TABLE demo_term_taxonomy (
  term_taxonomy_id bigint unsigned NOT NULL,
  term_id bigint unsigned NOT NULL,
  taxonomy varchar(32) NOT NULL,
  description longtext NOT NULL,
  parent bigint unsigned NOT NULL DEFAULT 0,
  count bigint NOT NULL DEFAULT 0,
  PRIMARY KEY (term_taxonomy_id)
);

CREATE TABLE demo_term_relationships (
  object_id bigint unsigned NOT NULL,
  term_taxonomy_id bigint unsigned NOT NULL,
  term_order int NOT NULL DEFAULT 0
);

INSERT INTO demo_users VALUES
(1,'wpadmin','$P$BTESTHASH000000000000000000000','wpadmin','wpadmin@example.test','https://wp.example.test','2022-01-01 08:00:00',0,'WordPress Admin'),
(2,'editor','$P$BTESTHASH111111111111111111111','editor','editor@example.test','','2022-01-02 09:00:00',0,'Test Editor'),
(7,'writer','$P$BTESTHASH777777777777777777777','writer','writer@example.test','','2022-02-01 10:00:00',0,'Test Writer'),
(8,'noemail','$P$BTESTHASH888888888888888888888','noemail','','','2022-03-01 11:00:00',0,'No Email User');

INSERT INTO demo_options VALUES
(1,'siteurl','https://wp.example.test','yes'),
(2,'upload_url_path','','yes'),
(3,'db_version','58975','yes');

INSERT INTO demo_terms VALUES
(10,'News','news'),
(11,'Travel','travel'),
(12,'Asia','asia'),
(20,'Migrator','migrator-tag');

INSERT INTO demo_term_taxonomy VALUES
(100,10,'category','',0,2),
(101,11,'category','',0,1),
(102,12,'category','',11,1),
(200,20,'post_tag','',0,1);

INSERT INTO demo_posts VALUES
(101,7,'2023-01-10 10:00:00','<p>WordPress intro.</p><!--more--><p>WordPress body with image <img src="https://wp.example.test/wp-content/uploads/2023/01/test.jpg" alt=""></p>','WordPress News Post','Short excerpt','publish','open','wordpress-news-post','2023-01-11 10:00:00',0,'https://wp.example.test/?p=101','post'),
(102,2,'2023-02-20 12:00:00','<p>Travel post content.</p>','Travel Post','','publish','open','travel-post','2023-02-20 12:30:00',0,'https://wp.example.test/?p=102','post'),
(201,1,'2023-03-01 09:00:00','<p>WordPress page content.</p>','About WordPress Test','','publish','closed','about-test','2023-03-01 09:30:00',0,'https://wp.example.test/about-test/','page'),
(301,7,'2023-01-10 10:05:00','','test.jpg','','inherit','open','test-jpg','2023-01-10 10:05:00',101,'https://wp.example.test/wp-content/uploads/2023/01/test.jpg','attachment'),
(401,7,'2023-04-01 08:00:00','Revision data','WordPress News Post','','inherit','closed','101-revision-v1','2023-04-01 08:00:00',101,'','revision');

INSERT INTO demo_term_relationships VALUES
(101,100,0),
(101,200,0),
(102,101,0),
(102,102,1);

INSERT INTO demo_comments VALUES
(500,101,'Anonymous Visitor','visitor@example.test','2023-01-12 12:00:00','Anonymous root comment.','1','comment',0,0),
(501,101,'Test Writer','writer@example.test','2023-01-12 13:00:00','Registered reply.','1','comment',500,7),
(502,101,'Pingback','','2023-01-12 14:00:00','Pingback content.','1','pingback',0,0),
(503,102,'Pending Visitor','pending@example.test','2023-02-21 10:00:00','Pending comment.','0','comment',0,0);
