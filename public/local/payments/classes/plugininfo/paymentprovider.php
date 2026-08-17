<?php
namespace local_payments\plugininfo;

defined('MOODLE_INTERNAL') || die();

use core\plugininfo\base;

/**
 * Plugin info for the "paymentprovider" subplugin type of local_payments.
 *
 * Declaring this class lets core\plugin_manager resolve the subplugin type
 * cleanly (otherwise it falls back to the generic handler and emits a
 * developer-debug notice on every page that builds the settings navigation).
 */
class paymentprovider extends base {

    /**
     * Payment providers can be uninstalled through the plugins UI.
     *
     * @return bool
     */
    public function is_uninstall_allowed() {
        return true;
    }

    /**
     * Provider settings live on the local_payments settings pages, not here.
     *
     * @return null
     */
    public function get_settings_section_name() {
        return null;
    }
}
