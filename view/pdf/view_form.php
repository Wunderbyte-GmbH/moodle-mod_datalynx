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

/**
 *
 * @package datalynxview
 * @subpackage pdf
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/view/view_form.php");

class datalynxview_pdf_form extends datalynxview_base_form {

    /**
     */
    public function view_definition_after_gps() {
        global $COURSE;

        $view = $this->_view;
        $editoroptions = $view->editors();
        $editorattr = array('cols' => 40, 'rows' => 12);

        $mform = &$this->_form;

        // Repeated entry (param2).
        $mform->addElement('header', 'viewlisthdr', get_string('entrytemplate', 'datalynx'));

        $mform->addElement('editor', 'eparam2_editor', '', $editorattr, $editoroptions['param2']);
        $mform->setDefault("eparam2_editor[format]", FORMAT_PLAIN);
        $this->add_tags_selector('eparam2_editor', 'field');
        $this->add_tags_selector('eparam2_editor', 'character');

        // PDF settings (param1).
        $mform->addElement('header', 'pdfsettingshdr',
                get_string('pdfsettings', 'datalynxview_pdf'));
        // Document name.
        $mform->addElement('text', 'docname', get_string('docname', 'datalynxview_pdf'),
                array('size' => 64));
        $mform->setType('docname', PARAM_TEXT);
        $mform->addHelpButton('docname', 'docname', 'datalynxview_pdf');
        // Orientation: Portrait, Landscape.
        $options = array('' => get_string('auto', 'datalynxview_pdf'),
                'P' => get_string('portrait', 'datalynxview_pdf'),
                'L' => get_string('landscape', 'datalynxview_pdf'));
        $mform->addElement('select', 'orientation', get_string('orientation', 'datalynxview_pdf'),
                $options);
        // Unit.
        $options = array('mm' => get_string('unit_mm', 'datalynxview_pdf'),
                'pt' => get_string('unit_pt', 'datalynxview_pdf'),
                'cm' => get_string('unit_cm', 'datalynxview_pdf'),
                'in' => get_string('unit_in', 'datalynxview_pdf'));
        $mform->addElement('select', 'unit', get_string('unit', 'datalynxview_pdf'), $options);
        // Format.
        $options = array('A4' => get_string('A4', 'datalynxview_pdf'),
                'LETTER' => get_string('LETTER', 'datalynxview_pdf'));
        $mform->addElement('select', 'format', get_string('format', 'datalynxview_pdf'), $options);
        // Destination.
        $options = array('D' => get_string('dest_D', 'datalynxview_pdf'),
                'I' => get_string('dest_I', 'datalynxview_pdf'));
        $mform->addElement('select', 'destination', get_string('destination', 'datalynxview_pdf'),
                $options);

        // PDF TOC.
        $mform->addElement('header', 'pdftochdr', get_string('pdftoc', 'datalynxview_pdf'));
        // Page.
        $mform->addElement('text', 'tocpage', get_string('tocpage', 'datalynxview_pdf'));
        $mform->setType('tocpage', PARAM_INT);
        $mform->addHelpButton('tocpage', 'tocpage', 'datalynxview_pdf');
        // Name.
        $mform->addElement('text', 'tocname', get_string('tocname', 'datalynxview_pdf'));
        $mform->setType('tocname', PARAM_TEXT);
        $mform->addHelpButton('tocname', 'tocname', 'datalynxview_pdf');
        // Title.
        $mform->addElement('textarea', 'toctitle', get_string('toctitle', 'datalynxview_pdf'),
                array('rows' => 3, 'style' => 'width:100%'));
        $mform->addHelpButton('toctitle', 'toctitle', 'datalynxview_pdf');
        // Template.
        $mform->addElement('textarea', 'toctmpl', get_string('toctmpl', 'datalynxview_pdf'),
                array('rows' => 10, 'style' => 'width:100%'));
        $mform->addHelpButton('toctmpl', 'toctmpl', 'datalynxview_pdf');

        // PDF Frame.
        $mform->addElement('header', 'pdfframehdr', get_string('pdfframe', 'datalynxview_pdf'));
        $fileoptions = array('subdirs' => 0, 'maxbytes' => -1, 'maxfiles' => 1,
                'accepted_types' => array('image'));
        $mform->addElement('filemanager', 'pdfframe', get_string('image', 'datalynxview_pdf'), null,
                $fileoptions);
        $mform->addHelpButton('pdfframe', 'pdfframe', 'datalynxview_pdf');
        $mform->addElement('selectyesno', 'pdfframefirstpageonly', get_string('pdfframefirstpageonly', 'datalynxview_pdf'));

        // PDF Watermark.
        $mform->addElement('header', 'pdfwatermarkhdr', get_string('pdfwmark', 'datalynxview_pdf'));
        $fileoptions = array('subdirs' => 0, 'maxbytes' => -1, 'maxfiles' => 1,
                'accepted_types' => array('image'));
        // Watermark image.
        $mform->addElement('filemanager', 'pdfwmark', get_string('image', 'datalynxview_pdf'), null,
                $fileoptions);
        $mform->addHelpButton('pdfwmark', 'pdfwmark', 'datalynxview_pdf');
        // Watermark Transparency.
        $transunits = range(0, 1, 0.1);
        $options = array_combine($transunits, $transunits);
        $mform->addElement('select', 'transparency', get_string('transparency', 'datalynxview_pdf'),
                $options);
        $mform->addHelpButton('transparency', 'transparency', 'datalynxview_pdf');

        // PDF Header.
        $mform->addElement('header', 'pdfheaderhdr', get_string('pdfheader', 'datalynxview_pdf'));
        // Header enbabled.
        $mform->addElement('selectyesno', 'headerenabled',
                get_string('enabled', 'datalynxview_pdf'));
        // Header content (param3).
        $mform->addElement('editor', 'eparam3_editor', get_string('content'), $editorattr,
                $editoroptions['param3']);
        $mform->setDefault("eparam3_editor[format]", FORMAT_PLAIN);
        // Header margin top.
        $mform->addElement('text', 'headermargintop',
                get_string('margin', 'datalynxview_pdf') . ' ' .
                get_string('margintop', 'datalynxview_pdf'));
        $mform->setType('headermargintop', PARAM_INT);
        $mform->addRule('headermargintop', null, 'numeric', null, 'client');
        $mform->disabledIf('headermargintop', 'headerenabled', 'eq', 0);
        // Header margin left.
        $mform->addElement('text', 'headermarginleft',
                get_string('margin', 'datalynxview_pdf') . ' ' .
                get_string('marginleft', 'datalynxview_pdf'));
        $mform->setType('headermarginleft', PARAM_INT);
        $mform->addRule('headermarginleft', null, 'numeric', null, 'client');
        $mform->disabledIf('headermarginleft', 'headerenabled', 'eq', 0);

        // PDF Footer.
        $mform->addElement('header', 'pdffooterhdr', get_string('pdffooter', 'datalynxview_pdf'));

        // Footer enbabled.
        $mform->addElement('selectyesno', 'footerenabled',
                get_string('enabled', 'datalynxview_pdf'));

        // Footer content (param4).
        $mform->addElement('editor', 'eparam4_editor', get_string('content'), $editorattr,
                $editoroptions['param4']);
        $mform->setDefault("eparam4_editor[format]", FORMAT_PLAIN);

        // Footer margin.
        $options = array_combine(range(1, 30), range(1, 30));
        $mform->addElement('select', 'footermargin', get_string('margin', 'datalynxview_pdf'),
                $options);
        $mform->disabledIf('footermargin', 'footerenabled', 'eq', 0);

        // PDF margins and paging.
        $mform->addElement('header', 'pdfmarginshdr', get_string('pdfmargins', 'datalynxview_pdf'));

        $mform->addElement('text', 'marginleft', get_string('marginleft', 'datalynxview_pdf'));
        $mform->setType('marginleft', PARAM_INT);
        $mform->addElement('text', 'margintop', get_string('margintop', 'datalynxview_pdf'));
        $mform->setType('margintop', PARAM_INT);
        $mform->addElement('text', 'marginright', get_string('marginright', 'datalynxview_pdf'));
        $mform->setType('marginright', PARAM_INT);
        $mform->addElement('selectyesno', 'marginkeep',
                get_string('marginkeep', 'datalynxview_pdf'));

        // Page break.
        $options = array('none' => get_string('none'),
                'auto' => get_string('auto', 'datalynxview_pdf'),
                'entry' => get_string('entry', 'datalynx'));
        $mform->addElement('select', 'pagebreak', get_string('pagebreak', 'datalynxview_pdf'),
                $options);

        // Protection.
        $mform->addElement('header', 'pdfprotectionhdr',
                get_string('pdfprotection', 'datalynxview_pdf'));

        // Permissions.
        $perms = $view::get_permission_options();
        foreach ($perms as $perm => $label) {
            $elemgrp[] = &$mform->createElement('advcheckbox', "perm_$perm", null, $label, array('size' => 1),
                    array('', $perm));
        }
        $mform->addGroup($elemgrp, "perms_grp", get_string('protperms', 'datalynxview_pdf'),
                '<br />', false);

        // User Password.
        $mform->addElement('text', 'protuserpass', get_string('protuserpass', 'datalynxview_pdf'));
        $mform->setType('protuserpass', PARAM_TEXT);

        // Owner Password.
        $mform->addElement('text', 'protownerpass', get_string('protownerpass', 'datalynxview_pdf'));
        $mform->setType('protownerpass', PARAM_TEXT);

        // Mode.
        $options = array('' => get_string('none'), 0 => get_string('protmode0', 'datalynxview_pdf'),
                1 => get_string('protmode1', 'datalynxview_pdf'),
                2 => get_string('protmode2', 'datalynxview_pdf'),
                3 => get_string('protmode3', 'datalynxview_pdf'));
        $mform->addElement('select', 'protmode', get_string('protmode', 'datalynxview_pdf'),
                $options);

        // Pub keys.

        // Digital Signature.
        $mform->addElement('header', 'pdfsignaturehdr',
                get_string('pdfsignature', 'datalynxview_pdf'));

        // Certification.
        $fileoptions = array('subdirs' => 0, 'maxbytes' => -1, 'maxfiles' => 1,
                'accepted_types' => array('.crt'));
        $mform->addElement('filemanager', 'pdfcert',
                get_string('certification', 'datalynxview_pdf'), null, $fileoptions);

        // Password.
        $mform->addElement('text', 'certpassword', get_string('certpassword', 'datalynxview_pdf'));
        $mform->setType('certpassword', PARAM_TEXT);

        // Type.
        $options = array(1 => get_string('none'), 2 => get_string('certperm2', 'datalynxview_pdf'),
                3 => get_string('certperm3', 'datalynxview_pdf'));
        $mform->addElement('select', 'certtype', get_string('certtype', 'datalynxview_pdf'),
                $options);

        // Info.
        $mform->addElement('text', 'certinfoname', get_string('certinfoname', 'datalynxview_pdf'));
        $mform->setType('certinfoname', PARAM_TEXT);

        $mform->addElement('text', 'certinfoloc', get_string('certinfoloc', 'datalynxview_pdf'));
        $mform->setType('certinfoloc', PARAM_TEXT);

        $mform->addElement('text', 'certinforeason',
                get_string('certinforeason', 'datalynxview_pdf'));
        $mform->setType('certinforeason', PARAM_TEXT);

        $mform->addElement('text', 'certinfocontact',
                get_string('certinfocontact', 'datalynxview_pdf'));
        $mform->setType('certinfocontact', PARAM_TEXT);
    }

    /**
     */
    public function data_preprocessing(&$data) {
        parent::data_preprocessing($data);

        $view = $this->_view;

        // Pdf settings.
        if ($settings = $view->get_pdf_settings()) {
            foreach ($settings as $name => $value) {
                if ($name == 'header') {
                    $data->headerenabled = $settings->header->enabled;
                    $data->headermargintop = $settings->header->margintop;
                    $data->headermarginleft = $settings->header->marginleft;
                } else {
                    if ($name == 'footer') {
                        $data->footerenabled = $settings->footer->enabled;
                        $data->footermargin = $settings->footer->margin;
                    } else {
                        if ($name == 'margins') {
                            $data->marginleft = $settings->margins->left;
                            $data->margintop = $settings->margins->top;
                            $data->marginright = $settings->margins->right;
                            $data->marginkeep = $settings->margins->keep;
                        } else {
                            if ($name == 'toc') {
                                $data->tocpage = $settings->toc->page;
                                $data->tocname = $settings->toc->name;
                                $data->toctitle = $settings->toc->title;
                                $data->toctmpl = $settings->toc->template;
                            } else {
                                if ($name == 'protection') {
                                    $this->data_preprocess_protection($data, $value);
                                } else {
                                    if ($name == 'signature') {
                                        $this->data_preprocess_signature($data, $value);
                                    } else {
                                        $data->$name = $value;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Prepare protection settings for being serialized
     *
     * @param stdClass $data
     * @param stdClass $protection
     */
    protected function data_preprocess_protection(&$data, $protection) {
        $view = $this->_view;
        $perms = $view::get_permission_options();
        foreach ($perms as $perm => $unused) {
            if (in_array($perm, $protection->permissions)) {
                $var = "perm_$perm";
                $data->$var = $perm;
            }
        }
        $data->protuserpass = $protection->user_pass;
        $data->protownerpass = $protection->owner_pass;
        $data->protmode = $protection->mode;
    }

    /**
     */
    protected function data_preprocess_signature(&$data, $signsettings) {
        $data->certpassword = $signsettings->password;
        $data->certtype = $signsettings->type;
        $data->certinfoname = $signsettings->info['Name'];
        $data->certinfoloc = $signsettings->info['Location'];
        $data->certinforeason = $signsettings->info['Reason'];
        $data->certinfocontact = $signsettings->info['ContactInfo'];
    }

    /**
     */
    public function set_data($data) {
        $this->data_preprocessing($data);
        parent::set_data($data);
    }

    /**
     * Prepare data for saving. All settings are saved in param1 as serialized values
     *
     * {@inheritDoc}
     * @see datalynxview_base_form::get_data()
     */
    public function get_data($slashed = true) {
        $data = parent::get_data($slashed);
        if (!$data) {
            return null;
        }

        // Pdf settings.
        $view = $this->_view;
        if ($settings = $view->get_pdf_settings()) {
            foreach ($settings as $name => $value) {
                if ($name == 'header') {
                    $settings->header->enabled = $data->headerenabled;
                    if ($data->headerenabled) {
                        $settings->header->margintop = $data->headermargintop;
                        $settings->header->marginleft = $data->headermarginleft;
                    }
                } else {
                    if ($name == 'footer') {
                        $settings->footer->enabled = $data->footerenabled;
                        if ($data->footerenabled) {
                            $settings->footer->margin = $data->footermargin;
                        }
                    } else {
                        if ($name == 'margins') {
                            $settings->margins->left = $data->marginleft;
                            $settings->margins->top = $data->margintop;
                            $settings->margins->right = $data->marginright;
                            $settings->margins->keep = $data->marginkeep;
                        } else {
                            if ($name == 'toc') {
                                $settings->toc->page = $data->tocpage;
                                $settings->toc->name = $data->tocname;
                                $settings->toc->title = $data->toctitle;
                                $settings->toc->template = $data->toctmpl;
                                $settings->toc->template = $data->toctmpl;
                            } else {
                                if ($name == 'protection') {
                                    $this->data_postprocess_protection($settings, $data);
                                } else {
                                    if ($name == 'signature') {
                                        $this->data_postprocess_signature($settings, $data);
                                    } else {
                                        if (isset($data->$name)) {
                                            $settings->$name = $data->$name;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $data->param1 = base64_encode(serialize($settings));
        }

        return $data;
    }

    /**
     */
    protected function data_postprocess_protection(&$settings, $data) {
        $view = $this->_view;

        $protection = $settings->protection;
        $protection->permissions = array();
        $perms = $view::get_permission_options();
        foreach ($perms as $perm => $unused) {
            $var = "perm_$perm";
            if (!empty($data->$var)) {
                $protection->permissions[] = $perm;
            }
        }
        $protection->user_pass = $data->protuserpass;
        $protection->owner_pass = $data->protownerpass;
        $protection->mode = $data->protmode;

        $settings->protection = $protection;
    }

    /**
     */
    protected function data_postprocess_signature(&$settings, $data) {
        $signsettings = $settings->signature;
        $signsettings->password = $data->certpassword;
        $signsettings->type = $data->certtype;
        $signsettings->info['Name'] = $data->certinfoname;
        $signsettings->info['Location'] = $data->certinfoloc;
        $signsettings->info['Reason'] = $data->certinforeason;
        $signsettings->info['ContactInfo'] = $data->certinfocontact;

        $settings->signature = $signsettings;
    }
}
