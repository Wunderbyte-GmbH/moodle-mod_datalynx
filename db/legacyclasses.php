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
 * Legacy class map for datalynxfield classes stored in field subplugin classes/ directories.
 *
 * Paths are relative to {plugin_dir}/classes/ as required by load_legacy_classes().
 *
 * @package mod_datalynx
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$legacyclasses = [
    'datalynxfield_approve' => '../field/approve/classes/datalynxfield_approve.php',
    'datalynxfield_approve_renderer' => '../field/approve/classes/datalynxfield_approve_renderer.php',
    'datalynxfield_checkbox' => '../field/checkbox/classes/datalynxfield_checkbox.php',
    'datalynxfield_checkbox_form' => '../field/checkbox/classes/datalynxfield_checkbox_form.php',
    'datalynxfield_checkbox_renderer' => '../field/checkbox/classes/datalynxfield_checkbox_renderer.php',
    'datalynxfield_comment' => '../field/comment/classes/datalynxfield_comment.php',
    'datalynxfield_comment_renderer' => '../field/comment/classes/datalynxfield_comment_renderer.php',
    'datalynxfield_coursegroup' => '../field/coursegroup/classes/datalynxfield_coursegroup.php',
    'datalynxfield_coursegroup_form' => '../field/coursegroup/classes/datalynxfield_coursegroup_form.php',
    'datalynxfield_coursegroup_renderer' => '../field/coursegroup/classes/datalynxfield_coursegroup_renderer.php',
    'datalynxfield_datalynxview' => '../field/datalynxview/classes/datalynxfield_datalynxview.php',
    'datalynxfield_datalynxview_form' => '../field/datalynxview/classes/datalynxfield_datalynxview_form.php',
    'datalynxfield_datalynxview_renderer' => '../field/datalynxview/classes/datalynxfield_datalynxview_renderer.php',
    'datalynxfield_duration' => '../field/duration/classes/datalynxfield_duration.php',
    'datalynxfield_duration_form' => '../field/duration/classes/datalynxfield_duration_form.php',
    'datalynxfield_duration_renderer' => '../field/duration/classes/datalynxfield_duration_renderer.php',
    'datalynxfield_editor' => '../field/editor/classes/datalynxfield_editor.php',
    'datalynxfield_editor_form' => '../field/editor/classes/datalynxfield_editor_form.php',
    'datalynxfield_editor_renderer' => '../field/editor/classes/datalynxfield_editor_renderer.php',
    'datalynxfield_entryauthor' => '../field/entryauthor/classes/datalynxfield_entryauthor.php',
    'datalynxfield_entryauthor_renderer' => '../field/entryauthor/classes/datalynxfield_entryauthor_renderer.php',
    'datalynxfield_entry' => '../field/entry/classes/datalynxfield_entry.php',
    'datalynxfield_entry_renderer' => '../field/entry/classes/datalynxfield_entry_renderer.php',
    'datalynxfield_entrygroup' => '../field/entrygroup/classes/datalynxfield_entrygroup.php',
    'datalynxfield_entrygroup_renderer' => '../field/entrygroup/classes/datalynxfield_entrygroup_renderer.php',
    'datalynxfield_entryteammemberprofilefield' => '../field/entryteammemberprofilefield/classes/datalynxfield_entryteammemberprofilefield.php',
    'datalynxfield_entryteammemberprofilefield_renderer' => '../field/entryteammemberprofilefield/classes/datalynxfield_entryteammemberprofilefield_renderer.php',
    'datalynxfield_entrytime' => '../field/entrytime/classes/datalynxfield_entrytime.php',
    'datalynxfield_entrytime_renderer' => '../field/entrytime/classes/datalynxfield_entrytime_renderer.php',
    'datalynxfield_fieldgroup' => '../field/fieldgroup/classes/datalynxfield_fieldgroup.php',
    'datalynxfield_fieldgroup_form' => '../field/fieldgroup/classes/datalynxfield_fieldgroup_form.php',
    'datalynxfield_fieldgroup_renderer' => '../field/fieldgroup/classes/datalynxfield_fieldgroup_renderer.php',
    'datalynxfield_file' => '../field/file/classes/datalynxfield_file.php',
    'datalynxfield_file_form' => '../field/file/classes/datalynxfield_file_form.php',
    'datalynxfield_file_renderer' => '../field/file/classes/datalynxfield_file_renderer.php',
    'datalynxfield_gradeitem' => '../field/gradeitem/classes/datalynxfield_gradeitem.php',
    'datalynxfield_gradeitem_form' => '../field/gradeitem/classes/datalynxfield_gradeitem_form.php',
    'datalynxfield_gradeitem_renderer' => '../field/gradeitem/classes/datalynxfield_gradeitem_renderer.php',
    'datalynxfield_identifier' => '../field/identifier/classes/datalynxfield_identifier.php',
    'datalynxfield_identifier_form' => '../field/identifier/classes/datalynxfield_identifier_form.php',
    'datalynxfield_identifier_renderer' => '../field/identifier/classes/datalynxfield_identifier_renderer.php',
    'datalynxfield_multiselect' => '../field/multiselect/classes/datalynxfield_multiselect.php',
    'datalynxfield_multiselect_form' => '../field/multiselect/classes/datalynxfield_multiselect_form.php',
    'datalynxfield_multiselect_renderer' => '../field/multiselect/classes/datalynxfield_multiselect_renderer.php',
    'datalynxfield_number' => '../field/number/classes/datalynxfield_number.php',
    'datalynxfield_number_form' => '../field/number/classes/datalynxfield_number_form.php',
    'datalynxfield_number_renderer' => '../field/number/classes/datalynxfield_number_renderer.php',
    'datalynxfield_picture' => '../field/picture/classes/datalynxfield_picture.php',
    'datalynxfield_picture_form' => '../field/picture/classes/datalynxfield_picture_form.php',
    'datalynxfield_picture_renderer' => '../field/picture/classes/datalynxfield_picture_renderer.php',
    'datalynxfield_radiobutton' => '../field/radiobutton/classes/datalynxfield_radiobutton.php',
    'datalynxfield_radiobutton_form' => '../field/radiobutton/classes/datalynxfield_radiobutton_form.php',
    'datalynxfield_radiobutton_renderer' => '../field/radiobutton/classes/datalynxfield_radiobutton_renderer.php',
    'datalynxfield_rating' => '../field/rating/classes/datalynxfield_rating.php',
    'datalynxfield_rating_renderer' => '../field/rating/classes/datalynxfield_rating_renderer.php',
    'datalynxfield_select' => '../field/select/classes/datalynxfield_select.php',
    'datalynxfield_select_form' => '../field/select/classes/datalynxfield_select_form.php',
    'datalynxfield_select_renderer' => '../field/select/classes/datalynxfield_select_renderer.php',
    'datalynxfield_status' => '../field/status/classes/datalynxfield_status.php',
    'datalynxfield_status_renderer' => '../field/status/classes/datalynxfield_status_renderer.php',
    'datalynxfield_tag' => '../field/tag/classes/datalynxfield_tag.php',
    'datalynxfield_tag_form' => '../field/tag/classes/datalynxfield_tag_form.php',
    'datalynxfield_tag_renderer' => '../field/tag/classes/datalynxfield_tag_renderer.php',
    'datalynxfield_teammemberselect' => '../field/teammemberselect/classes/datalynxfield_teammemberselect.php',
    'datalynxfield_teammemberselect_form' => '../field/teammemberselect/classes/datalynxfield_teammemberselect_form.php',
    'datalynxfield_teammemberselect_renderer' => '../field/teammemberselect/classes/datalynxfield_teammemberselect_renderer.php',
    'datalynxfield_textarea' => '../field/textarea/classes/datalynxfield_textarea.php',
    'datalynxfield_textarea_form' => '../field/textarea/classes/datalynxfield_textarea_form.php',
    'datalynxfield_textarea_renderer' => '../field/textarea/classes/datalynxfield_textarea_renderer.php',
    'datalynxfield_text' => '../field/text/classes/datalynxfield_text.php',
    'datalynxfield_text_form' => '../field/text/classes/datalynxfield_text_form.php',
    'datalynxfield_text_renderer' => '../field/text/classes/datalynxfield_text_renderer.php',
    'datalynxfield_time' => '../field/time/classes/datalynxfield_time.php',
    'datalynxfield_time_form' => '../field/time/classes/datalynxfield_time_form.php',
    'datalynxfield_time_renderer' => '../field/time/classes/datalynxfield_time_renderer.php',
    'datalynxfield_url' => '../field/url/classes/datalynxfield_url.php',
    'datalynxfield_url_form' => '../field/url/classes/datalynxfield_url_form.php',
    'datalynxfield_url_renderer' => '../field/url/classes/datalynxfield_url_renderer.php',
    'datalynxfield_userinfo' => '../field/userinfo/classes/datalynxfield_userinfo.php',
    'datalynxfield_userinfo_form' => '../field/userinfo/classes/datalynxfield_userinfo_form.php',
    'datalynxfield_userinfo_renderer' => '../field/userinfo/classes/datalynxfield_userinfo_renderer.php',
    'datalynxfield_youtube' => '../field/youtube/classes/datalynxfield_youtube.php',
    'datalynxfield_youtube_form' => '../field/youtube/classes/datalynxfield_youtube_form.php',
    'datalynxfield_youtube_renderer' => '../field/youtube/classes/datalynxfield_youtube_renderer.php',
];
