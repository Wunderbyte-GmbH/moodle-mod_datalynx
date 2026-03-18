<?php
/**
 * Filter form class.
 *
 * @package    mod_datalynx
 * @copyright  2014 onwards by edulabs.org and associated programmers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
/**
 * Filter form class.
 */
class mod_datalynx_filter_form extends mod_datalynx_filter_base_form {
    /**
     * Form definition.
     */
    public function definition() {
        $df = $this->_df;
        $filter = $this->_filter;
        $name = empty($filter->name) ? get_string('filternew', 'datalynx') : $filter->name;
        $description = empty($filter->description) ? '' : $filter->description;
        $visible = !isset($filter->visible) ? 1 : $filter->visible;
        $fields = $df->get_fields();
        $fieldoptions = [0 => get_string('choose')] + $df->get_fields(['entry'], true);
        $mform = &$this->_form;
        // Buttons.
        $this->add_action_buttons(true);
        $mform->addElement('header', 'general', get_string('general', 'form'));
        // Name and description.
        $mform->addElement('text', 'name', get_string('name'));
        $mform->addElement('text', 'description', get_string('description'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->setType('description', PARAM_TEXT);
        $mform->setDefault('name', $name);
        $mform->setDefault('description', $description);
        // Visibility.
        $visibilityoptions = [0 => 'hidden', 1 => 'visible'];
        $mform->addElement('select', 'visible', get_string('visible'), $visibilityoptions);
        $mform->setDefault('visible', $visible);
        $mform->addElement('header', 'filterhdr', get_string('viewfilter', 'datalynx'));
        $mform->setExpanded('filterhdr');
        // Entries per page.
        $options = [0 => get_string('choose'), 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6,
                7 => 7, 8 => 8, 9 => 9, 10 => 10, 15 => 15, 20 => 20, 30 => 30, 40 => 40, 50 => 50,
                100 => 100, 200 => 200, 300 => 300, 400 => 400, 500 => 500, 1000 => 1000];
        $mform->addElement('select', 'perpage', get_string('viewperpage', 'datalynx'), $options);
        $mform->setDefault('perpage', $filter->perpage);
        // Selection method.
        $options = [0 => get_string('filterbypage', 'datalynx'), 1 => get_string('random', 'datalynx')];
        $mform->addElement('select', 'selection', get_string('filterselection', 'datalynx'), $options);
        $mform->setDefault('selection', $filter->selection);
        $mform->disabledIf('selection', 'perpage', 'eq', '0');
        // Group by.
        $groupbyfieldoptions = [0 => get_string('choose')];
        foreach ($fields as $field) {
            if ($field->supports_group_by()) {
                $groupbyfieldoptions[$field->id()] = $field->name();
            }
        }
        $mform->addElement('select', 'groupby', get_string('filtergroupby', 'datalynx'), $groupbyfieldoptions);
        $mform->setDefault('groupby', $filter->groupby);
        // Search.
        $mform->addElement('text', 'search', get_string('search'));
        $mform->setType('search', PARAM_TEXT);
        $mform->setDefault('search', $filter->search);
        // Custom sort.
        $mform->addElement('header', 'customsorthdr', get_string('filtercustomsort', 'datalynx'));
        $mform->setExpanded('customsorthdr');
        $this->custom_sort_definition($filter->customsort, $fields, $fieldoptions, true);
        // Custom search.
        $mform->addElement('header', 'customsearchhdr', get_string('filtercustomsearch', 'datalynx'));
        $mform->setExpanded('customsearchhdr');
        $this->custom_search_definition($filter->customsearch, $fields, $fieldoptions, true);
        // Buttons.
        $this->add_action_buttons(true);
    }
    /**
     * Validate form data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $df = $this->_df;
        $filter = $this->_filter;
        // Validate unique name.
        if (empty($data['name']) || $df->name_exists('filters', $data['name'], $filter->id)) {
            $errors['name'] = get_string(
                'invalidname',
                'datalynx',
                get_string('filter', 'datalynx')
            );
        }
        return $errors;
    }
}
