<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_nit_assistant\tools;

/**
 * Strict validation of a tool's input against its JSON schema (the subset the
 * tools use: object/string/integer/number/boolean/array, required, enum,
 * minLength/maxLength, pattern, minimum/maximum, maxItems). Unknown properties
 * are refused. Tool inputs stream from the model, so nothing runs before this.
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class schema {
    /**
     * Validate $value; returns a list of problems (empty = valid).
     *
     * @param mixed $value
     * @param array $schema
     * @param string $path
     * @return string[]
     */
    public static function errors(mixed $value, array $schema, string $path = 'input'): array {
        $type = $schema['type'] ?? null;
        switch ($type) {
            case 'object':
                if (!is_array($value) || (array_is_list($value) && $value !== [])) {
                    return ["{$path}: must be an object"];
                }
                $errors = [];
                $props = (array) ($schema['properties'] ?? []); // May be an empty stdClass (JSON {}).
                foreach ($schema['required'] ?? [] as $req) {
                    if (!array_key_exists($req, $value)) {
                        $errors[] = "{$path}.{$req}: required";
                    }
                }
                foreach ($value as $k => $v) {
                    if (!isset($props[$k])) {
                        $errors[] = "{$path}.{$k}: not allowed";
                        continue;
                    }
                    $errors = array_merge($errors, self::errors($v, $props[$k], "{$path}.{$k}"));
                }
                return $errors;
            case 'string':
                if (!is_string($value)) {
                    return ["{$path}: must be a string"];
                }
                $len = \core_text::strlen($value);
                if (isset($schema['minLength']) && $len < $schema['minLength']) {
                    return ["{$path}: too short"];
                }
                if (isset($schema['maxLength']) && $len > $schema['maxLength']) {
                    return ["{$path}: too long (max {$schema['maxLength']})"];
                }
                if (isset($schema['enum']) && !in_array($value, $schema['enum'], true)) {
                    return ["{$path}: must be one of " . implode(', ', $schema['enum'])];
                }
                if (isset($schema['pattern']) && !preg_match('/' . str_replace('/', '\/', $schema['pattern']) . '/u', $value)) {
                    return ["{$path}: invalid format"];
                }
                return [];
            case 'integer':
            case 'number':
                $ok = $type === 'integer' ? is_int($value) : (is_int($value) || is_float($value));
                if (!$ok) {
                    return [$type === 'integer' ? "{$path}: must be an integer" : "{$path}: must be a number"];
                }
                if (isset($schema['minimum']) && $value < $schema['minimum']) {
                    return ["{$path}: must be ≥ {$schema['minimum']}"];
                }
                if (isset($schema['maximum']) && $value > $schema['maximum']) {
                    return ["{$path}: must be ≤ {$schema['maximum']}"];
                }
                return [];
            case 'boolean':
                return is_bool($value) ? [] : ["{$path}: must be true or false"];
            case 'array':
                if (!is_array($value) || !array_is_list($value)) {
                    return ["{$path}: must be a list"];
                }
                if (isset($schema['maxItems']) && count($value) > $schema['maxItems']) {
                    return ["{$path}: at most {$schema['maxItems']} items"];
                }
                $errors = [];
                foreach ($value as $i => $item) {
                    $errors = array_merge($errors, self::errors($item, $schema['items'] ?? [], "{$path}[{$i}]"));
                }
                return $errors;
            default:
                return [];
        }
    }
}
