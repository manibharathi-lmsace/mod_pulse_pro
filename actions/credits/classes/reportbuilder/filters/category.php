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
 * Pulse action report category filter.
 *
 * @package    pulseaction_credits
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace pulseaction_credits\reportbuilder\filters;

use core_course_category;
use core_reportbuilder\local\helpers\database;

/**
 * Pulse action report category filter.
 */
class category extends \core_reportbuilder\local\filters\category {
    /**
     * Return filter SQL
     *
     * @param array $values
     * @return array
     */
    public function get_sql_filter(array $values): array {
        global $DB;

        [$fieldsql, $params] = $this->filter->get_field_sql_and_params();

        $operator = (int) ($values["{$this->name}_operator"] ?? self::EQUAL_TO);
        $category = (int) ($values["{$this->name}_value"] ?? 0);
        $subcategories = !empty($values["{$this->name}_subcategories"]);

        // Invalid or inactive filter.
        if (empty($category)) {
            return ['', []];
        }

        // Initial matching on selected category.
        $paramcategory = database::generate_param_name();
        $params[$paramcategory] = $category;
        $like = $DB->sql_like($fieldsql, ":{$paramcategory}");
        $sql = "$like";
        $params[$paramcategory] = "%\"$category\"%";

        // Sub-category matching on path of selected category.
        if ($subcategories) {
            // We need to re-use the original filter SQL here, while ensuring parameter uniqueness is preserved.
            [$fieldsql, $params1] = $this->filter->get_field_sql_and_params(1);
            $params = array_merge($params, $params1);

            $paramcategorypath = database::generate_param_name();
            $params[$paramcategorypath] = "%/{$category}/%";

            $records = $DB->get_records_sql("
                SELECT id
                FROM {course_categories}
                WHERE " . $DB->sql_like('path', ":{$paramcategorypath}"), $params);

            $subrecords = array_column($records, 'id');

            if (!empty($subrecords)) {
                foreach ($subrecords as $key => $cateid) {
                    $likesql[] = $DB->sql_like("{$fieldsql}", ":cateid$key", false, false);
                    $params["cateid$key"] = '%"' . $cateid . '"%';
                }

                $subcateselect = '(' . implode(' OR ', $likesql) . ')';

                $sql .= " OR {$subcateselect}";
            }
        }

        // If specified "Not equal to", then negate the entire clause.
        if ($operator === self::NOT_EQUAL_TO) {
            $sql = "NOT ({$sql})";
            // Include the free avatars without pro data.
            $fieldsql = $this->filter->get_field_sql();
            $alias = explode('.', $fieldsql)[0];
            $sql .= " OR {$alias}.avatarid IS NULL";
        }

        return [$sql, $params];
    }
}
