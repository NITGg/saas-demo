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

namespace local_nit_finance\local;

/**
 * The refund pages' confirmations as Moodle modals instead of the browser's
 * confirm() / prompt() boxes.
 *
 * A form marked data-nitrefund="confirm" asks to confirm (data-title, data-body,
 * data-save); data-nitrefund="reason" asks for a required reason (data-title,
 * data-label, data-save, data-required) and puts it in the form's "reason" field.
 * Without JavaScript the form simply posts.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class refunds_ui {

    /**
     * Attributes of a form that asks to confirm before posting.
     *
     * @param string $title
     * @param string $body plain text
     * @param string $save the confirm button
     * @return array
     */
    public static function confirm_attributes(string $title, string $body, string $save): array {
        return ['data-nitrefund' => 'confirm', 'data-title' => $title, 'data-body' => $body, 'data-save' => $save];
    }

    /**
     * Attributes of a form that asks for a reason before posting.
     *
     * @param string $title
     * @param string $label above the text box
     * @param string $save the confirm button
     * @return array
     */
    public static function reason_attributes(string $title, string $label, string $save): array {
        return ['data-nitrefund' => 'reason', 'data-title' => $title, 'data-label' => $label, 'data-save' => $save,
            'data-required' => get_string('err_reasonrequired', 'local_nit_finance')];
    }

    /**
     * Load the script that turns those forms' submit into a modal.
     */
    public static function require_js(): void {
        global $PAGE;
        $PAGE->requires->js_amd_inline(<<<'JS'
require(['core/notification', 'core/modal_save_cancel', 'core/modal_events'], function(Notification, ModalSaveCancel, ModalEvents) {
    var esc = function(text) {
        var div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    };
    document.addEventListener('submit', function(e) {
        var form = e.target.closest ? e.target.closest('form[data-nitrefund]') : null;
        if (!form || form.dataset.nitrefundOk === '1') {
            return;
        }
        e.preventDefault();
        var go = function() {
            form.dataset.nitrefundOk = '1';
            form.submit();
        };
        if (form.dataset.nitrefund === 'confirm') {
            Notification.saveCancelPromise(form.dataset.title, esc(form.dataset.body), form.dataset.save)
                .then(go).catch(function() { /* Cancelled. */ });
            return;
        }
        ModalSaveCancel.create({
            title: form.dataset.title,
            body: '<label class="form-label mb-2" for="nitrefund-reason">' + esc(form.dataset.label) + '</label>'
                + '<textarea id="nitrefund-reason" class="form-control" rows="3" maxlength="500"></textarea>'
                + '<div class="text-danger small mt-1" data-region="nitrefund-error" hidden>' + esc(form.dataset.required) + '</div>',
            buttons: {save: form.dataset.save},
            removeOnClose: true,
            show: true
        }).then(function(modal) {
            var root = modal.getRoot();
            root.on(ModalEvents.shown, function() {
                root[0].querySelector('textarea').focus();
            });
            root.on(ModalEvents.save, function(ev) {
                var text = root[0].querySelector('textarea').value.trim();
                if (!text) {
                    ev.preventDefault();
                    root[0].querySelector('[data-region="nitrefund-error"]').hidden = false;
                    return;
                }
                form.querySelector('input[name="reason"]').value = text;
                go();
            });
            return modal;
        }).catch(Notification.exception);
    });
});
JS
        );
    }
}
