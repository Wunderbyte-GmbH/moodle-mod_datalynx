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
 * @package mod_datalynx
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$capabilities = [

        'mod/datalynx:addinstance' => [
                'riskbitmask' => RISK_XSS,
                'captype' => 'write',
                'contextlevel' => CONTEXT_COURSE,
                'archetypes' => ['editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW],
                'clonepermissionsfrom' => 'moodle/course:manageactivities',
        ],

        // Manage templates: do anything in the datalynx.
        'mod/datalynx:managetemplates' => [
                'riskbitmask' => RISK_SPAM | RISK_XSS,
                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW],
        ],

        // View index.
        'mod/datalynx:viewindex' => [
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW],
        ],

        // ENTRIES.
        // Manage entries: view, write, delete, export etc.
        'mod/datalynx:manageentries' => [
                'riskbitmask' => RISK_SPAM | RISK_DATALOSS,
                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW],
        ],

        // Approve an entry.
        'mod/datalynx:approve' => [
                'riskbitmask' => RISK_SPAM,
                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW],
        ],

        // View entries.
        'mod/datalynx:viewentry' => [
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['frontpage' => CAP_ALLOW, // Needed for datalynxs on the frontpage.
                        'guest' => CAP_ALLOW, 'user' => CAP_ALLOW, 'student' => CAP_ALLOW, 'teacher' => CAP_ALLOW,
                        'editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW],
        ],

        // Write entries.
        'mod/datalynx:writeentry' => [
                'riskbitmask' => RISK_SPAM,
                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['student' => CAP_ALLOW, 'teacher' => CAP_ALLOW,
                        'editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW],
        ],

        // View anonymous entries.
        'mod/datalynx:viewanonymousentry' => [
                'captype' => 'read', 'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['teacher' => CAP_ALLOW, 'manager' => CAP_ALLOW],
        ],

        // Export entries.
        'mod/datalynx:exportentry' => [
                'riskbitmask' => RISK_PERSONAL,
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['manager' => CAP_ALLOW, 'teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW],
        ],

        // Export own entries.
        'mod/datalynx:exportownentry' => [
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['manager' => CAP_ALLOW, 'teacher' => CAP_ALLOW,
                        'editingteacher' => CAP_ALLOW, 'student' => CAP_ALLOW],
        ],

        // Export all entries.
        'mod/datalynx:exportallentries' => [
                'riskbitmask' => RISK_PERSONAL,
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['manager' => CAP_ALLOW, 'teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW],
        ],

        // COMMENTS.
        // Manage comments.
        'mod/datalynx:managecomments' => [
                'riskbitmask' => RISK_SPAM,
                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW],
        ],

        // Comment on entries.
        'mod/datalynx:comment' => [
                'riskbitmask' => RISK_SPAM,
                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['student' => CAP_ALLOW, 'teacher' => CAP_ALLOW,
                        'editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW],
        ],

        // RATINGS.
        // Manage ratings.
        'mod/datalynx:manageratings' => [
                'riskbitmask' => RISK_SPAM,
                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW],
        ],

        // Rate entries.
        'mod/datalynx:rate' => [
                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW],
        ],

        // View entry ratings.
        'mod/datalynx:ratingsview' => [
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW],
        ],

        // Moodle.org: Allows the user to view aggregated ratings made on other people's items (but not their own).
        'mod/datalynx:ratingsviewany' => [
                'riskbitmask' => RISK_PERSONAL,
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW],
                'clonepermissionsfrom' => 'mod/datalynx:ratingsview',
        ],

        // Moodle.org: Allows the user to see individual ratings.
        'mod/datalynx:ratingsviewall' => [
                'riskbitmask' => RISK_PERSONAL,
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW],
                'clonepermissionsfrom' => 'mod/datalynx:ratingsview',
        ],

        // PRESETS.
        // Manage user presets.
        'mod/datalynx:managepresets' => [
                'riskbitmask' => RISK_SPAM | RISK_XSS,
                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['manager' => CAP_ALLOW],
        ],

        // View all user presets.
        'mod/datalynx:presetsviewall' => [
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW],
        ],

        // NOTIFICATIONS.
        // Notified on added entry.
        'mod/datalynx:notifyentryadded' => [
                'riskbitmask' => RISK_PERSONAL,
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW],
        ],

        // Notified on updated entry.
        'mod/datalynx:notifyentryupdated' => [
                'riskbitmask' => RISK_PERSONAL,
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW],
        ],

        // Notified on deleted entry.
        'mod/datalynx:notifyentrydeleted' => [
                'riskbitmask' => RISK_PERSONAL,
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW],
        ],

        // Notified on approved entry.
        'mod/datalynx:notifyentryapproved' => [
                'riskbitmask' => RISK_PERSONAL,
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW],
        ],

        // Notified on disapproved entry.
        'mod/datalynx:notifyentrydisapproved' => [
                'riskbitmask' => RISK_PERSONAL,
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW],
        ],

        // Notified on added comment.
        'mod/datalynx:notifycommentadded' => [
                'riskbitmask' => RISK_PERSONAL,
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW],
        ],

        // Notified on added rating.
        'mod/datalynx:notifyratingadded' => [
                'riskbitmask' => RISK_PERSONAL,
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW],
        ],

        // Notified on updated rating.
        'mod/datalynx:notifyratingupdated' => [
                'riskbitmask' => RISK_PERSONAL,
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW],
        ],

        // Notified on updated team.
        'mod/datalynx:notifyteamupdated' => [
                'riskbitmask' => RISK_PERSONAL,
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['manager' => CAP_ALLOW, 'teacher' => CAP_ALLOW,
                        'editingteacher' => CAP_ALLOW, 'student' => CAP_ALLOW],
        ],

        // Allow viewing of entries marked as draft.
        'mod/datalynx:viewdrafts' => [
                'riskbitmask' => RISK_PERSONAL,
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['manager' => CAP_ALLOW],
        ],

        // Allow viewing of entries marked as draft.
        'mod/datalynx:editrestrictedfields' => [
                'riskbitmask' => RISK_PERSONAL,
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['manager' => CAP_ALLOW],
        ],

        'mod/datalynx:viewprivilegeadmin' => [
                'riskbitmask' => RISK_PERSONAL,
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => [],
        ],

        'mod/datalynx:viewprivilegemanager' => [
                'riskbitmask' => RISK_PERSONAL,
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['manager' => CAP_ALLOW],
        ],

        'mod/datalynx:viewprivilegeteacher' => [
                'riskbitmask' => RISK_PERSONAL,
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW],
        ],

        'mod/datalynx:viewprivilegestudent' => [
                'riskbitmask' => RISK_PERSONAL,
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['student' => CAP_ALLOW],
        ],

        'mod/datalynx:viewprivilegeguest' => [
                'riskbitmask' => RISK_PERSONAL,
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['guest' => CAP_ALLOW, 'user' => CAP_ALLOW],
        ],

        'mod/datalynx:editprivilegeadmin' => [
                'riskbitmask' => RISK_PERSONAL, RISK_DATALOSS,
                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => [],
        ],

        'mod/datalynx:editprivilegemanager' => [
                'riskbitmask' => RISK_PERSONAL, RISK_DATALOSS,
                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['manager' => CAP_ALLOW],
        ],

        'mod/datalynx:editprivilegeteacher' => [
                'riskbitmask' => RISK_PERSONAL, RISK_DATALOSS,
                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW],
        ],

        'mod/datalynx:editprivilegestudent' => [
                'riskbitmask' => RISK_PERSONAL, RISK_DATALOSS,
                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['student' => CAP_ALLOW],
        ],

        'mod/datalynx:editprivilegeguest' => [
                'riskbitmask' => RISK_PERSONAL, RISK_DATALOSS,
                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['guest' => CAP_ALLOW, 'user' => CAP_ALLOW],
        ],

        'mod/datalynx:viewstatistics' => [
                'riskbitmask' => RISK_PERSONAL,
                'captype' => 'read',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['manager' => CAP_ALLOW, 'teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW],
        ],

        'mod/datalynx:teamsubscribe' => [
                'riskbitmask' => RISK_PERSONAL,
                'captype' => 'write',
                'contextlevel' => CONTEXT_MODULE,
                'archetypes' => ['manager' => CAP_ALLOW, 'teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW,
                        'student' => CAP_ALLOW],
        ],
];
