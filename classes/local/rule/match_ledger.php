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

use dml_exception;

/**
 * Record of the entry pairs whose authors have already been told about each other.
 *
 * Both entries of a pair are saved and edited independently, and each save re-runs the search, so
 * without a record the same two people would hear about each other again on every edit. A pair is
 * held as its two entry ids in ascending order, which makes it the same pair whichever side the
 * search started from.
 *
 * The scope is a free token rather than a rule id: matching the two sides of a relationship often
 * takes one rule per side (an offer looks for requests, a request looks for offers), and those
 * rules have to share one ledger or each pair would be announced twice. A token also survives a
 * backup and restore, where rule ids do not.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class match_ledger {
    /** @var string The table holding the announced pairs. */
    const TABLE = 'datalynx_rule_matches';

    /** @var int The datalynx instance the pairs belong to. */
    protected int $dataid;

    /** @var string The announcement scope shared by the rules writing to this ledger. */
    protected string $scope;

    /**
     * Constructor.
     *
     * @param int $dataid
     * @param string $scope
     */
    public function __construct(int $dataid, string $scope) {
        $this->dataid = $dataid;
        $this->scope = substr($scope, 0, 64);
    }

    /**
     * Record a pair, reporting whether it is news.
     *
     * @param int $entrya
     * @param int $entryb
     * @return bool true when the pair had not been announced yet and the caller should notify
     */
    public function remember(int $entrya, int $entryb): bool {
        global $DB;

        $key = $this->key($entrya, $entryb);
        if ($DB->record_exists(self::TABLE, $key)) {
            return false;
        }

        try {
            $DB->insert_record(self::TABLE, (object) ($key + ['timenotified' => time()]));
        } catch (dml_exception $e) {
            // Lost the race against a concurrent sweep - the unique index decides, and the sweep
            // that won is doing the notifying.
            return false;
        }

        return true;
    }

    /**
     * Forget one pair, so that it counts as news again should it match later.
     *
     * @param int $entrya
     * @param int $entryb
     */
    public function forget(int $entrya, int $entryb): void {
        global $DB;

        $DB->delete_records(self::TABLE, $this->key($entrya, $entryb));
    }

    /**
     * Forget every pair of one entry except the ones given.
     *
     * Called after a search with the entries it found, so that a counterpart that has dropped out
     * - the departure moved, the route changed - is announced again if it comes back.
     *
     * @param int $entryid
     * @param int[] $keep the counterparts that still match
     */
    public function forget_missing(int $entryid, array $keep): void {
        global $DB;

        $params = ['dataid' => $this->dataid, 'scope' => $this->scope, 'low' => $entryid, 'high' => $entryid];
        $where = 'dataid = :dataid AND scope = :scope AND (entrylow = :low OR entryhigh = :high)';

        if ($keep) {
            [$insql, $inparams] = $DB->get_in_or_equal(array_map('intval', $keep), SQL_PARAMS_NAMED, 'keep', false);
            // The partner is whichever of the two columns is not this entry.
            $where .= " AND (entrylow + entryhigh - {$entryid}) $insql";
            $params += $inparams;
        }

        $DB->delete_records_select(self::TABLE, $where, $params);
    }

    /**
     * Forget every pair involving one entry, in every scope of this instance.
     *
     * @param int $entryid
     */
    public function forget_entry(int $entryid): void {
        global $DB;

        $DB->delete_records_select(
            self::TABLE,
            'dataid = :dataid AND (entrylow = :low OR entryhigh = :high)',
            ['dataid' => $this->dataid, 'low' => $entryid, 'high' => $entryid]
        );
    }

    /**
     * Forget everything recorded under this scope.
     */
    public function forget_scope(): void {
        global $DB;

        $DB->delete_records(self::TABLE, ['dataid' => $this->dataid, 'scope' => $this->scope]);
    }

    /**
     * The stored representation of a pair.
     *
     * @param int $entrya
     * @param int $entryb
     * @return array
     */
    private function key(int $entrya, int $entryb): array {
        return [
            'dataid' => $this->dataid,
            'scope' => $this->scope,
            'entrylow' => min($entrya, $entryb),
            'entryhigh' => max($entrya, $entryb),
        ];
    }
}
