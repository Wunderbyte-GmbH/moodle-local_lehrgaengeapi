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
 * Admin setting: search and select several Moodle users.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\admin;

/**
 * Admin setting: search and select several Moodle users.
 *
 * The value is stored as a comma separated list of user ids.
 * @package local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_users_autocomplete extends \admin_setting {
    /**
     * Constructor.
     *
     * @param string $name 'plugin/setting' name.
     * @param string $visiblename Localised name.
     * @param string $description Localised description.
     */
    public function __construct(string $name, string $visiblename, string $description) {
        parent::__construct($name, $visiblename, $description, '');
    }

    /**
     * Current value: comma separated user ids, null if never saved.
     *
     * @return string|null
     */
    public function get_setting() {
        return $this->config_read($this->name);
    }

    /**
     * Save the submitted user ids.
     *
     * Unknown, deleted and duplicate ids are dropped; the "xxxxx" placeholder that
     * keeps an empty selection submittable is ignored.
     *
     * @param mixed $data Array of submitted user ids.
     * @return string Empty string on success, otherwise an error message.
     */
    public function write_setting($data) {
        global $DB;

        $ids = array_unique(array_filter(array_map('intval', (array)$data)));
        if ($ids) {
            [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
            $valid = $DB->get_fieldset_select('user', 'id', "id $insql AND deleted = 0", $params);
            $ids = array_values(array_filter($ids, static fn($id) => in_array($id, $valid)));
        }

        return $this->config_write($this->name, implode(',', $ids)) ? '' : get_string('errorsetting', 'admin');
    }

    /**
     * Render a multi-select enhanced with the core user autocomplete.
     *
     * Only the already selected users are rendered as options, everything else is
     * searched via AJAX - so the page stays fast on sites with many users.
     *
     * @param mixed $data Current value.
     * @param string $query Admin search query.
     * @return string
     */
    public function output_html($data, $query = '') {
        global $DB, $PAGE;

        $ids = array_filter(array_map('intval', explode(',', (string)$data)));
        $options = '';
        if ($ids) {
            $users = $DB->get_records_list('user', 'id', $ids, 'lastname, firstname');
            foreach ($users as $user) {
                $options .= \html_writer::tag('option', s(fullname($user)), [
                    'value' => $user->id,
                    'selected' => 'selected',
                ]);
            }
        }

        $select = \html_writer::tag('select', $options, [
            'id' => $this->get_id(),
            'name' => $this->get_full_name() . '[]',
            'multiple' => 'multiple',
            'class' => 'form-select',
        ]);
        // Without this an empty selection would not be submitted at all.
        $select .= \html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => $this->get_full_name() . '[]',
            'value' => 'xxxxx',
        ]);

        $PAGE->requires->js_call_amd('core/form-autocomplete', 'enhance', [
            '#' . $this->get_id(),
            false,
            'core_user/form_user_selector',
            get_string('search'),
            false,
            true,
            get_string('noselection', 'form'),
        ]);

        return format_admin_setting($this, $this->visiblename, $select, $this->description, true, '', null, $query);
    }
}
