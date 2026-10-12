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

namespace local_nit_assistant\tests;

use local_nit_assistant\llm\client;
use local_nit_assistant\llm\result;
use local_nit_assistant\llm\usage;

/**
 * A scripted model for tests (the network is the only thing mocked).
 *
 * @package    local_nit_assistant
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class fake_client implements client {
    /** @var array<int,callable|result|\Throwable> Scripted replies, in order. */
    public array $script = [];

    /** @var array[] Requests received. */
    public array $requests = [];

    /** @var callable|null Runs before each reply (e.g. to simulate a takeover mid-turn). */
    public $beforereply = null;

    /**
     * A plain text reply.
     *
     * @param string $text
     * @param string $model
     * @return result
     */
    public static function text(string $text, string $model = 'claude-sonnet-5-5'): result {
        return new result($text, [], result::END, new usage(1000, 50), $model, [['type' => 'text', 'text' => $text]]);
    }

    /**
     * A reply that calls tools.
     *
     * @param array $calls list of [name, input]
     * @param string $text text before the calls
     * @return result
     */
    public static function tools(array $calls, string $text = ''): result {
        $list = [];
        foreach ($calls as $i => [$name, $input]) {
            $list[] = ['id' => 'toolu_' . $i . '_' . random_string(6), 'name' => $name, 'input' => $input];
        }
        return new result($text, $list, result::TOOL_USE, new usage(1000, 40), 'claude-sonnet-5-5', []);
    }

    /**
     * {@inheritDoc}
     */
    public function call(array $request, ?callable $ontext = null): result {
        $this->requests[] = $request;
        if ($this->beforereply) {
            ($this->beforereply)(count($this->requests));
        }
        if (!$this->script) {
            throw new \coding_exception('fake_client: no scripted reply left');
        }
        $next = array_shift($this->script);
        if ($next instanceof \Throwable) {
            throw $next;
        }
        $res = is_callable($next) ? $next($request) : $next;
        if ($ontext && $res->text !== '') {
            $ontext($res->text);
        }
        return $res;
    }

    /**
     * {@inheritDoc}
     */
    public function estimate_input_tokens(array $request): int {
        return 1000;
    }
}
