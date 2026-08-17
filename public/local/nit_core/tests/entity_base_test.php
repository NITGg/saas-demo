<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_nit_core;

use local_nit_core\event\entity_created;
use local_nit_core\event\entity_deleted;
use local_nit_core\event\entity_updated;
use local_nit_core\fixtures\reference_entity;

require_once(__DIR__ . '/fixtures/reference_entity.php');

/**
 * Integration tests for the entity base: audit events + timestamp population.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_core\base\entity
 * @covers     \local_nit_core\traits\audits_changes
 */
final class entity_base_test extends \advanced_testcase {
    /**
     * Create the fixture table before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->create_fixture_table();
    }

    /**
     * Build the fixture table used by reference_entity.
     */
    private function create_fixture_table(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('local_nit_core_fixture');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        $dbman->create_table($table);
    }

    /**
     * Creating persists the row, populates audit columns, and fires an event.
     */
    public function test_create_populates_and_audits(): void {
        $sink = $this->redirectEvents();

        $entity = new reference_entity(0, (object) ['name' => 'alpha']);
        $entity->create();

        $this->assertNotNull($entity->get_id());
        $this->assertGreaterThan(0, $entity->get('timecreated'));
        $this->assertGreaterThan(0, $entity->get('usermodified'));

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(entity_created::class, $events[0]);
    }

    /**
     * Updating fires exactly one updated event.
     */
    public function test_update_audits(): void {
        $entity = new reference_entity(0, (object) ['name' => 'alpha']);
        $entity->create();

        $sink = $this->redirectEvents();
        $entity->set('name', 'beta');
        $entity->update();

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(entity_updated::class, $events[0]);
    }

    /**
     * Deleting fires a deleted event carrying the pre-delete id.
     */
    public function test_delete_audits_with_id(): void {
        $entity = new reference_entity(0, (object) ['name' => 'alpha']);
        $entity->create();
        $id = $entity->get_id();

        $sink = $this->redirectEvents();
        $entity->delete();

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(entity_deleted::class, $events[0]);
        $this->assertSame($id, (int) $events[0]->other['entityid']);
    }

    /**
     * audit_snapshot exposes the record as an array.
     */
    public function test_audit_snapshot(): void {
        $entity = new reference_entity(0, (object) ['name' => 'alpha']);
        $entity->create();

        $snapshot = $entity->audit_snapshot();
        $this->assertIsArray($snapshot);
        $this->assertSame('alpha', $snapshot['name']);
        $this->assertArrayHasKey('timecreated', $snapshot);
    }
}
