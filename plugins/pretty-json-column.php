<?php

/**
 * Pretty print JSON values in edit
 */
class AdminerPrettyJsonColumn
{
    private function _testJson($value)
    {
        if ((substr($value, 0, 1) == '{' || substr($value, 0, 1) == '[') && ($json = json_decode($value, true))) {
            return $json;
        }
        return $value;
    }

    function editInput($table, $field, $attrs, $value)
    {
        $json = $this->_testJson($value);
        if ($json !== $value) {
            $jsonText = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return <<<HTML
<textarea {$attrs} cols="50" rows="20">{$jsonText}</textarea>
HTML;
        }
        return '';
    }
}
