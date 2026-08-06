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

/**
 * Behat generator for pulse module actions.
 *
 * @package   mod_pulse
 * @copyright 2024, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Behat generator for pulse module actions.
 */
class behat_mod_pulse_generator extends behat_generator_base {
    /**
     * Get a list of the entities that can be created.
     *
     * @return array entity name => information about how to generate.
     */
    protected function get_creatable_entities(): array {

        return [
            'automation templates' => [
                'singular' => 'automation template',
                'datagenerator' => 'automation_template',
                'required' => ['title', 'reference'],
                'switchids' => [],
            ],

            'automation instances' => [
                'singular' => 'automation instance',
                'datagenerator' => 'automation_instance',
                'required' => ['template', 'reference', 'course'],
                'switchids' => ['template' => 'templateid', 'course' => 'courseid'],
            ],
        ];
    }

    /**
     * Get template id by its title or reference.
     *
     * @param string $reference
     * @return int
     */
    public function get_template_id(string $reference): int {
        global $DB;
        return $DB->get_field_select(
            'pulse_autotemplates',
            'id',
            'title=:title OR reference=:reference',
            ['title' => $reference, 'reference' => $reference],
            MUST_EXIST
        );
    }
}
