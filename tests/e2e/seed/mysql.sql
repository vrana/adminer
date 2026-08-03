-- Demo data for the end-to-end tests, applied by tests/e2e/harness/fixture.php.
--
-- The same tables and rows as seed/pgsql.sql - the tests in tests/e2e/ are run against every
-- driver and compare against these, so the two files have to stay in step. Only the types
-- differ, and only where they have to. What comes after them is MySQL's own and only
-- tests/e2e/cases/mysql/ sees it.

DROP TABLE IF EXISTS notes;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
	id         int AUTO_INCREMENT PRIMARY KEY,
	name       varchar(255),
	email      varchar(255),
	created_at date,
	active     tinyint(1)
);

INSERT INTO users (name, email, created_at, active) VALUES
	('Diana Marsh',     'diana@example.com',   '2026-04-27', 1),
	('Amélie Fontaine', 'amelie@example.com',  '2026-01-04', 1),
	('Carl Jörgensen',  'carl@example.com',    '2026-03-19', 0),
	('Beatriz Chávez',  'beatriz@example.com', '2026-02-11', 1),
	('Eero Väisälä',    'eero@example.com',    '2026-05-30', 1),
	('Felix Brown',     'felix@example.com',   '2026-06-08', 0);

CREATE TABLE notes (
	id    int AUTO_INCREMENT PRIMARY KEY,
	title varchar(255),
	body  text
);

-- MySQL only. enum is its type and Adminer offers the allowed values as radio buttons instead
-- of a text field, which is what tests/e2e/cases/mysql/enum.test.php checks.
DROP TABLE IF EXISTS articles;

CREATE TABLE articles (
	id     int AUTO_INCREMENT PRIMARY KEY,
	title  varchar(255),
	status enum('draft', 'published', 'archived') NOT NULL DEFAULT 'draft'
);

INSERT INTO articles (title, status) VALUES
	('Unfinished article', 'draft'),
	('Released article',   'published');
