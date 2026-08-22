<?php

namespace HiveNova\Repository;

use HiveNova\Core\Database;

class MessageRepository
{
    /**
     * Combat/expedition unit-loss line (fleet + defenses).
     *
     * Matches a non-zero attacker or defender loss inside a raportWin/Lose/Draw
     * span. Resource steal/debris use reportSteal/reportDebris (or raportSteal
     * on expedition mail), so they do not match. pretty_number may emit a bare
     * EU-formatted digit or wrap it in an ln span.
     */
    public const COMBAT_UNIT_LOSS_REGEXP = 'class="raport(Win|Lose|Draw)">[^<]*: ((<span class=\'ln\' data-n=\'[1-9][0-9]*\'>)|[1-9])';

    public const LOST_SPY_LIKE = '%spyReportLost%';

    /**
     * Extra WHERE fragment for the unit-loss / lost-spy list filter.
     *
     * Combat and expedition reports match when either side lost fleet or
     * defense units. Spy reports that lose probes use spyReportLost.
     *
     * @return array{sql: string, params: array<string, string>}
     */
    public static function lostFilterClause(int $category, string $filter): array
    {
        if ($filter !== 'lost') {
            return ['sql' => '', 'params' => []];
        }

        if ($category === 100) {
            return [
                'sql'    => ' AND (message_text REGEXP :lostNeedle OR message_text LIKE :lostNeedleSpy)',
                'params' => [
                    ':lostNeedle'    => self::COMBAT_UNIT_LOSS_REGEXP,
                    ':lostNeedleSpy' => self::LOST_SPY_LIKE,
                ],
            ];
        }

        if ($category === 0) {
            return [
                'sql'    => ' AND message_text LIKE :lostNeedle',
                'params' => [':lostNeedle' => self::LOST_SPY_LIKE],
            ];
        }

        if ($category === 3 || $category === 15) {
            return [
                'sql'    => ' AND message_text REGEXP :lostNeedle',
                'params' => [':lostNeedle' => self::COMBAT_UNIT_LOSS_REGEXP],
            ];
        }

        return ['sql' => '', 'params' => []];
    }

    /**
     * Whether stored combat-mail HTML reports any fleet or defense losses.
     */
    public static function combatMessageHasUnitLosses(string $html): bool
    {
        return preg_match('/' . self::COMBAT_UNIT_LOSS_REGEXP . '/', $html) === 1;
    }

    /**
     * Count messages for a user. Pass category=100 for all categories.
     * Pass category=999 for sent messages (message_sender).
     */
    public static function countMessages(int $userId, int $category, bool $deletedOnly = false, string $filter = ''): int
    {
        $db = Database::get();
        $lost = self::lostFilterClause($category, $filter);

        if ($category === 999) {
            $sql = 'SELECT COUNT(*) as c FROM %%MESSAGES%% WHERE message_sender = :userId AND message_type != 50 AND message_deleted IS NULL;';
            $params = [':userId' => $userId];
        } elseif ($category === 100) {
            $sql = 'SELECT COUNT(*) as c FROM %%MESSAGES%% WHERE message_owner = :userId AND message_deleted IS NULL' . $lost['sql'] . ';';
            $params = [':userId' => $userId] + $lost['params'];
        } else {
            $sql = 'SELECT COUNT(*) as c FROM %%MESSAGES%% WHERE message_owner = :userId AND message_type = :category AND message_deleted IS NULL' . $lost['sql'] . ';';
            $params = [':userId' => $userId, ':category' => $category] + $lost['params'];
        }

        return (int) $db->selectSingle($sql, $params, 'c');
    }

    /**
     * Fetch a paged list of messages. Category 999 = sent, 100 = all.
     */
    public static function getMessagesPaged(int $userId, int $category, int $offset, int $limit, string $filter = ''): array
    {
        $db = Database::get();
        $lost = self::lostFilterClause($category, $filter);

        if ($category === 999) {
            $sql = 'SELECT message_id, message_time,
                        CONCAT(username, \' [\', galaxy, \':\', `system`, \':\', planet, \']\') as message_from,
                        message_subject, message_sender, message_type, message_unread, message_text
                    FROM %%MESSAGES%% INNER JOIN %%USERS%% ON id = message_owner
                    WHERE message_sender = :userId AND message_type != 50 AND message_deleted IS NULL
                    ORDER BY message_time DESC
                    LIMIT :offset, :limit;';
            $params = [':userId' => $userId, ':offset' => $offset, ':limit' => $limit];
        } elseif ($category === 100) {
            $sql = 'SELECT message_id, message_time, message_from, message_subject, message_sender, message_type, message_unread, message_text
                    FROM %%MESSAGES%%
                    WHERE message_owner = :userId AND message_deleted IS NULL' . $lost['sql'] . '
                    ORDER BY message_time DESC
                    LIMIT :offset, :limit;';
            $params = [':userId' => $userId, ':offset' => $offset, ':limit' => $limit] + $lost['params'];
        } else {
            $sql = 'SELECT message_id, message_time, message_from, message_subject, message_sender, message_type, message_unread, message_text
                    FROM %%MESSAGES%%
                    WHERE message_owner = :userId AND message_type = :category AND message_deleted IS NULL' . $lost['sql'] . '
                    ORDER BY message_time DESC
                    LIMIT :offset, :limit;';
            $params = [':userId' => $userId, ':category' => $category, ':offset' => $offset, ':limit' => $limit] + $lost['params'];
        }

        return $db->select($sql, $params);
    }

    public static function markAsRead(int $userId, ?int $category = null): void
    {
        $db = Database::get();

        if ($category === null) {
            $db->update(
                'UPDATE %%MESSAGES%% SET message_unread = 0 WHERE message_owner = :userId;',
                [':userId' => $userId]
            );
        } else {
            $db->update(
                'UPDATE %%MESSAGES%% SET message_unread = 0 WHERE message_owner = :userId AND message_type = :category;',
                [':userId' => $userId, ':category' => $category]
            );
        }
    }
}
