<?php

namespace HiveNova\Repository;

use HiveNova\Core\Database;

class MessageRepository
{
    /**
     * Combat/expedition title class. Win mail still embeds raportLose on the
     * opponent's loss line, so a bare "%raportLose%" matches every non-draw.
     */
    public const LOST_COMBAT_TITLE_LIKE = '%target="_blank"><span class="raportLose">%';

    public const LOST_SPY_LIKE = '%spyReportLost%';

    /**
     * Extra WHERE fragment for the lost-battle / lost-spy list filter.
     *
     * Combat and expedition reports mark the player's own result on the
     * report-link title. Spy reports that lose probes use spyReportLost.
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
                'sql'    => ' AND (message_text LIKE :lostNeedle OR message_text LIKE :lostNeedleSpy)',
                'params' => [
                    ':lostNeedle'    => self::LOST_COMBAT_TITLE_LIKE,
                    ':lostNeedleSpy' => self::LOST_SPY_LIKE,
                ],
            ];
        }

        $pattern = match ($category) {
            0 => self::LOST_SPY_LIKE,
            3, 15 => self::LOST_COMBAT_TITLE_LIKE,
            default => null,
        };

        if ($pattern === null) {
            return ['sql' => '', 'params' => []];
        }

        return [
            'sql'    => ' AND message_text LIKE :lostNeedle',
            'params' => [':lostNeedle' => $pattern],
        ];
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
