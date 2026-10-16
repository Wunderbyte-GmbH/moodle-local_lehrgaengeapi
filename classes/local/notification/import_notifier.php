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
 * Notifies the configured users about the result of an automatic import.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\notification;

/**
 * Notifies the configured users about the result of an automatic import.
 * @package local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class import_notifier {
    /**
     * Notify all users selected in the notifyusers setting.
     *
     * A failing notification must never break or re-trigger the import itself,
     * so errors are only logged.
     *
     * @param string $status import_run_repository::STATUS_SUCCESS or STATUS_ERROR.
     * @return void
     */
    public static function notify_configured_users(string $status): void {
        global $DB;

        $ids = array_unique(array_filter(array_map(
            'intval',
            explode(',', (string)get_config('local_lehrgaengeapi', 'notifyusers'))
        )));
        if (!$ids) {
            return;
        }

        // Any Moodle user may be selected; deleted and suspended accounts are skipped.
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $recipients = $DB->get_records_select('user', "id $insql AND deleted = 0 AND suspended = 0", $params);

        foreach ($recipients as $userto) {
            try {
                self::send($userto, $status);
            } catch (\Throwable $e) {
                mtrace('local_lehrgaengeapi: could not notify user ' . $userto->id . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Send one notification.
     *
     * @param \stdClass $userto Recipient.
     * @param string $status import_run_repository::STATUS_SUCCESS or STATUS_ERROR.
     * @return void
     */
    private static function send(\stdClass $userto, string $status): void {
        $message = new \core\message\message();
        $message->component = 'local_lehrgaengeapi';
        $message->name = 'importcomplete';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $userto;
        $message->subject = get_string('autoimport' . $status . 'subject', 'local_lehrgaengeapi');
        $body = get_string('autoimport' . $status . 'body', 'local_lehrgaengeapi');
        $message->fullmessage = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = nl2br(s($body));
        $message->smallmessage = $body;
        $message->notification = 1;
        $message->contexturl = (new \moodle_url('/local/lehrgaengeapi/admin/import_runs.php'))->out(false);
        $message->contexturlname = get_string('importrunspagename', 'local_lehrgaengeapi');

        message_send($message);
    }
}
