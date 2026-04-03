<?php
// This file is part of mod_datalynx for Moodle - http://moodle.org/
//
// It is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// It is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_datalynx\local\field;

use stdClass;

/**
 * Base class for Datalynx field types that offer single choice or multiplce choices
 * from a set of options
 */
abstract class datalynxfield_option extends datalynxfield_base {
    protected $options = [];

    /**
     * TODO: see if this can be changed or merged with function below
     *
     * @return mixed
     */
    public function get_options() {
        if (!$this->options) {
            if (!empty($this->field->param1)) {
                $rawoptions = explode("\n", $this->field->param1);
                foreach ($rawoptions as $key => $option) {
                    $option = trim($option);
                    if ($option != '') {
                        $this->options[$key + 1] = $option;
                    }
                }
            }
        }
        return $this->options;
    }

    /**
     *
     * @param boolean $forceget
     * @return mixed
     */
    public function options_menu($forceget = false, $addnoselection = false) {
        if (!$this->options || $forceget) {
            if (!empty($this->field->param1)) {
                if ($addnoselection) {
                    $this->options[0] = '...';
                }
                $rawoptions = explode("\n", $this->field->param1);
                foreach ($rawoptions as $key => $option) {
                    $option = trim($option);
                    if ($option != '') {
                        $this->options[$key + 1] = $option;
                    }
                }
            }
        }
        return $this->options;
    }

    /**
     *
     * @param array $map
     * @return mixed
     */
    abstract public function update_options($map = []);

    /**
     * When an option from a single/multi choice is deleted / renamed or added
     * the old content will be updated to the new values of the options. If an option
     * is deleted all the selections for that specific option made in an entry will be deleted
     * (non-PHPdoc)
     *
     * @see datalynxfield_base::set_field()
     */
    public function set_field($forminput = null) {
        $this->field = new stdClass();
        $this->field->id = !empty($forminput->id) ? $forminput->id : 0;
        $this->field->type = $this->type;
        $this->field->dataid = $this->df->id();
        $this->field->name = !empty($forminput->name) ? trim($forminput->name) : '';
        $this->field->description = !empty($forminput->description) ? trim($forminput->description) : '';
        $this->field->visible = isset($forminput->visible) ? $forminput->visible : 2;
        $this->field->edits = isset($forminput->edits) ? $forminput->edits : -1;
        $this->field->label = !empty($forminput->label) ? $forminput->label : '';

        $oldvalues = $newvalues = $this->options;
        $renames = !empty($forminput->renameoption) ? $forminput->renameoption : [];
        $deletes = !empty($forminput->deleteoption) ? $forminput->deleteoption : [];
        $adds = preg_split(
                "/[\|\r\n]+/",
                !empty($forminput->addoptions) ? $forminput->addoptions : ''
        );

        // Make sure there are no renames when options are deleted. That will not work.
        $delvalues = array_values($deletes);
        if (!empty($delvalues)) {
            $renames = [];
        }

        $delkeys = array_keys($deletes);
        foreach ($delkeys as $id) {
            $addedid = array_search($oldvalues[$id], $adds);
            if ($addedid !== false) {
                unset($adds[$addedid]);
                unset($deletes[$id]);
            } else {
                unset($newvalues[$id]);
            }
        }

        $map = [0 => 0];
        for ($i = 1; $i <= count($oldvalues); $i++) {
            $j = array_search($oldvalues[$i], $newvalues);
            if ($j !== false) {
                $map[$i] = $j;
            } else {
                $map[$i] = 0;
            }
        }

        foreach ($renames as $id => $newname) {
            if (!!(trim($newname))) {
                $newvalues[$id] = $newname;
            }
        }

        foreach ($adds as $add) {
            $add = trim($add);
            if (!empty($add)) {
                $newvalues[] = $add;
            }
        }

        if (!empty($this->options)) {
            $this->update_options($map);
        }

        $this->field->param1 = implode("\n", $newvalues);
        for ($i = 2; $i <= 10; $i++) {
            $param = "param$i";
            if (isset($forminput->$param)) {
                $this->field->$param = $forminput->$param;
            }
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see datalynxfield_base::format_search_value()
     */
    public function format_search_value($searchparams) {
        [$not, $operator, $value] = $searchparams;
        if (is_array($value)) {
            $selected = implode(', ', $value);
            return $not . ' ' . $operator . ' ' . $selected;
        } else {
            return false;
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see datalynxfield_base::parse_search()
     */
    public function parse_search($formdata, $i) {
        $fieldname = "f_{$i}_{$this->field->id}";
        return optional_param_array($fieldname, false, PARAM_NOTAGS);
    }

    /**
     * Are fields of this field type suitable for use in customfilters?
     *
     * @return bool
     */
    public static function is_customfilterfield() {
        return true;
    }

    public function get_argument_count(string $operator) {
        if ($operator === "") { // "Empty" operator
            return 0;
        } else {
            return 1;
        }
    }
}