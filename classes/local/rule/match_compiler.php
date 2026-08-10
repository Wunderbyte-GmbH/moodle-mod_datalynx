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

namespace mod_datalynx\local\rule;

use mod_datalynx\datalynx;
use mod_datalynx\local\field\datalynxfield_base;
use mod_datalynx\local\ride\itinerary;
use mod_datalynx\local\ride\matcher;
use stdClass;

/**
 * Turns "matches the entry the event touched" into an ordinary search.
 *
 * A rule can look for other entries that agree or disagree with the entry an event just touched -
 * the same organiser, a different ride type, a departure within the hour, a route passing the same
 * way. By the time the rule fires, the values that entry holds are known, so most of those
 * relations can be stated as a plain search criterion and handed to the filter engine, which is
 * the same code path that already evaluates a rule's trigger conditions.
 *
 * One relation cannot be stated that way and comes back as a post-filter over the candidate ids
 * instead: an itinerary corridor, because it has to run in either direction and the filter engine
 * holds only one flat AND group and one flat OR group.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class match_compiler {
    /** @var string Holds the same value as the entry that triggered the rule. */
    const OP_SAME = 'same';

    /** @var string Holds a different value from the entry that triggered the rule. */
    const OP_DIFFERENT = 'different';

    /** @var string Holds a value within a tolerance of the triggering entry's value. */
    const OP_WITHIN = 'within';

    /** @var string Its route and the triggering entry's route can carry one another. */
    const OP_ROUTE = 'route';

    /** @var string The candidate's route has to cover the triggering entry's two ends. */
    const DIRECTION_FORWARD = 'forward';

    /** @var string The triggering entry's route has to cover the candidate's two ends. */
    const DIRECTION_REVERSE = 'reverse';

    /** @var string Either of the two above. */
    const DIRECTION_EITHER = 'either';

    /** @var datalynx The datalynx instance the criteria belong to. */
    protected datalynx $dlx;

    /** @var array Stored content values of the triggering entry, by field id. */
    private array $subjectvalues = [];

    /**
     * Constructor.
     *
     * @param datalynx $dlx
     */
    public function __construct(datalynx $dlx) {
        $this->dlx = $dlx;
    }

    /**
     * Fields of this instance that can express at least one relation to another entry.
     *
     * Internal fields (status, approve, the entry author and timestamps) are left out: they are
     * evaluated on the entry row rather than on stored content, and "the same status as this
     * entry" is not a useful thing to match on.
     *
     * @param datalynx $dlx
     * @return array field id => field name
     */
    public static function eligible_fields(datalynx $dlx): array {
        $eligible = [];
        foreach ($dlx->get_fields() as $fieldid => $field) {
            if (!is_numeric($fieldid) || $field::is_internal() || !$field->supported_relative_criteria()) {
                continue;
            }
            $eligible[(int) $fieldid] = $field->name();
        }
        asort($eligible);

        return $eligible;
    }

    /**
     * Relations one field offers, labelled for the settings form.
     *
     * @param datalynxfield_base $field
     * @return array relation => label
     */
    public static function operators_for(datalynxfield_base $field): array {
        $labels = [
            self::OP_SAME => get_string('matchrelsame', 'datalynxrule_eventnotification'),
            self::OP_DIFFERENT => get_string('matchreldifferent', 'datalynxrule_eventnotification'),
            self::OP_WITHIN => get_string('matchrelwithin', 'datalynxrule_eventnotification'),
            self::OP_ROUTE => get_string('matchrelroute', 'datalynxrule_eventnotification'),
        ];

        return array_intersect_key($labels, array_flip($field->supported_relative_criteria()));
    }

    /**
     * The directions an itinerary criterion can be matched in, labelled for the settings form.
     *
     * @return array direction => label
     */
    public static function directions(): array {
        return [
            self::DIRECTION_EITHER => get_string('matchdirectioneither', 'datalynxrule_eventnotification'),
            self::DIRECTION_FORWARD => get_string('matchdirectionforward', 'datalynxrule_eventnotification'),
            self::DIRECTION_REVERSE => get_string('matchdirectionreverse', 'datalynxrule_eventnotification'),
        ];
    }

    /**
     * Compile the stored criteria against the entry an event touched.
     *
     * @param array $criteria rows of ['fieldid' => int, 'op' => string, 'tolerance' => float,
     *                        'radius' => float, 'direction' => string]
     * @param int $subjectentryid the entry the event touched
     * @return array{customsearch: array, postfilters: array, impossible: bool}
     *         `impossible` means the triggering entry cannot match anything - it holds no value
     *         for a field a criterion needs, or the criterion is misconfigured - and the caller
     *         must notify nobody rather than fall back to matching everything.
     */
    public function compile(array $criteria, int $subjectentryid): array {
        $customsearch = [];
        $postfilters = [];
        $fields = $this->dlx->get_fields();

        foreach ($criteria as $criterion) {
            $fieldid = (int) ($criterion['fieldid'] ?? 0);
            $relation = (string) ($criterion['op'] ?? '');
            if (!$fieldid || !isset($fields[$fieldid])) {
                return $this->impossible();
            }
            $field = $fields[$fieldid];
            if (!in_array($relation, $field->supported_relative_criteria(), true)) {
                return $this->impossible();
            }

            if ($field->type === 'itinerary') {
                $postfilter = $this->compile_itinerary($field, $criterion, $relation, $subjectentryid);
                if ($postfilter === null) {
                    return $this->impossible();
                }
                if ($postfilter !== false) {
                    $postfilters[] = $postfilter;
                }
                continue;
            }

            $row = $field->compile_relative_criterion(
                $relation,
                (string) $this->subject_value($fieldid, $subjectentryid),
                ['tolerance' => $this->tolerance_of($criterion)]
            );
            if ($row === null) {
                return $this->impossible();
            }
            // Criteria are collected per field id, in one AND group: every row has to hold.
            $customsearch[$fieldid]['AND'][] = $row;
        }

        return ['customsearch' => $customsearch, 'postfilters' => $postfilters, 'impossible' => false];
    }

    /**
     * Narrow a candidate set by one post-filter.
     *
     * @param int[] $candidates entry ids
     * @param stdClass $filter a descriptor produced by {@see self::compile()}
     * @return int[] the candidates that survive
     */
    public function apply_postfilter(array $candidates, stdClass $filter): array {
        if (!$candidates) {
            return [];
        }

        return $this->apply_route_filter($candidates, $filter);
    }

    /**
     * Candidates whose route and the triggering entry's route can carry one another.
     *
     * Forward - "their route covers my two ends" - is a single corridor query restricted to the
     * candidates. Reverse - "my route covers their two ends" - has per-candidate coordinates and
     * so needs one query each; it is the same test the ride matching has always run, only the
     * other way round.
     *
     * @param int[] $candidates
     * @param stdClass $filter
     * @return int[]
     */
    private function apply_route_filter(array $candidates, stdClass $filter): array {
        global $DB;

        $keep = [];
        if ($filter->direction !== self::DIRECTION_REVERSE) {
            $keep = matcher::find_matching_entries(
                $filter->fieldid,
                $filter->from,
                $filter->to,
                $filter->radius,
                $candidates
            );
        }

        if ($filter->direction !== self::DIRECTION_FORWARD) {
            foreach (array_diff($candidates, $keep) as $candidateid) {
                $journey = itinerary::from_json((string) $DB->get_field(
                    'datalynx_contents',
                    'content',
                    ['entryid' => $candidateid, 'fieldid' => $filter->fieldid],
                    IGNORE_MISSING
                ));
                if (matcher::entry_covers_request($filter->fieldid, $filter->subjectentryid, $journey, $filter->radius)) {
                    $keep[] = (int) $candidateid;
                }
            }
        }

        return array_values(array_unique(array_map('intval', $keep)));
    }

    /**
     * Build the post-filter descriptor for an itinerary criterion.
     *
     * @param datalynxfield_base $field
     * @param array $criterion
     * @param string $relation
     * @param int $subjectentryid
     * @return stdClass|false|null the post-filter, false when the criterion does not apply to this
     *                             entry and should simply be dropped, null when the entry cannot
     *                             match anything at all
     */
    private function compile_itinerary(
        datalynxfield_base $field,
        array $criterion,
        string $relation,
        int $subjectentryid
    ) {
        $fieldid = (int) $field->id();

        $journey = itinerary::from_json((string) $this->subject_value($fieldid, $subjectentryid));
        if (!$journey->is_route()) {
            return null;
        }
        $radius = (float) ($criterion['radius'] ?? 0);
        $direction = (string) ($criterion['direction'] ?? self::DIRECTION_EITHER);

        return (object) [
            'type' => 'route',
            'fieldid' => $fieldid,
            'subjectentryid' => $subjectentryid,
            'radius' => $radius > 0 ? $radius : $field->get_match_radius(),
            'direction' => array_key_exists($direction, self::directions()) ? $direction : self::DIRECTION_EITHER,
            'from' => $journey->first(),
            'to' => $journey->last(),
        ];
    }

    /**
     * The tolerance of a criterion in the field's own unit (seconds for times and durations).
     *
     * @param array $criterion
     * @return float
     */
    private function tolerance_of(array $criterion): float {
        $unit = (float) ($criterion['unit'] ?? 1);

        return (float) ($criterion['tolerance'] ?? 0) * ($unit > 0 ? $unit : 1);
    }

    /**
     * A stored content value of the triggering entry, read once per compile run.
     *
     * @param int $fieldid
     * @param int $entryid
     * @param string $column the content column to read
     * @return string|null
     */
    private function subject_value(int $fieldid, int $entryid, string $column = 'content'): ?string {
        global $DB;

        $key = "{$fieldid}:{$column}";
        if (!array_key_exists($key, $this->subjectvalues)) {
            $value = $DB->get_field(
                'datalynx_contents',
                $column,
                ['entryid' => $entryid, 'fieldid' => $fieldid],
                IGNORE_MISSING
            );
            $this->subjectvalues[$key] = $value === false ? null : $value;
        }

        return $this->subjectvalues[$key];
    }

    /**
     * The result that tells the caller to notify nobody.
     *
     * @return array{customsearch: array, postfilters: array, impossible: bool}
     */
    private function impossible(): array {
        return ['customsearch' => [], 'postfilters' => [], 'impossible' => true];
    }
}
