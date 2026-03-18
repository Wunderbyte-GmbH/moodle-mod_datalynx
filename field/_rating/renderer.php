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
 * @subpackage _rating
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/field/renderer.php");

/**
 * Internal rating field renderer.
 */
class datalynxfield__rating_renderer extends datalynxfield_renderer {
    /**
     * Returns tag replacements for the field.
     *
     * @param array $tags
     * @param object $entry
     * @param array $options
     * @return array
     */
    public function replacements(array $tags = null, $entry = null, array $options = null) {
        global $CFG, $DB;

        $field = $this->_field;
        $edit = !empty($options['edit']) ? $options['edit'] : false;

        // If edit mode or rating not active return.
        if ($edit || (!$this->_field->df()->data->rating)) {
            if ($tags) {
                $replacements = [];
                foreach ($tags as $tag) {
                    switch (trim($tag, '@')) {
                        case '##ratings:count##':
                        case '##ratings:avg##':
                        case '##ratings:max##':
                        case '##ratings:min##':
                        case '##ratings:sum##':
                            $str = '-';
                            break;
                        default:
                            $str = '';
                    }
                    $replacements[$tag] = $str ? ['html', $str] : '';
                }

                return $replacements;
            } else {
                return null;
            }
        }

        require_once("$CFG->dirroot/mod/datalynx/field/_rating/lib.php");
        $rm = new datalynx_rating_manager();
        // Get entry rating objects.
        if ($entry->id > 0) {
            $options = new stdClass();
            $options->context = $field->df()->context;
            $options->component = 'mod_datalynx';
            $options->ratingarea = 'entry';
            // Ugly hack to work around the exception in generate_settings.
            $options->aggregate = RATING_AGGREGATE_COUNT;
            // TODO: MDL-0000 check when scaleid is empty.
            $options->scaleid = !empty($entry->scaleid) ? $entry->scaleid : $field->df()->data->rating;

            $rec = new stdClass();
            $rec->itemid = $entry->id;
            $rec->context = $field->df()->context;
            $rec->component = 'mod_datalynx';
            $rec->ratingarea = 'entry';
            $rec->settings = $rm->get_rating_settings_object($options);
            $rec->aggregate = array_keys($rm->get_aggregate_types());
            $rec->scaleid = $entry->scaleid;
            $rec->userid = $entry->ratinguserid;
            $rec->id = $entry->ratingid;
            $rec->usersrating = $entry->usersrating;
            $rec->numratings = $entry->numratings;
            $rec->avgratings = $entry->avgratings;
            $rec->sumratings = $entry->sumratings;
            $rec->maxratings = $entry->maxratings;
            $rec->minratings = $entry->minratings;

            $entry->rating = $rm->get_rating_object($entry, $rec);

            $aggravg = round($entry->rating->aggregate[datalynxfield__rating::AGGREGATE_AVG] ?: 0, 2);
            $aggrmax = round($entry->rating->aggregate[datalynxfield__rating::AGGREGATE_MAX] ?: 0, 2);
            $aggrmin = round($entry->rating->aggregate[datalynxfield__rating::AGGREGATE_MIN] ?: 0, 2);
            $aggrsum = round($entry->rating->aggregate[datalynxfield__rating::AGGREGATE_SUM] ?: 0, 2);

            // Get all ratings for inline view.
            if (in_array('##ratings:viewinline##', $tags)) {
                static $allratings = false;
                static $ratingrecords = null;
                if (!$allratings) {
                    $allratings = true;
                    [$sql, $params] = $rm->get_sql_all($options, false);
                    $ratingrecords = $DB->get_records_sql($sql, $params);
                }
                if ($ratingrecords) {
                    foreach ($ratingrecords as $recordid => $raterecord) {
                        if ($raterecord->itemid < $entry->id) {
                            continue;
                        }
                        // Break if we already found the respective records.
                        if ($raterecord->itemid > $entry->id) {
                            continue;
                        }
                        // Attach the rating record to the entry.
                        if (!isset($entry->rating->records)) {
                            $entry->rating->records = [];
                        }
                        $entry->rating->records[$recordid] = $raterecord;
                    }
                }
            }
        }

        // No edit mode for this field so just return html.
        $replacements = [];
        foreach ($tags as $tag) {
            if ($entry->id > 0 && !empty($entry->rating)) {
                switch (trim($tag, '@')) {
                    case '##ratings:count##':
                        $str = !empty($entry->rating->count) ? $entry->rating->count : '-';
                        break;
                    case '##ratings:avg##':
                        $str = !empty($aggravg) ? $aggravg : '-';
                        break;
                    case '##ratings:max##':
                        $str = !empty($aggrmax) ? $aggrmax : '-';
                        break;
                    case '##ratings:min##':
                        $str = !empty($aggrmin) ? $aggrmin : '-';
                        break;
                    case '##ratings:sum##':
                        $str = !empty($aggrsum) ? $aggrsum : '-';
                        break;
                    case '##ratings:view##':
                    case '##ratings:viewurl##':
                        $str = $this->display_view($entry, $tag);
                        break;
                    case '##ratings:viewinline##':
                        $str = $this->display_view_inline($entry);
                        break;
                    case '##ratings:rate##':
                        $str = $this->render_rating($entry);
                        break;
                    case '##ratings:avg:bar##':
                        $str = $this->display_bar($entry, $aggravg);
                        break;
                    case '##ratings:avg:star##':
                        $str = $this->display_star($entry, $aggravg);
                        break;
                    default:
                        $str = '';
                }
                $replacements[$tag] = ['html', $str];
            }
        }
        return $replacements;
    }

    /**
     * Get aggregate types used in the field.
     *
     * @param array $patterns
     * @return array|null
     */
    public function get_aggregations($patterns) {
        $aggr = [datalynxfield__rating::AGGREGATE_AVG => '##ratings:avg##',
                datalynxfield__rating::AGGREGATE_MAX => '##ratings:max##',
                datalynxfield__rating::AGGREGATE_MIN => '##ratings:min##',
                datalynxfield__rating::AGGREGATE_SUM => '##ratings:sum##'];
        if ($aggregations = array_intersect($aggr, $patterns)) {
            return array_keys($aggregations);
        } else {
            return null;
        }
    }

    /**
     * Display rating view link.
     *
     * @param object $entry
     * @param string $tag
     * @return string
     */
    protected function display_view($entry, $tag) {
        if (isset($entry->rating)) {
            $rating = $entry->rating;
            if (
                $rating->settings->permissions->viewall &&
                    $rating->settings->pluginpermissions->viewall
            ) {
                $nonpopuplink = $rating->get_view_ratings_url();
                if (trim($tag, '@') == '##ratings:viewurl##') {
                    return $nonpopuplink;
                }
                $str = get_string('viewratings', 'datalynx');
                return $this->output->action_link($nonpopuplink, $str, new popup_action('click', $nonpopuplink));
            }
        }
        return '';
    }

    /**
     * Display rating view inline.
     *
     * @param object $entry
     * @return string
     */
    protected function display_view_inline($entry) {
        if (!isset($entry->rating) || empty($entry->rating->records)) {
            return '';
        }
        $str = '';
        foreach ($entry->rating->records as $raterecord) {
            $user = new stdClass();
            $user->id = $raterecord->userid;
            $user->firstname = $raterecord->uidfirstname;
            $user->lastname = $raterecord->uidlastname;
            $user->imagealt = $raterecord->uidimagealt;
            $user->picture = $raterecord->uidpicture;
            $user->email = $raterecord->uidemail;

            $str .= html_writer::start_tag('div', ['class' => 'datalynx_rating_inline']);
            $str .= $this->output->user_picture($user, ['size' => 16]);
            $str .= ' ' . fullname($user) . ': ';
            if ($entry->rating->settings->scale->isnumeric) {
                $str .= $raterecord->rating;
            } else {
                $str .= $entry->rating->settings->scale->scaleitems[$raterecord->rating];
            }
            $str .= html_writer::end_tag('div');
        }

        return $str;
    }

    /**
     * Display aggregate as bar.
     *
     * @param object $entry
     * @param float $aggravg
     * @return string
     */
    protected function display_bar($entry, $aggravg) {
        if (isset($entry->rating) && $aggravg) {
            $rating = $entry->rating;

            $width = round($aggravg / $rating->settings->scale->max * 100);
            $bar = html_writer::tag(
                'div',
                '.',
                ['style' => "width:$width%;height:100%;background:gold;color:gold",
                ]
            );
            return $bar;
        }
        return '';
    }

    /**
     * Display aggregate as star.
     *
     * @param object $entry
     * @param float $aggravg
     * @return string
     */
    protected function display_star($entry, $aggravg) {
        $str = '';
        if ($entry->rating->settings->scale->isnumeric) {
            $max = $entry->rating->settings->scale->max;
            $fullstar = $this->output->pix_icon('t/star', '');
            $halfstar = $this->output->pix_icon('t/starhalf', '');
            $emptystar = $this->output->pix_icon('t/starempty', '');

            for ($i = 1; $i <= $max; $i++) {
                if ($i <= $aggravg) {
                    $str .= $fullstar;
                } else if ($i - 0.5 <= $aggravg) {
                    $str .= $halfstar;
                } else {
                    $str .= $emptystar;
                }
            }
        }
        return $str;
    }

    /**
     * Render rating field.
     *
     * @param object $entry
     * @return string
     */
    protected function render_rating($entry) {
        if (!empty($entry->rating)) {
            return $this->output->render($entry->rating);
        }
        return '';
    }

    /**
     * Returns tag patterns.
     *
     * @return array
     */
    protected function patterns() {
        $fieldinternalname = $this->_field->get('internalname');
        $cat = get_string('ratings', 'datalynx');

        $patterns = [];
        switch ($fieldinternalname) {
            case 'ratings':
                $patterns['##ratings:rate##'] = [true, $cat,
                ];
                $patterns['##ratings:view##'] = [true, $cat,
                ];
                $patterns['##ratings:viewurl##'] = [false,
                ];
                $patterns['##ratings:viewinline##'] = [true, $cat,
                ];
                break;
            case 'avgratings':
                $patterns['##ratings:avg##'] = [true, $cat,
                ];
                $patterns['##ratings:avg:bar##'] = [false,
                ];
                $patterns['##ratings:avg:star##'] = [false,
                ];
                break;
            case 'countratings':
                $patterns['##ratings:count##'] = [true, $cat,
                ];
                break;
            case 'maxratings':
                $patterns['##ratings:max##'] = [true, $cat,
                ];
                break;
            case 'minratings':
                $patterns['##ratings:min##'] = [true, $cat,
                ];
                break;
            case 'sumratings':
                $patterns['##ratings:sum##'] = [true, $cat,
                ];
                break;
        }

        return $patterns;
    }
}
