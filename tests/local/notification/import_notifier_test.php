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
 * Tests for import_notifier.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\notification;

use local_lehrgaengeapi\local\repository\import_run_repository;

/**
 * Tests for import_notifier.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class import_notifier_test extends \advanced_testcase {
    /**
     * Every configured user gets exactly one success notification.
     *
     * @covers \local_lehrgaengeapi\local\notification\import_notifier::notify_configured_users
     */
    public function test_success_notifies_every_configured_user(): void {
        $this->resetAfterTest(true);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user(); // Not selected.
        set_config('notifyusers', $user1->id . ',' . $user2->id, 'local_lehrgaengeapi');
        $sink = $this->redirectMessages();

        import_notifier::notify_configured_users(import_run_repository::STATUS_SUCCESS);

        $messages = $sink->get_messages_by_component_and_type('local_lehrgaengeapi', 'importcomplete');
        $this->assertCount(2, $messages);
        $recipients = array_map(static fn($m) => (int)$m->useridto, $messages);
        $this->assertEqualsCanonicalizing([(int)$user1->id, (int)$user2->id], $recipients);
        $this->assertNotContains((int)$other->id, $recipients);
        foreach ($messages as $message) {
            $this->assertSame(get_string('autoimportsuccesssubject', 'local_lehrgaengeapi'), $message->subject);
        }
    }

    /**
     * The error status uses the error texts.
     *
     * @covers \local_lehrgaengeapi\local\notification\import_notifier::notify_configured_users
     */
    public function test_error_uses_error_subject(): void {
        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();
        set_config('notifyusers', (string)$user->id, 'local_lehrgaengeapi');
        $sink = $this->redirectMessages();

        import_notifier::notify_configured_users(import_run_repository::STATUS_ERROR);

        $messages = $sink->get_messages_by_component_and_type('local_lehrgaengeapi', 'importcomplete');
        $this->assertCount(1, $messages);
        $this->assertSame(
            get_string('autoimporterrorsubject', 'local_lehrgaengeapi'),
            reset($messages)->subject
        );
    }

    /**
     * Without a configured user (setting missing, empty or "nobody") nothing is sent
     * and nothing fails - the automatic import must not depend on this setting.
     *
     * @covers \local_lehrgaengeapi\local\notification\import_notifier::notify_configured_users
     */
    public function test_nobody_configured_sends_nothing(): void {
        $this->resetAfterTest(true);
        $sink = $this->redirectMessages();

        import_notifier::notify_configured_users(import_run_repository::STATUS_SUCCESS);
        set_config('notifyusers', '', 'local_lehrgaengeapi');
        import_notifier::notify_configured_users(import_run_repository::STATUS_SUCCESS);
        set_config('notifyusers', '$@NONE@$', 'local_lehrgaengeapi');
        import_notifier::notify_configured_users(import_run_repository::STATUS_SUCCESS);

        $this->assertCount(0, $sink->get_messages_by_component_and_type('local_lehrgaengeapi', 'importcomplete'));
    }

    /**
     * Any Moodle user can be selected (no capability required), but suspended and deleted
     * users are skipped while the remaining recipients are still notified.
     *
     * @covers \local_lehrgaengeapi\local\notification\import_notifier::notify_configured_users
     */
    public function test_suspended_and_deleted_users_are_skipped(): void {
        $this->resetAfterTest(true);

        $active = $this->getDataGenerator()->create_user();
        $suspended = $this->getDataGenerator()->create_user(['suspended' => 1]);
        $deleted = $this->getDataGenerator()->create_user();
        delete_user($deleted);
        set_config(
            'notifyusers',
            $suspended->id . ',' . $deleted->id . ',' . $active->id,
            'local_lehrgaengeapi'
        );
        $sink = $this->redirectMessages();

        import_notifier::notify_configured_users(import_run_repository::STATUS_SUCCESS);

        $messages = $sink->get_messages_by_component_and_type('local_lehrgaengeapi', 'importcomplete');
        $this->assertCount(1, $messages);
        $this->assertSame((int)$active->id, (int)reset($messages)->useridto);
    }

    /**
     * Garbage in the stored value (e.g. the legacy "$@NONE@$" marker) is ignored.
     *
     * @covers \local_lehrgaengeapi\local\notification\import_notifier::notify_configured_users
     */
    public function test_invalid_stored_value_is_ignored(): void {
        $this->resetAfterTest(true);
        $sink = $this->redirectMessages();

        set_config('notifyusers', '$@NONE@$,abc,0', 'local_lehrgaengeapi');
        import_notifier::notify_configured_users(import_run_repository::STATUS_SUCCESS);

        $this->assertCount(0, $sink->get_messages_by_component_and_type('local_lehrgaengeapi', 'importcomplete'));
    }
}
