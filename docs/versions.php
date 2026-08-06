#!/usr/bin/env php
<?php
// Compile Adminer and record the sizes to versions.csv under the version in adminer/include/version.inc.php.
// The working tree is measured as it is, including the uncommitted changes, so run this before releasing the version set in the file.
// With --all, the versions missing in the CSV are compiled too. Their tags are checked out in a worktree in the temp directory so that
// the checkout running this script is not touched; the worktree is left there for the next run, delete it by 'git worktree remove --force'.
// Recorded versions are skipped so an interrupted run just continues, delete a row to measure it again.

const CSV = __DIR__ . "/versions.csv";
const VERSION_FILE = "adminer/include/version.inc.php";
const SUBMODULES = array("jush", "JsShrink", "PhpShrink");
const TIMEOUT = 60; // a compilation takes a fraction of a second, old versions can hang on the current PHP
const TIMED_OUT = 124; // the exit code used by the timeout command
// compile.php before 4.8 doesn't run on PHP 8 and the drivers of 3.x use ereg() removed in PHP 7
// the versions compiling on more of these produce the same output on all of them
const OLD_PHPS = array("C:/Program Files/PHP74/php.exe", "C:/Program Files/PHP56/php.exe");

/** Print an error and exit
* @return never
*/
function fail($message) {
	fwrite(STDERR, "$message\n");
	exit(1);
}

/** Run a command, return array(exit code, stdout, stderr) */
function run(array $command, $timeout = 0) {
	$files = array(1 => tempnam(sys_get_temp_dir(), "ver"), 2 => tempnam(sys_get_temp_dir(), "ver"));
	// output to files instead of pipes, stream_select() doesn't work on pipes on Windows so they couldn't be read with a timeout
	$process = proc_open($command, array(1 => array("file", $files[1], "w"), 2 => array("file", $files[2], "w")), $pipes);
	if (!$process) {
		fail("Failed to run: " . implode(" ", $command));
	}
	if (!$timeout) {
		$code = proc_close($process); // waits for the process, polling it would slow down the hundreds of Git commands
	} else {
		$start = time();
		for (;;) {
			$status = proc_get_status($process);
			if (!$status["running"]) {
				$code = $status["exitcode"];
				break;
			}
			if (time() - $start >= $timeout) {
				proc_terminate($process);
				$code = TIMED_OUT;
				break;
			}
			usleep(100000);
		}
		proc_close($process);
	}
	$return = array($code);
	foreach ($files as $file) {
		$return[] = file_get_contents($file);
		unlink($file);
	}
	return $return;
}

/** Run a Git command, exit if it fails */
function git(array $args) {
	list($code, $stdout, $stderr) = run(array_merge(array("git"), $args));
	if ($code) {
		fail("git " . implode(" ", $args) . " failed with code $code:\n$stdout$stderr");
	}
	return rtrim($stdout, "\n");
}

/** Get files in a directory of a tag, empty if the directory doesn't exist
* @param string $dir use "." for the whole tree
*/
function tree_files($tag, $dir) {
	$output = git(array("ls-tree", "-r", "--name-only", $tag, "--", $dir));
	return ($output != "" ? explode("\n", $output) : array());
}

/** Create externals/jsmin-php/jsmin.php which is not in the repository
* Versions 3.0.0 - 3.6.4 fall back to their own simplified JSMin when the submodule is missing, 2.x has no fallback and produces a broken file.
* Using the same minifier everywhere makes the sizes comparable, they can differ from the official releases built with the real jsmin-php.
*/
function create_jsmin() {
	$filename = "externals/jsmin-php/jsmin.php";
	if (!file_exists($filename)) {
		if (!preg_match('~\t/\*\* Simple JS minifier.*?\n\t\}\n~s', git(array("show", "v3.0.0:compile.php")), $match)) {
			fail("The JSMin fallback was not found in compile.php of 3.0.0.");
		}
		@mkdir(dirname($filename), 0777, true);
		file_put_contents($filename, "<?php\n// extracted from compile.php of 3.0.0\n$match[0]");
	}
}

/** Check out the submodule versions recorded in a tag */
function update_submodules($tag) {
	$paths = array();
	foreach (array_keys(tree_gitlinks($tag)) as $name) {
		if (in_array($name, SUBMODULES)) {
			$paths[] = "externals/$name";
		}
		// other submodules (jsmin-php, tinymce, ...) are not available, the compilation fails
	}
	if ($paths) {
		// the worktree gets its own copy of the submodules in .git/worktrees/*/modules/ so this doesn't move the submodules of the main checkout
		git(array_merge(array("submodule", "update", "--init", "--force", "--"), $paths));
	}
}

/** Get array(submodule name => commit) recorded in externals/ of a tag */
function tree_gitlinks($tag) {
	$return = array();
	foreach (explode("\n", git(array("ls-tree", $tag, "--", "externals/"))) as $line) {
		if (preg_match('~^160000 commit (\S+)\s+externals/(.+)$~', $line, $match)) {
			$return[$match[2]] = $match[1];
		}
	}
	return $return;
}

/** Compile all measured variants, return their sizes in bytes
* @param bool $only_mysql versions before 3.0.0 support only MySQL so the whole build is the MySQL build
*/
function compile_all($tag, $only_mysql, $compiler = "compile.php") {
	$return = array(compile($tag, array(), $compiler), compile($tag, array("en"), $compiler));
	$return[] = ($only_mysql ? $return[0] : compile($tag, array("mysql"), $compiler));
	return $return;
}

/** Compile one variant, return its size in bytes or "" if the compilation fails
* @param string $compiler compile.php of the measured version, it can be run from any directory and creates the file in the current one
*   versions before 1.11 use _compile.php producing phpMinAdmin[-lang].php, it must be run from the directory with the source
*/
function compile($tag, array $args, $compiler = "compile.php") {
	$binaries = array(PHP_BINARY);
	foreach (OLD_PHPS as $binary) {
		if (file_exists($binary)) {
			$binaries[] = $binary;
		}
	}
	$messages = array();
	foreach ($binaries as $binary) {
		$command = array_merge(array($binary, $compiler), $args);
		list($code, $stdout, $stderr) = run($command, TIMEOUT);
		// compile.php prints "Not found: ..." and exits with 1, PHP fatal errors exit with 255
		// the "Missing ..." warnings on stderr are harmless so the exit code decides
		// the oldest versions compile also the editor and don't report the size
		if (!$code && preg_match('~^((?:adminer|phpMinAdmin)\S*) created(?: \((\d+) B\))?\.~m', $stdout, $match)) {
			$size = (isset($match[2]) ? $match[2] : (file_exists($match[1]) ? filesize($match[1]) : ""));
			if (isset($match[2]) && file_exists($match[1]) && filesize($match[1]) != $match[2]) {
				$messages[] = "$match[1] has " . filesize($match[1]) . " B but compile.php reported $match[2] B";
			} elseif ($size != "") {
				return $size;
			} else {
				$messages[] = "$match[1] was reported as created but does not exist";
			}
		} else {
			// versions 4.9 - 4.14 need dependencies installed by Composer, they compile to export/ without them so the sizes are not comparable
			$messages[] = implode(" ", $command) . ($code == TIMED_OUT ? " timed out after " . TIMEOUT . " s" : ($code ? " failed with code $code" : " created no adminer*.php")) . ":\n$stdout$stderr";
		}
	}
	echo "$tag: " . implode("\n", $messages) . "\n";
	return "";
}

/** Get the size of the jush modules used by Adminer, they are a part of the source code
* @param array $gitlinks submodules recorded in the version, jush is not bundled before 3.0.0 where it is loaded from a CDN
*/
function jush_size(array $gitlinks, $number) {
	$return = 0;
	if (!isset($gitlinks["jush"])) {
		return $return;
	}
	// merged into default.css and dark.css, jush-dark.css appeared in jush at the same time as Adminer started using it
	$return += file_size("externals/jush/jush.css") + file_size("externals/jush/jush-dark.css");
	if (!is_dir("externals/jush/modules")) { // jush before it was split to modules
		return $return + file_size("externals/jush/jush.js");
	}
	$modules = array("jush.js", "jush-autocomplete-sql.js", "jush-json.js", "jush-mssql.js", "jush-oracle.js", "jush-pgsql.js", "jush-sql.js", "jush-sqlite.js", "jush-textarea.js", "jush-txt.js");
	if (version_compare($number, "6", "<")) { // IGDB and SimpleDB are not highlighted since 6.0.0
		$modules = array_merge($modules, array("jush-igdb.js", "jush-simpledb.js"));
	}
	foreach ($modules as $filename) {
		if ($filename == "jush-json.js" && !file_exists("externals/jush/modules/$filename")) {
			$filename = "jush-js.js"; // JSON highlighting replaced the JS one
		}
		$return += file_size("externals/jush/modules/$filename");
	}
	return $return;
}

/** Count the translations of a version, they moved from lang.inc.php to lang/ in 1.3.0 and to adminer/lang/ in 1.11.0 */
function count_langs($release) {
	foreach (array("adminer/lang/", "lang/") as $dir) {
		$return = 0;
		foreach (tree_files($release, $dir) as $filename) {
			if (preg_match('~\.inc\.php$~', $filename) && basename($filename) != "xx.inc.php") { // xx is just a template for new translations
				$return++;
			}
		}
		if ($return) {
			return $return;
		}
	}
	list($code, $stdout) = run(array("git", "show", "$release:lang.inc.php"));
	return (!$code ? preg_match_all("~^\t{1,2}'\\w+' => array\\($~m", $stdout, $match) : 0); // the languages are the top level keys of $translations
}

/** Get the size of the Adminer source code checked out in the current directory */
function source_size($release) {
	if (tree_files($release, "adminer")) {
		return dir_size("adminer");
	}
	// versions before 1.11 keep the source in the root next to the development scripts, the tests and the documentation
	return files_size(preg_grep('~^(_.*\.php|tests/.*|.*\.txt)$~', tree_files($release, "."), PREG_GREP_INVERT));
}

/** Get the total size of the files in a directory */
function dir_size($dir) {
	$return = 0;
	if (is_dir($dir)) {
		foreach (array_diff(scandir($dir), array(".", "..")) as $filename) {
			$path = "$dir/$filename";
			$return += (is_dir($path) ? dir_size($path) : filesize($path));
		}
	}
	return $return;
}

/** Get the total size of the listed files */
function files_size(array $filenames) {
	$return = 0;
	foreach ($filenames as $filename) {
		$return += file_size($filename);
	}
	return $return;
}

/** Get the size of a file, 0 if it doesn't exist */
function file_size($filename) {
	return (file_exists($filename) ? filesize($filename) : 0);
}

/** Delete the compiled files */
function clean() {
	foreach (glob("{adminer,editor,phpMinAdmin}*.php", GLOB_BRACE) as $filename) {
		unlink($filename);
	}
	// versions 4.9 - 4.14 compile to export/ or temp/, no version tracks these directories
	delete_dir("export");
	delete_dir("temp");
}

/** Delete a directory with all its contents */
function delete_dir($dir) {
	if (is_dir($dir)) {
		foreach (array_diff(scandir($dir), array(".", "..")) as $filename) {
			$path = "$dir/$filename";
			if (is_dir($path)) {
				delete_dir($path);
			} else {
				unlink($path);
			}
		}
		rmdir($dir);
	}
}

/** Get array(version => array(commit to compile, commit with the release date or the date itself)) of all releases, the newest first */
function versions() {
	// versions before 3.0.0 are not tagged, they are released by a commit which either sets the version or bumps it to the next -dev right after the release
	$releases = array();
	foreach (explode("\n", git(array("log", "--all", "--format=%H %s"))) as $line) { // --all because the worktree is left checked out at the oldest processed version
		list($commit, $subject) = array_pad(explode(" ", $line, 2), 2, "");
		if (preg_match('~^Released?(?: ([0-9.]+))?$~', $subject, $match)) {
			$named = array_pad($match, 2, ""); // the version is not always in the message
			$release = $commit;
			$version = version_at($commit);
			if ($version == "" || preg_match('~-dev$~', $version)) { // the version was bumped after the release so the released code is in the parent commit
				$release = "$commit^";
				$version = ($named[1] != "" ? $named[1] : preg_replace('~-dev$~', '', version_at($release)));
			}
			// only main, the original AdminerNeo branch releases the same versions from code needing Composer
			// versions before 1.11 have no compile.php, they are not comparable with the compiled ones
			if ($version != "" && !isset($releases["v$version"]) && is_ancestor($commit) && tree_files($release, "compile.php")) {
				$releases["v$version"] = array($release, $commit, $version);
			}
		}
	}

	$return = array();
	foreach (explode("\n", git(array("tag"))) as $tag) {
		// tags of 4.7.6 - 4.14 point outside main to the AdminerNeo code which needs Composer, main has most of the same releases rebased and numbered x.y.0
		// 4.13 has neither a release commit in main nor an entry in the changelog so it was never released
		if (is_ancestor($tag) || (!isset($releases[pad_version($tag)]) && in_changelog($tag))) {
			$return[$tag] = array($tag, $tag, substr($tag, 1));
		}
	}
	foreach ($releases as $version => $commits) {
		if (!isset($return[$version])) {
			$return[$version] = $commits;
		}
	}

	// the phpMinAdmin era releases are marked by nothing but the changelog, 1.11.0 has no release commit either
	$padded = array();
	foreach (array_keys($return) as $version) {
		$padded[pad_version($version)] = true;
	}
	foreach (changelog_releases() as $version => $date) {
		if (!isset($padded[pad_version("v$version")])) {
			$return["v$version"] = array(release_at($version, $date), $date, $version);
		}
	}

	// by the version number, the tags are prefixed by v
	uasort($return, function ($a, $b) {
		return version_compare($b[2], $a[2]);
	});
	return $return;
}

/** Complete a version to three components so that v4.9 matches v4.9.0 */
function pad_version($version) {
	return implode(".", array_pad(explode(".", $version), 3, "0"));
}

/** Check if a version has an entry in the changelog of main */
function in_changelog($version) {
	return preg_match('~^## \w+ ' . preg_quote(substr($version, 1), '~') . '(?![\d.])~m', changelog());
}

/** Get array(version => release date) of the releases dated in the changelog of main */
function changelog_releases() {
	// the entries of 4.9 - 4.15 carry no date, these versions are found by their tag or release commit
	preg_match_all('~^## \w+ ([\d.]+) \(released ([\d-]+)\)~m', changelog(), $matches, PREG_SET_ORDER);
	$return = array();
	foreach ($matches as $match) {
		$return[$match[1]] = $match[2];
	}
	return $return;
}

/** Get the content of CHANGELOG.md in main */
function changelog() {
	static $return;
	if (!isset($return)) {
		list($code, $return) = run(array("git", "show", "main:CHANGELOG.md"));
		if ($code) {
			fail("CHANGELOG.md was not found in main.");
		}
	}
	return $return;
}

/** Get the commit released as a version, the releases before 1.11.0 are marked by no commit
* @param string $date the release date from the changelog
*/
function release_at($version, $date) {
	// main because the worktree is left checked out at the oldest processed version, the old commits have the same author and committer date
	foreach (explode("\n", git(array("log", "main", "--until=$date 23:59:59", "--format=%H"))) as $commit) {
		// the date alone is not enough, 1.4.0 was bumped to 1.4.1 the same day it was released
		// the version alone is not enough either, 1.5.0 was released eight days before the bump and 1.6.0 while the code still said 1.5.1
		$number = version_at($commit);
		if ($number == "" || version_compare($number, $version, "<=")) {
			return $commit;
		}
	}
	fail("No commit of $version released on $date was found.");
}

/** Check if a commit is in the history of main */
function is_ancestor($commit) {
	list($code) = run(array("git", "merge-base", "--is-ancestor", $commit, "main"));
	return !$code;
}

/** Get the version set in a commit, empty if the version is not stored there */
function version_at($commit) {
	list($code, $stdout) = run(array("git", "show", "$commit:" . VERSION_FILE));
	if (!$code) {
		return parse_version($stdout);
	}
	// versions 1.3.2 - 1.10.1 print the version in the page title, the older ones store it nowhere
	list($code, $stdout) = run(array("git", "show", "$commit:design.inc.php"));
	return (!$code && preg_match('~lang\(\'phpMinAdmin\'\) \. " ([\d.]+)~', $stdout, $match) ? $match[1] : "");
}

/** Get the version defined in the content of version.inc.php, empty if it is not there */
function parse_version($content) {
	return (preg_match('~VERSION\s*=\s*[\'"]([^\'"]+)~', $content, $match) ? $match[1] : "");
}

/** Format a size in bytes as kB, empty if the size is not known */
function size_kb($size) {
	return ($size != "" ? number_format($size / 1000, 3, ".", "") : ""); // a byte count divided by 1000 has three decimals at most so nothing is rounded
}

/** Write a row to the CSV, the versions are sorted from the newest */
function write_row(array $row) {
	$lines = (file_exists(CSV) ? file(CSV) : array());
	$position = count($lines);
	foreach ($lines as $i => $line) {
		list($version) = explode("\t", $line);
		if ($i && version_compare(ltrim($version, "v"), ltrim($row[0], "v"), "<")) { // the first line is the header
			$position = $i;
			break;
		}
	}
	array_splice($lines, $position, 0, implode("\t", $row) . "\n");
	if (!file_put_contents(CSV, implode("", $lines))) {
		fail("Failed to write " . CSV . ".");
	}
	echo implode("\t", $row) . "\n";
}


chdir(__DIR__ . "/.."); // the repository root
$repository = getcwd();

$done = array();
if (file_exists(CSV)) {
	foreach (file(CSV, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
		$row = explode("\t", $line);
		$done[$row[0]] = true;
	}
} else {
	write_row(array("version", "date", "source", "adminer", "adminer-en", "adminer-mysql", "languages", "drivers", "plugin drivers"));
}

$number = parse_version(file_get_contents(VERSION_FILE));
if ($number == "") {
	fail("The version was not found in " . VERSION_FILE . ".");
}
if (!isset($done["v$number"])) { // the working tree including the uncommitted changes, delete its row to measure it again
	// the ignored files (adminer-plugins/, adminer.sqlite, ...) are not a part of the source code
	$source = files_size(explode("\n", git(array("ls-files", "--cached", "--others", "--exclude-standard", "--", "adminer"))))
		+ jush_size(tree_gitlinks("HEAD"), $number);
	$langs = count(preg_grep('~/xx\.inc\.php$~', glob("adminer/lang/*.inc.php"), PREG_GREP_INVERT)); // xx is just a template for new translations
	$drivers = count(glob("adminer/drivers/*.inc.php"));
	$plugin_drivers = count(glob("plugins/drivers/*.php"));

	$build = sys_get_temp_dir() . "/adminer-build"; // compiled outside the repository so that it doesn't delete the files compiled there by hand
	@mkdir($build);
	chdir($build);
	clean();
	$compiled = compile_all("v$number", false, "$repository/compile.php");
	clean();
	chdir($repository);

	// the date of the measurement, the code is not committed yet
	write_row(array_merge(array("v$number", date("Y-m-d"), size_kb($source)), array_map('size_kb', $compiled), array($langs, $drivers, $plugin_drivers)));
}

if (!in_array("--all", $argv)) { // the recorded versions never change so they are measured only on demand
	exit;
}

$worktree = sys_get_temp_dir() . "/adminer-versions";
git(array("worktree", "prune")); // the system can delete the temp directory, the stale registration would block creating the worktree again
if (!file_exists("$worktree/.git")) { // a linked worktree has a .git file pointing to the common directory
	delete_dir($worktree); // a leftover directory without the registration would block the creation too
	git(array("worktree", "add", "--detach", $worktree));
}
chdir($worktree);

foreach (versions() as $version => $commits) { // the newest first
	if (isset($done[$version])) { // this script doesn't include errors.inc.php silencing the warnings
		continue;
	}
	list($release, $dated, $number) = $commits;

	// read the counts from the commit instead of the checkout so that they are recorded even if the compilation fails
	$langs = count_langs($release);
	// versions before 3.0.0 support only MySQL - they have no drivers directory and compile.php takes no driver so the whole build is the MySQL build
	$only_mysql = version_compare($number, "3", "<");
	$drivers = ($only_mysql ? 1 : count(preg_grep('~\.inc\.php$~', tree_files($release, "adminer/drivers/"))));
	$plugin_drivers = count(preg_grep('~\.php$~', tree_files($release, "plugins/drivers/")));

	git(array("checkout", "--force", "--detach", $release)); // --force also deletes the files not present in the commit
	update_submodules($release);
	create_jsmin();
	clean();

	$source = source_size($release) + jush_size(tree_gitlinks($release), $number);
	$compiler = (tree_files($release, "compile.php") ? "compile.php" : "_compile.php"); // the compiler moved to the root in 1.11.0
	$compiled = compile_all($version, $only_mysql, $compiler);
	clean();

	// the author date, the rebased releases of 4.9 - 4.15 carry the date of the rebase as the committer date
	$date = (preg_match('~^\d{4}-\d\d-\d\d$~', $dated) ? $dated : git(array("log", "-1", "--format=%as", $dated)));
	write_row(array_merge(array($version, $date, size_kb($source)), array_map('size_kb', $compiled), array($langs, $drivers, $plugin_drivers)));
}
