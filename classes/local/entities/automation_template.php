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
 * Pulse automation template entity for report builder.
 *
 * @package   mod_pulse
 * @copyright 2025, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_pulse\local\entities;

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\report\{column, filter};
use core_reportbuilder\local\filters\{date, number, select, text};
use core_reportbuilder\local\helpers\format;
use lang_string;
use pulseaction_credits\reportbuilder\filters\category;

/**
 * Pulse automation template entity for report builder.
 */
class automation_template extends base {
    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_tables(): array {
        return [
            'pulse_autotemplates',
        ];
    }

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return [
            'pulse_autotemplates' => 'pat',
        ];
    }

    /**
     * The default title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('automationtemplate', 'mod_pulse');
    }

    /**
     * Initialise the automation template entity.
     *
     * @return base
     */
    public function initialise(): base {

        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $column->add_joins($this->get_joins());
            $this->add_column($column);
        }

        [$filters, $conditions] = $this->get_all_filters();
        foreach ($filters as $filter) {
            $this->add_filter($filter);
        }

        foreach ($conditions as $condition) {
            $this->add_condition($condition);
        }

        return $this;
    }

    /**
     * List of columns available for automation template.
     *
     * @return array
     */
    protected function get_all_columns(): array {
        $templatesalias = $this->get_table_alias('pulse_autotemplates');

        $columns = [];

        // Template ID.
        $columns[] = (new column(
            'id',
            new lang_string('templateid', 'mod_pulse'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(true)
        ->add_field("{$templatesalias}.id");

        // Template title.
        $columns[] = (new column(
            'title',
            new lang_string('templatetitle', 'mod_pulse'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(true)
        ->add_field("{$templatesalias}.title", 'templatetitle')
        ->add_callback(static function ($value, $row): string {
            $val = $row->templatetitle ?: '';
            return format_string($val);
        });

        // Template reference.
        $columns[] = (new column(
            'reference',
            new lang_string('tempreference', 'mod_pulse'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(true)
        ->add_field("{$templatesalias}.reference", 'tempreference')
        ->add_callback(static function ($value, $row): string {
            $val = $row->tempreference ?: '';
            return format_string($val);
        });

        // Template status.
        $columns[] = (new column(
            'status',
            new lang_string('status', 'mod_pulse'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(true)
        ->add_field("{$templatesalias}.status")
        ->add_callback(static function ($value, $row): string {
            return $value ? get_string('enabled', 'mod_pulse') : get_string('disabled', 'mod_pulse');
        });

        // Template visibility.
        $columns[] = (new column(
            'visible',
            new lang_string('visibility', 'mod_pulse'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(true)
        ->add_field("{$templatesalias}.visible")
        ->add_callback(static function ($value, $row): string {
            return $value ? get_string('visible', 'mod_pulse') : get_string('hidden', 'mod_pulse');
        });

        // Template category.
        $columns[] = (new column(
            'category',
            new lang_string('category', 'mod_pulse'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(true)
        ->add_field("{$templatesalias}.categories")
        ->add_callback(function ($value) {
            $categories = $value ? json_decode($value, true) : [];
            if (empty($categories)) {
                return get_string('none');
            }
            $categorynames = array_map(function ($categoryid) {
                $category = \core_course_category::get($categoryid, IGNORE_MISSING);
                return $category ? $category->get_formatted_name() : '';
            }, $categories);
            return implode(', ', array_filter($categorynames));
        });

        // Time modified.
        $columns[] = (new column(
            'timemodified',
            new lang_string('timemodified', 'mod_pulse'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(true)
        ->add_field("{$templatesalias}.timemodified")
        ->add_callback(static function ($value, $row): string {
            return userdate($value);
        });

        return $columns;
    }

    /**
     * Defined filters for the automation template entity.
     *
     * @return array
     */
    protected function get_all_filters(): array {
        $templatesalias = $this->get_table_alias('pulse_autotemplates');

        $filters = [];
        $conditions = [];

        // Template ID filter.
        $conditions[] = (new filter(
            number::class,
            'id',
            new lang_string('templateid', 'mod_pulse'),
            $this->get_entity_name(),
            "{$templatesalias}.id"
        ));

        // Template title filter.
        $filters[] = (new filter(
            text::class,
            'title',
            new lang_string('templatetitle', 'mod_pulse'),
            $this->get_entity_name(),
            "{$templatesalias}.title"
        ));

        // Template reference filter.
        $filters[] = (new filter(
            text::class,
            'reference',
            new lang_string('tempreference', 'mod_pulse'),
            $this->get_entity_name(),
            "{$templatesalias}.reference"
        ));

        // Template status filter.
        $filters[] = (new filter(
            select::class,
            'status',
            new lang_string('status', 'mod_pulse'),
            $this->get_entity_name(),
            "{$templatesalias}.status"
        ))->set_options([
            0 => get_string('disabled', 'mod_pulse'),
            1 => get_string('enabled', 'mod_pulse'),
        ]);

        // Template visibility filter.
        $filters[] = (new filter(
            select::class,
            'visible',
            new lang_string('visibility', 'mod_pulse'),
            $this->get_entity_name(),
            "{$templatesalias}.visible"
        ))->set_options([
            0 => get_string('hidden', 'mod_pulse'),
            1 => get_string('visible', 'mod_pulse'),
        ]);

        // Template category filter.
        $filters[] = (new filter(
            category::class,
            'category',
            new lang_string('category', 'mod_pulse'),
            $this->get_entity_name(),
            "{$templatesalias}.categories"
        ));

        // Time modified filter.
        $filters[] = (new filter(
            date::class,
            'timemodified',
            new lang_string('timemodified', 'mod_pulse'),
            $this->get_entity_name(),
            "{$templatesalias}.timemodified"
        ));

        return [$filters, array_merge($conditions, $filters)];
    }
}
