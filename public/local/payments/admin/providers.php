<?php
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_payments_providers');

$action = optional_param('action', '', PARAM_ALPHA);
$providerid = optional_param('id', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/payments/admin/providers.php'));
$PAGE->set_title(get_string('manageproviders', 'local_payments'));

// Handle enable/disable toggle.
if ($action === 'toggle' && $providerid && confirm_sesskey()) {
    $provider = $DB->get_record('local_payments_providers', ['id' => $providerid], '*', MUST_EXIST);
    $DB->update_record('local_payments_providers', (object) [
        'id' => $providerid,
        'enabled' => $provider->enabled ? 0 : 1,
        'timemodified' => time(),
    ]);
    redirect(new moodle_url('/local/payments/admin/providers.php'));
}

// Handle priority update.
if ($action === 'moveup' && $providerid && confirm_sesskey()) {
    $provider = $DB->get_record('local_payments_providers', ['id' => $providerid], '*', MUST_EXIST);
    $new_priority = max(0, $provider->priority - 10);
    $DB->update_record('local_payments_providers', (object) [
        'id' => $providerid,
        'priority' => $new_priority,
        'timemodified' => time(),
    ]);
    redirect(new moodle_url('/local/payments/admin/providers.php'));
}

if ($action === 'movedown' && $providerid && confirm_sesskey()) {
    $provider = $DB->get_record('local_payments_providers', ['id' => $providerid], '*', MUST_EXIST);
    $DB->update_record('local_payments_providers', (object) [
        'id' => $providerid,
        'priority' => $provider->priority + 10,
        'timemodified' => time(),
    ]);
    redirect(new moodle_url('/local/payments/admin/providers.php'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manageproviders', 'local_payments'));

$providers = $DB->get_records('local_payments_providers', null, 'priority ASC');

if (empty($providers)) {
    echo $OUTPUT->notification('No payment providers registered.', 'warning');
} else {
    $table = new html_table();
    $table->head = ['Provider', 'Plugin', 'Enabled', 'Priority', 'Countries', 'Currencies', 'Actions'];
    $table->attributes['class'] = 'generaltable';

    foreach ($providers as $p) {
        $toggle_url = new moodle_url('/local/payments/admin/providers.php', [
            'action' => 'toggle', 'id' => $p->id, 'sesskey' => sesskey(),
        ]);
        $up_url = new moodle_url('/local/payments/admin/providers.php', [
            'action' => 'moveup', 'id' => $p->id, 'sesskey' => sesskey(),
        ]);
        $down_url = new moodle_url('/local/payments/admin/providers.php', [
            'action' => 'movedown', 'id' => $p->id, 'sesskey' => sesskey(),
        ]);

        $enabled_badge = $p->enabled
            ? '<span class="badge text-bg-success">Enabled</span>'
            : '<span class="badge text-bg-secondary">Disabled</span>';

        $countries = $p->supported_countries === '*' ? 'All' : $p->supported_countries;
        $currencies = $p->supported_currencies === '*' ? 'All' : $p->supported_currencies;

        $actions = html_writer::link($toggle_url, $p->enabled ? 'Disable' : 'Enable', ['class' => 'btn btn-sm btn-outline-secondary me-1']);
        $actions .= html_writer::link($up_url, '↑', ['class' => 'btn btn-sm btn-outline-secondary me-1']);
        $actions .= html_writer::link($down_url, '↓', ['class' => 'btn btn-sm btn-outline-secondary']);

        // Link to sub-plugin settings if available.
        $settings_url = new moodle_url('/admin/settings.php', ['section' => $p->plugin_name]);
        $actions .= ' ' . html_writer::link($settings_url, 'Settings', ['class' => 'btn btn-sm btn-outline-primary ms-1']);

        $table->data[] = [
            $p->display_name,
            $p->plugin_name,
            $enabled_badge,
            $p->priority,
            $countries,
            $currencies,
            $actions,
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
