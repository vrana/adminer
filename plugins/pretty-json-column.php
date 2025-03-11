<?php

/** Pretty print JSON values in edit
*/
class AdminerPrettyJsonColumn {
	private function testJson($value) {
		if ((substr($value, 0, 1) == '{' || substr($value, 0, 1) == '[') && ($json = json_decode($value, true))) {
			return $json;
		}
		return $value;
	}

	function editInput($table, $field, $attrs, $value) {
		$json = $this->testJson($value);
		if ($json !== $value) {
			$jsonText = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			return "<textarea$attrs cols='50' rows='20'>" . h($jsonText) . "</textarea>";
		}
		return '';
	}

	function processInput($field, $value, $function = '') {
		if ($function === '') {
			$json = $this->testJson($value);
			if ($json !== $value) {
				$value = json_encode($json);
			}
		}
	}
}
