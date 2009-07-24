DROP DATABASE IF EXISTS adminer_test;
CREATE DATABASE adminer_test COLLATE utf8_czech_ci;
USE adminer_test;

CREATE TABLE interprets (
	id int NOT NULL auto_increment,
	name varchar(50) NOT NULL COMMENT 'Name',
	PRIMARY KEY (id)
) ENGINE=InnoDB COMMENT='Interprets';

CREATE TABLE albums (
	id int NOT NULL auto_increment,
	interpret int NOT NULL COMMENT 'Interpret',
	title varchar(100) NOT NULL COMMENT 'Title',
	FOREIGN KEY (interpret) REFERENCES interprets(id),
	PRIMARY KEY (id)
) ENGINE=InnoDB COMMENT='Albums';

CREATE TABLE songs (
	id int NOT NULL auto_increment,
	album int NOT NULL COMMENT 'Album',
	rank tinyint(4) NOT NULL COMMENT 'Rank',
	title varchar(100) NOT NULL COMMENT 'Title',
	duration time NOT NULL COMMENT 'Duration',
	FOREIGN KEY (album) REFERENCES albums(id),
	PRIMARY KEY (id)
) ENGINE=InnoDB COMMENT='Songs';

INSERT INTO interprets VALUES (1, 'Michael Jackson');
INSERT INTO albums VALUES (1, 1, 'Dangerous');
INSERT INTO songs VALUES (1, 1, 1, 'Jam', '00:05:39'), (2, 1, 2, 'Why You Wanna Trip On Me', '00:05:24'), (3, 1, 3, 'In the Closet', '00:06:31'), (4, 1, 4, 'She Drives Me Wild', '00:03:41'), (5, 1, 5, 'Remember the Time', '00:04:00'), (6, 1, 6, 'Can\'t Let Her Get Away', '00:04:58'), (7, 1, 7, 'Heal the World', '00:06:24'), (8, 1, 8, 'Black or White', '00:04:15'), (9, 1, 9, 'Who Is It', '00:06:34'), (10, 1, 10, 'Give In To Me', '00:05:29'), (11, 1, 11, 'Will You Be There', '00:07:40'), (12, 1, 12, 'Keep the Faith', '00:05:57'), (13, 1, 13, 'Gone Too Soon', '00:03:23'), (14, 1, 14, 'Dangerous', '00:06:57');
