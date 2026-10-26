<?php

/** Allow switching designs
* @link https://www.adminer.org/plugins/#use
* @author Jakub Vrana, https://www.vrana.cz/
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
*/
class AdminerDesigns extends Adminer\Plugin {
	protected $designs;
	private $config;

	/**
	* @param list<string> $designs URL in key, name in value
	* @param bool $config choose the design on the AdminerConfig page instead of the select on every page
	*/
	function __construct(array $designs, $config = false) {
		$this->designs = $designs;
		$this->config = $config;
	}

	function afterConnect() {
		if (!$this->config && isset($_POST["design"]) && Adminer\verify_token()) {
			Adminer\restart_session();
			$_SESSION["design"] = $_POST["design"];
			Adminer\redirect(Adminer\relative_uri());
		}
	}

	function css() {
		$return = array();
		$design = ($this->config ? Adminer\get_setting("designs", "adminer_config") : $_SESSION["design"]);
		if (array_key_exists($design, $this->designs)) {
			$return[$design] = (preg_match('~-dark~', $design)
				? "dark"
				: (preg_match('~prefers-color-scheme:\s*dark~', file_get_contents($this->filename($design))) ? "" : "light")
			);
		} elseif ($this->config) {
			return null; // use adminer.css if it exists
		}
		return $return;
	}

	/** Get the file or URL to read a design linked by this URL from */
	private function filename($url) {
		if (preg_match('~^(\w+:)?//~', $url, $match)) {
			return ($match[1] ? "" : (Adminer\HTTPS ? "https:" : "http:")) . $url;
		}
		$path = preg_replace('~[?#].*~s', '', $url);
		// a relative URL is relative to the script, which needn't be in the working directory
		return ($path[0] == "/" ? $_SERVER["DOCUMENT_ROOT"] : dirname($_SERVER["SCRIPT_FILENAME"]) . "/") . $path;
	}

	function config() {
		if ($this->config) {
			return array($this->lang('Design') => Adminer\html_select("config[designs]", array("" => "") + $this->designs, Adminer\get_setting("designs", "adminer_config"))
				. " <a href='https://www.adminer.org/#extras'" . Adminer\target_blank() . ">" . $this->lang('Gallery') . "</a>"
			);
		}
	}

	function navigation($missing) {
		if (!$this->config) {
			echo "<form method='post' style='position: fixed; bottom: .5em; right: .5em;'>";
			echo Adminer\html_select("design", array("" => "(design)") + $this->designs, $_SESSION["design"], Adminer\on('change', 'formSubmit'));
			echo Adminer\input_token();
			echo "</form>\n";
		}
	}

	function screenshot() {
		return "https://www.adminer.org/static/plugins/designs.webp";
	}

	protected $translations = array(
		'cs' => array(
			'' => 'Umožní změnit vzhled',
			'Design' => 'Vzhled',
			'Gallery' => 'Galerie',
		),
		'de' => array(
			'' => 'Designwechsel ermöglichen',
			'Design' => 'Design',
			'Gallery' => 'Galerie', // Claude Opus 5.5
		),
		'hr' => array(
			'' => 'Omogućuje promjenu dizajna',
			'Design' => 'Dizajn',
			'Gallery' => 'Galerija', // Claude Opus 5.5
		),
		'ja' => array(
			'' => 'テーマ設定を有効化',
			'Design' => 'デザイン',
			'Gallery' => 'ギャラリー', // Claude Opus 5.5
		),
		'pl' => array(
			'' => 'Zezwalaj na przełączanie motywów',
			'Design' => 'Wygląd',
			'Gallery' => 'Galeria', // Claude Opus 5.5
		),
		'ro' => array(
			'' => 'Permiteți comutarea designurilor',
			'Design' => 'Design', // Claude Opus 5.5
			'Gallery' => 'Galerie', // Claude Opus 5.5
		),
		'sk' => array(
			'' => 'Umožní prepínať vzhľady', // Claude Opus 5
			'Design' => 'Vzhľad', // Claude Opus 5.5
			'Gallery' => 'Galéria', // Claude Opus 5.5
		),
		'zh' => array(
			'' => '允许切换外观', // Claude Opus 5
			'Design' => '外观', // Claude Opus 5
			'Gallery' => '图库', // Claude Opus 5.5
		),
	);
}
