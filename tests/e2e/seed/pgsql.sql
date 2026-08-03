-- Demo data for the end-to-end tests, applied by tests/e2e/harness/fixture.php.
--
-- The names are deliberately not in alphabetical order so that sorting by them visibly
-- reorders the rows, and deliberately not ASCII so that a broken encoding shows up as a
-- failing test instead of as a screenshot nobody looks at.
--
-- notes is written to by insert.test.php; everything else is read-only for the tests.
-- seed/mysql.sql has to keep the same tables and rows, see there. What comes after them is
-- PostgreSQL's own and only tests/e2e/cases/pgsql/ sees it.

DROP TABLE IF EXISTS notes;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
	id         serial PRIMARY KEY,
	name       text,
	email      text,
	created_at date,
	active     boolean
);

INSERT INTO users (name, email, created_at, active) VALUES
	('Diana Marsh',     'diana@example.com',   '2026-04-27', true),
	('Amélie Fontaine', 'amelie@example.com',  '2026-01-04', true),
	('Carl Jörgensen',  'carl@example.com',    '2026-03-19', false),
	('Beatriz Chávez',  'beatriz@example.com', '2026-02-11', true),
	('Eero Väisälä',    'eero@example.com',    '2026-05-30', true),
	('Felix Brown',     'felix@example.com',   '2026-06-08', false);

CREATE TABLE notes (
	id    serial PRIMARY KEY,
	title text,
	body  text
);

-- PostgreSQL only. Adminer addresses tables in a schema in this driver and in no other, which
-- is what tests/e2e/cases/pgsql/schema.test.php switches between.
DROP SCHEMA IF EXISTS analytics CASCADE;
CREATE SCHEMA analytics;

CREATE TABLE analytics.reports (
	id    serial PRIMARY KEY,
	title text
);

INSERT INTO analytics.reports (title) VALUES
	('Daily summary'),
	('Monthly summary');
