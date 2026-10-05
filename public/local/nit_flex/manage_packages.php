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

/**
 * Admin: manage lesson (Flex) packages — the catalogue, assigning a package to a student,
 * and every student's packages (with unassign / refund to wallet).
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/nit_flex/lib.php');

use local_nit_finance\local\money;
use local_nit_flex\api\packages;
use local_nit_flex\api\purchase;
use local_nit_flex\entity\package;
use local_nit_flex\local\mlang;

admin_externalpage_setup('local_nit_flex_packages');
$context = context_system::instance();
require_capability('local/nit_flex:managepackages', $context);
local_nit_flex_require_enabled();

$s = fn(string $key, $a = null) => get_string($key, 'local_nit_flex', $a);
$tab = optional_param('tab', 'packages', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_ALPHA);
$pageurl = new moodle_url('/local/nit_flex/manage_packages.php', ['tab' => $tab]);
$PAGE->set_url($pageurl);

// Row actions on a package.
if (in_array($action, ['activate', 'deactivate', 'delete'], true) && confirm_sesskey()) {
    $id = required_param('id', PARAM_INT);
    try {
        if ($action === 'delete') {
            packages::delete_unused($id);
        } else {
            packages::set_status($id, $action === 'activate' ? package::STATUS_ACTIVE : package::STATUS_INACTIVE);
        }
    } catch (moodle_exception $e) {
        redirect($pageurl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
    redirect($pageurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Unassign a student's package.
if ($action === 'unassign' && confirm_sesskey()) {
    $refunded = purchase::unassign(required_param('purchaseid', PARAM_INT),
        (bool) optional_param('refund', 0, PARAM_BOOL), (int) $USER->id);
    redirect($pageurl, $refunded > 0 ? $s('unassignedrefund', money::format($refunded)) : $s('unassigned'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

// Create / edit form.
$editid = optional_param('id', 0, PARAM_INT);
$editing = $tab === 'packages' && $action === 'edit' && $editid;
$form = new \local_nit_flex\form\package_form(new moodle_url('/local/nit_flex/manage_packages.php'),
    ['editing' => $editing, 'title' => $editing ? $s('editpackage') : $s('newpackage')]);
if ($form->is_cancelled()) {
    redirect($pageurl);
}
if ($tab === 'packages' && ($data = $form->get_data())) {
    $record = (object) [
        'name' => mlang::join($data->name_ar, $data->name_en),
        'description' => mlang::join($data->desc_ar, $data->desc_en) ?: null,
        'flex_count' => (int) $data->flex_count,
        'price_minor' => (int) money::to_minor($data->price),
        'expiration_days' => max(0, (int) $data->expiration_days),
        'status' => $data->active ? package::STATUS_ACTIVE : package::STATUS_INACTIVE,
    ];
    if (!empty($data->id)) {
        packages::update((int) $data->id, $record);
    } else {
        packages::create($record);
    }
    redirect($pageurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}
if ($editing) {
    $pkg = package::get_record(['id' => $editid], MUST_EXIST);
    [$namear, $nameen] = mlang::split($pkg->get('name'));
    [$descar, $descen] = mlang::split($pkg->get('description'));
    $form->set_data([
        'id' => $editid, 'tab' => 'packages',
        'name_ar' => $namear, 'name_en' => $nameen, 'desc_ar' => $descar, 'desc_en' => $descen,
        'flex_count' => (int) $pkg->get('flex_count'),
        'price' => money::to_major((int) $pkg->get('price_minor')),
        'expiration_days' => (int) $pkg->get('expiration_days'),
        'active' => $pkg->get('status') === package::STATUS_ACTIVE ? 1 : 0,
    ]);
}

// Assign form.
$options = [];
foreach (packages::available() as $p) {
    $options[$p['id']] = format_string($p['name']) . ' — ' . money::format($p['price_minor']);
}
$assignform = new \local_nit_flex\form\assign_form(new moodle_url('/local/nit_flex/manage_packages.php'),
    ['packages' => $options]);
if ($tab === 'assign' && ($data = $assignform->get_data())) {
    $amount = trim((string) $data->amount) === '' ? 0 : (int) money::to_minor($data->amount);
    try {
        $result = purchase::assign((int) $USER->id, (int) $data->studentid, (int) $data->packageid, $amount,
            (string) $data->method, (string) $data->reference);
    } catch (moodle_exception $e) {
        redirect($pageurl, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
    $student = core_user::get_user((int) $data->studentid);
    redirect(new moodle_url('/local/nit_flex/manage_packages.php', ['tab' => 'students']),
        $s('assigned', (object) ['student' => fullname($student), 'flex' => $result['remaining_flex']]),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($s('managepackages'));

$tabs = [];
foreach (['packages' => 'tab_packages', 'assign' => 'assignpackage', 'students' => 'tab_students'] as $key => $label) {
    $tabs[] = new tabobject($key, new moodle_url('/local/nit_flex/manage_packages.php', ['tab' => $key]), $s($label));
}
echo $OUTPUT->tabtree($tabs, $tab);

if ($tab === 'packages') {
    $table = new html_table();
    $table->head = [$s('package'), $s('pkgflex'), $s('pkgprice'), $s('pkgdays'), get_string('status'),
        $s('buyers'), get_string('actions')];
    $table->attributes['class'] = 'generaltable';
    foreach (packages::all() as $p) {
        $buyers = $DB->count_records('nit_package_purchase', ['packageid' => $p['id']]);
        $isactive = $p['status'] === package::STATUS_ACTIVE;
        $links = [
            html_writer::link(new moodle_url($pageurl, ['action' => 'edit', 'id' => $p['id']]), get_string('edit')),
            html_writer::link(new moodle_url($pageurl, ['action' => $isactive ? 'deactivate' : 'activate',
                'id' => $p['id'], 'sesskey' => sesskey()]), $isactive ? $s('deactivate') : $s('activate')),
        ];
        if (!$buyers) {
            $links[] = html_writer::link(new moodle_url($pageurl, ['action' => 'delete', 'id' => $p['id'],
                'sesskey' => sesskey()]), get_string('delete'),
                ['onclick' => 'return confirm(' . json_encode($s('confirmdelete')) . ');']);
        }
        $table->data[] = [
            format_string($p['name']),
            $p['flex_count'],
            money::format($p['price_minor']),
            $p['expiration_days'] > 0 ? $p['expiration_days'] : $s('neverexpires'),
            $isactive ? $s('pstat_active') : $s('inactive'),
            $buyers,
            implode(' · ', $links),
        ];
    }
    if (empty($table->data)) {
        echo $OUTPUT->notification($s('nopackages'), 'info');
    } else {
        echo html_writer::table($table);
    }
    $form->display();
} else if ($tab === 'assign') {
    if (!$options) {
        echo $OUTPUT->notification($s('nopackages'), 'info');
    } else {
        echo html_writer::tag('p', $s('assignintro'));
        $assignform->display();
    }
} else {
    $sql = "SELECT pu.*, p.name AS package_name, u.firstname, u.lastname, u.email, u.middlename, u.alternatename,
                   u.firstnamephonetic, u.lastnamephonetic
              FROM {nit_package_purchase} pu
              JOIN {nit_package} p ON p.id = pu.packageid
              JOIN {user} u ON u.id = pu.userid
          ORDER BY pu.timecreated DESC";
    $service = new \local_nit_flex\service\purchase_service();
    $table = new html_table();
    $table->head = [$s('student'), $s('package'), $s('flexcolumn'), $s('paid'), get_string('status'),
        $s('expires'), get_string('actions')];
    $table->attributes['class'] = 'generaltable';
    foreach ($DB->get_records_sql($sql, [], 0, 500) as $r) {
        $status = $service->effective_status(new \local_nit_flex\entity\package_purchase(0, $r));
        $action = '';
        if (in_array($status, ['active', 'fully_used', 'expired'], true) && $r->status === 'active') {
            $action = html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false),
                    'class' => 'd-flex gap-2 align-items-center',
                    'onsubmit' => 'return confirm(' . json_encode($s('confirmunassign')) . ');'])
                . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()])
                . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'unassign'])
                . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'purchaseid', 'value' => $r->id])
                . html_writer::tag('label', html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'refund',
                    'value' => 1]) . ' ' . $s('refundunused'), ['class' => 'm-0'])
                . html_writer::tag('button', $s('unassign'), ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-danger'])
                . html_writer::end_tag('form');
        }
        $table->data[] = [
            html_writer::link(new moodle_url('/user/profile.php', ['id' => $r->userid]), fullname($r)),
            format_string($r->package_name),
            $s('flexleftshort', (object) ['remaining' => $r->remaining_flex, 'reserved' => $r->reserved_flex,
                'total' => $r->flex_count]),
            money::format((int) $r->price_paid_minor),
            $s('pstat_' . $status),
            (int) $r->expires_at > 0 ? userdate($r->expires_at, get_string('strftimedatefullshort', 'langconfig'))
                : $s('neverexpires'),
            $action,
        ];
    }
    if (empty($table->data)) {
        echo $OUTPUT->notification($s('nopurchases'), 'info');
    } else {
        echo html_writer::table($table);
    }
}

echo $OUTPUT->footer();
