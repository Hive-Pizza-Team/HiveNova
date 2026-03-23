<?php

namespace HiveNova\Repository;

use HiveNova\Core\Database;

class MessageRepository
{
    /**
     * Count messages for a user. Pass category=100 for all categories.
     * Pass category=999 for sent messages (message_sender).
     */
    public static function countMessages(int $userId, int $category, bool $deletedOnly = false): int
    {
        $db = Database::get();

        if ($category === 999) {
            $sql = 'SELECT COUNT(*) as c FROM %%MESSAGES%% WHERE message_sender = :userId AND message_type != 50 AND message_deleted IS NULL;';
            $params = [':userId' => $userId];
        } elseif ($category === 100) {
            $sql = 'SELECT COUNT(*) as c FROM %%MESSAGES%% WHERE message_owner = :userId AND message_deleted IS NULL;';
            $params = [':userId' => $userId];
        } else {
            $sql = 'SELECT COUNT(*) as c FROM %%MESSAGES%% WHERE message_owner = :userId AND message_type = :category AND message_deleted IS NULL;';
            $params = [':userId' => $userId, ':category' => $category];
        }

        return (int) $db->selectSingle($sql, $params, 'c');
    }

    /**
     * Fetch a paged list of messages. Category 999 = sent, 100 = all.
     */
    public static function getMessagesPaged(int $userId, int $category, int $offset, int $limit): array
    {
        $db = Database::get();

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
                    WHERE message_owner = :userId AND message_deleted IS NULL
                    ORDER BY message_time DESC
                    LIMIT :offset, :limit;';
            $params = [':userId' => $userId, ':offset' => $offset, ':limit' => $limit];
        } else {
            $sql = 'SELECT message_id, message_time, message_from, message_subject, message_sender, message_type, message_unread, message_text
                    FROM %%MESSAGES%%
                    WHERE message_owner = :userId AND message_type = :category AND message_deleted IS NULL
                    ORDER BY message_time DESC
                    LIMIT :offset, :limit;';
            $params = [':userId' => $userId, ':category' => $category, ':offset' => $offset, ':limit' => $limit];
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
