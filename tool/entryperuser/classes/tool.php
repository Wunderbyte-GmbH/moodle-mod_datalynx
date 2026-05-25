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
 * Entry per user tool runner.
 *
 * @package    datalynxtool_entryperuser
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxtool_entryperuser;

use mod_datalynx\local\datalynx_entries;
use datalynxtool_entryperuser\form\entryperuser_form;
use moodle_url;
use stdClass;

/**
 * Entry per user tool runner class.
 *
 * @package    datalynxtool_entryperuser
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool {
    /**
     * Run the tool to create entries for each user.
     *
     * @param \mod_datalynx\datalynx $dlx Datalynx instance.
     * @return array|null [good/bad status, message] or null if form is rendered and exits.
     */
    public static function run($dlx) {
        global $PAGE, $OUTPUT;

        // Find the default view or the first view containing the ##edit## tag.
        $views = $dlx->get_views();
        $defaultviewid = 0;

        // Try the default view first.
        if ($dlx->data->defaultview && isset($views[$dlx->data->defaultview])) {
            $view = $views[$dlx->data->defaultview];
            $hasedit = false;
            foreach ((array) $view->view as $val) {
                if (is_string($val) && strpos($val, '##edit##') !== false) {
                    $hasedit = true;
                    break;
                }
            }
            if ($hasedit) {
                $defaultviewid = $dlx->data->defaultview;
            }
        }

        // Fall back to the first view containing the ##edit## tag.
        if (!$defaultviewid) {
            foreach ($views as $viewid => $view) {
                foreach ((array) $view->view as $val) {
                    if (is_string($val) && strpos($val, '##edit##') !== false) {
                        $defaultviewid = $viewid;
                        break 2;
                    }
                }
            }
        }

        // Determine step and selected view ID.
        $step = optional_param('step', 1, PARAM_INT);
        $viewid = optional_param('viewid', $defaultviewid, PARAM_INT);

        // Build the form action URL.
        $formparams = [
            'id' => $dlx->cm->id,
            'run' => 'entryperuser',
            'sesskey' => sesskey(),
        ];
        $actionurl = new moodle_url('/mod/datalynx/tool/index.php', $formparams);
        $customdata = [
            'dlx' => $dlx,
            'step' => $step,
            'selectedviewid' => $viewid,
        ];

        $form = new entryperuser_form($actionurl, $customdata);

        if ($form->is_cancelled()) {
            redirect(new moodle_url('/mod/datalynx/tool/index.php', ['id' => $dlx->cm->id]));
        } else if ($form->is_submitted()) {
            $submitteddata = $form->get_submitted_data();

            if (isset($submitteddata->backbutton)) {
                // User clicked 'Back': return to Step 1.
                $step = 1;
                $viewid = isset($submitteddata->viewid) ? (int) $submitteddata->viewid : $defaultviewid;

                $customdata['step'] = $step;
                $customdata['selectedviewid'] = $viewid;
                $form = new entryperuser_form($actionurl, $customdata);
            } else if (isset($submitteddata->nextbutton)) {
                // User clicked 'Next': proceed to Step 2.
                $step = 2;
                $viewid = isset($submitteddata->viewid) ? (int) $submitteddata->viewid : $defaultviewid;

                $customdata['step'] = $step;
                $customdata['selectedviewid'] = $viewid;
                $form = new entryperuser_form($actionurl, $customdata);
            } else if ($data = $form->get_data()) {
                // Generate entries.
                $users = $dlx->get_gradebook_users();
                if (!$users) {
                    return ['bad', get_string('nousers', 'datalynxtool_entryperuser')];
                }

                // Construct entries data payload.
                $entriesdata = (object) ['eids' => []];
                $entryid = -1;

                foreach (array_keys($users) as $userid) {
                    $entriesdata->eids[$entryid] = $entryid;

                    // Set the owner/userid of the entry to the target user.
                    $entriesdata->{"field_userid_{$entryid}"} = $userid;

                    // Copy the submitted default values, replacing the suffix _-1 with the user's entry id.
                    foreach ($data as $key => $value) {
                        if (strpos($key, 'field_') === 0 && strpos($key, '_-1') !== false) {
                            $newkey = str_replace('_-1', "_{$entryid}", $key);
                            $entriesdata->$newkey = $value;
                        }
                    }
                    $entryid--;
                }

                // Process and save the entries.
                $em = new datalynx_entries($dlx);
                $processed = $em->process_entries('update', $entriesdata->eids, $entriesdata, true);

                if (is_array($processed)) {
                    [$strnotify, $processedeids] = $processed;
                    $entriesprocessed = $processedeids ? count($processedeids) : 0;
                    if ($entriesprocessed) {
                        return ['good', get_string('success', 'datalynxtool_entryperuser')];
                    }
                }

                return ['bad', get_string('entriesupdated', 'datalynx', get_string('no'))];
            }
        }

        // Render form.
        $dlx->print_header(['tab' => 'tools']);
        $form->display();
        $dlx->print_footer();
        exit(0);
    }
}
