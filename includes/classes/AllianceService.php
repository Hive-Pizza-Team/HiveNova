<?php

namespace HiveNova\Core;

/**
 * AllianceService — business-logic operations for alliances.
 *
 * Each method performs DB-mutating work and throws \RuntimeException on
 * business-rule violations so callers can present appropriate messages.
 */
class AllianceService
{
    /**
     * Create a new alliance and assign the founding user as its owner.
     *
     * @param int    $userId    ID of the founding user
     * @param int    $universe  Universe number
     * @param string $tag       Short alliance tag
     * @param string $name      Full alliance name
     * @param string $ownerRangeLabel  Translated label for the owner rank (e.g. $LNG['al_default_leader_name'])
     *
     * @return int  New alliance ID
     *
     * @throws \RuntimeException if tag or name is already taken
     */
    public static function createAlliance(int $userId, int $universe, string $tag, string $name, string $ownerRangeLabel): int
    {
        $db = Database::get();

        $sql = 'SELECT COUNT(*) as count FROM %%ALLIANCE%% WHERE ally_universe = :universe
                AND (ally_tag = :allianceTag OR ally_name = :allianceName);';
        $count = $db->selectSingle($sql, [
            ':universe'     => $universe,
            ':allianceTag'  => $tag,
            ':allianceName' => $name,
        ], 'count');

        if ($count != 0) {
            throw new \RuntimeException('tag_or_name_taken');
        }

        $sql = "INSERT INTO %%ALLIANCE%% SET
                ally_name         = :allianceName,
                ally_tag          = :allianceTag,
                ally_owner        = :userId,
                ally_owner_range  = :allianceOwnerRange,
                ally_members      = 1,
                ally_register_time = :time,
                ally_universe     = :universe;";
        $db->insert($sql, [
            ':allianceName'      => $name,
            ':allianceTag'       => $tag,
            ':userId'            => $userId,
            ':allianceOwnerRange' => $ownerRangeLabel,
            ':time'              => TIMESTAMP,
            ':universe'          => $universe,
        ]);

        $allianceId = (int) $db->lastInsertId();

        $sql = "UPDATE %%USERS%% SET ally_id = :allianceId, ally_rank_id = 0, ally_register_time = :time WHERE id = :userId;";
        $db->update($sql, [
            ':allianceId' => $allianceId,
            ':time'       => TIMESTAMP,
            ':userId'     => $userId,
        ]);

        $sql = "UPDATE %%STATPOINTS%% SET id_ally = :allianceId WHERE id_owner = :userId;";
        $db->update($sql, [
            ':allianceId' => $allianceId,
            ':userId'     => $userId,
        ]);

        return $allianceId;
    }

    /**
     * Dissolve an alliance entirely, removing all members and related rows.
     *
     * @param int $allyId     Alliance ID to dissolve
     * @param int $userId     User requesting the dissolution (must be the owner)
     *
     * @throws \RuntimeException if user has no alliance or is not the leader
     */
    public static function dissolveAlliance(int $allyId, int $userId): void
    {
        if ($allyId == 0) {
            throw new \RuntimeException('no_alliance');
        }

        $db = Database::get();

        $sql = 'SELECT ally_owner FROM %%ALLIANCE%% WHERE id = :allianceId;';
        $owner = $db->selectSingle($sql, [':allianceId' => $allyId], 'ally_owner');

        if ((int) $owner !== $userId) {
            throw new \RuntimeException('not_leader');
        }

        $sql = "UPDATE %%USERS%% SET ally_id = '0' WHERE ally_id = :AllianceID;";
        $db->update($sql, [':AllianceID' => $allyId]);

        $sql = "UPDATE %%STATPOINTS%% SET id_ally = '0' WHERE id_ally = :AllianceID;";
        $db->update($sql, [':AllianceID' => $allyId]);

        $sql = "DELETE FROM %%STATPOINTS%% WHERE id_owner = :AllianceID AND stat_type = 2;";
        $db->delete($sql, [':AllianceID' => $allyId]);

        $sql = "DELETE FROM %%ALLIANCE%% WHERE id = :AllianceID;";
        $db->delete($sql, [':AllianceID' => $allyId]);

        $sql = "DELETE FROM %%ALLIANCE_REQUEST%% WHERE allianceId = :AllianceID;";
        $db->delete($sql, [':AllianceID' => $allyId]);

        $sql = "DELETE FROM %%DIPLO%% WHERE owner_1 = :AllianceID OR owner_2 = :AllianceID;";
        $db->delete($sql, [':AllianceID' => $allyId]);
    }

    /**
     * Update alliance settings with an arbitrary set of field => value pairs.
     *
     * The $fields array keys must be valid %%ALLIANCE%% column names.
     * The caller is responsible for validating / sanitising values before calling.
     *
     * @param int   $allyId  Alliance ID
     * @param array $fields  Associative array of column => value
     */
    public static function editAlliance(int $allyId, array $fields): void
    {
        if (empty($fields)) {
            return;
        }

        $db = Database::get();

        $setClauses = [];
        $params     = [':AllianceID' => $allyId];

        foreach ($fields as $column => $value) {
            $paramKey           = ':' . $column;
            $setClauses[]       = "`{$column}` = {$paramKey}";
            $params[$paramKey]  = $value;
        }

        $sql = 'UPDATE %%ALLIANCE%% SET ' . implode(', ', $setClauses) . ' WHERE id = :AllianceID;';
        $db->update($sql, $params);
    }

    /**
     * Submit an application (request) to join an alliance.
     *
     * @param int    $allyId    Target alliance ID
     * @param int    $userId    Applicant user ID
     * @param int    $universe  Universe number
     * @param string $text      Application text
     *
     * @throws \RuntimeException if the user already has a pending application or is already a member
     */
    public static function applyToAlliance(int $allyId, int $userId, int $universe, string $text): void
    {
        $db = Database::get();

        // Guard: already member
        $sql = 'SELECT ally_id FROM %%USERS%% WHERE id = :userId;';
        $currentAllyId = (int) $db->selectSingle($sql, [':userId' => $userId], 'ally_id');
        if ($currentAllyId !== 0) {
            throw new \RuntimeException('already_member');
        }

        // Guard: already applied
        $sql = 'SELECT COUNT(*) as count FROM %%ALLIANCE_REQUEST%% WHERE userId = :userId;';
        $existing = (int) $db->selectSingle($sql, [':userId' => $userId], 'count');
        if ($existing > 0) {
            throw new \RuntimeException('already_applied');
        }

        $sql = "INSERT INTO %%ALLIANCE_REQUEST%% SET
                allianceId = :allianceId,
                text       = :text,
                time       = :time,
                userId     = :userId;";
        $db->insert($sql, [
            ':allianceId' => $allyId,
            ':text'       => $text,
            ':time'       => TIMESTAMP,
            ':userId'     => $userId,
        ]);
    }

    /**
     * Accept a membership request: assign user to alliance and remove the request row.
     *
     * @param int $requestId  applyID from %%ALLIANCE_REQUEST%%
     * @param int $allyId     Alliance ID (used to update member count)
     *
     * @throws \RuntimeException if the request is not found
     */
    public static function acceptMember(int $requestId, int $allyId): void
    {
        $db = Database::get();

        $sql    = 'SELECT userId FROM %%ALLIANCE_REQUEST%% WHERE applyID = :applyID;';
        $userId = $db->selectSingle($sql, [':applyID' => $requestId], 'userId');

        if (!$userId) {
            throw new \RuntimeException('request_not_found');
        }

        $sql = 'DELETE FROM %%ALLIANCE_REQUEST%% WHERE applyID = :applyID';
        $db->delete($sql, [':applyID' => $requestId]);

        $sql = 'UPDATE %%USERS%% SET ally_id = :allianceId, ally_register_time = :time, ally_rank_id = 0 WHERE id = :userId;';
        $db->update($sql, [
            ':allianceId' => $allyId,
            ':time'       => TIMESTAMP,
            ':userId'     => $userId,
        ]);

        $sql = 'UPDATE %%STATPOINTS%% SET id_ally = :allianceId WHERE id_owner = :userId AND stat_type = 1;';
        $db->update($sql, [
            ':allianceId' => $allyId,
            ':userId'     => $userId,
        ]);

        $sql = 'UPDATE %%ALLIANCE%% SET ally_members = (SELECT COUNT(*) FROM %%USERS%% WHERE ally_id = :allianceId) WHERE id = :allianceId;';
        $db->update($sql, [':allianceId' => $allyId]);
    }

    /**
     * Kick a member out of the alliance.
     *
     * @param int $memberId     ID of the user to kick
     * @param int $allyId       Alliance ID
     * @param int $currentUserId  The user performing the kick (may not kick themselves)
     *
     * @throws \RuntimeException if kicking self, or target is not in the same alliance
     */
    public static function kickMember(int $memberId, int $allyId, int $currentUserId): void
    {
        if ($memberId === $currentUserId) {
            throw new \RuntimeException('cannot_kick_self');
        }

        $db = Database::get();

        $sql               = 'SELECT ally_id FROM %%USERS%% WHERE id = :id;';
        $memberAllyId      = (int) $db->selectSingle($sql, [':id' => $memberId], 'ally_id');

        if (empty($memberAllyId) || $memberAllyId !== $allyId) {
            throw new \RuntimeException('not_in_alliance');
        }

        $sql = 'UPDATE %%USERS%% SET ally_id = 0, ally_register_time = 0, ally_rank_id = 0 WHERE id = :id;';
        $db->update($sql, [':id' => $memberId]);

        $sql = 'UPDATE %%STATPOINTS%% SET id_ally = 0 WHERE id_owner = :id AND stat_type = 1;';
        $db->update($sql, [':id' => $memberId]);

        $sql = 'UPDATE %%ALLIANCE%% SET ally_members = (SELECT COUNT(*) FROM %%USERS%% WHERE ally_id = :allianceId) WHERE id = :allianceId;';
        $db->update($sql, [':allianceId' => $allyId]);
    }

    /**
     * Leave an alliance voluntarily (non-leader members only).
     *
     * If the departing user was the last member the alliance is dissolved.
     * Leaders must dissolve the alliance explicitly instead.
     *
     * @param int $userId  User who wants to leave
     * @param int $allyId  Alliance ID
     *
     * @throws \RuntimeException if user is the alliance leader (must dissolve instead)
     */
    public static function leaveAlliance(int $userId, int $allyId): void
    {
        $db = Database::get();

        $sql   = 'SELECT ally_owner, ally_members FROM %%ALLIANCE%% WHERE id = :allianceId;';
        $alliance = $db->selectSingle($sql, [':allianceId' => $allyId]);

        if ((int) $alliance['ally_owner'] === $userId) {
            throw new \RuntimeException('leader_must_dissolve');
        }

        $sql = 'UPDATE %%USERS%% SET ally_id = 0, ally_register_time = 0, ally_rank_id = 0 WHERE id = :userId;';
        $db->update($sql, [':userId' => $userId]);

        $sql = 'UPDATE %%STATPOINTS%% SET id_ally = 0 WHERE id_owner = :userId AND stat_type = 1;';
        $db->update($sql, [':userId' => $userId]);

        $remainingMembers = (int) $alliance['ally_members'] - 1;

        if ($remainingMembers <= 0) {
            // Last member left — dissolve
            $sql = 'DELETE FROM %%ALLIANCE%% WHERE id = :allianceId;';
            $db->delete($sql, [':allianceId' => $allyId]);

            $sql = 'DELETE FROM %%ALLIANCE_REQUEST%% WHERE allianceId = :allianceId;';
            $db->delete($sql, [':allianceId' => $allyId]);

            $sql = 'DELETE FROM %%DIPLO%% WHERE owner_1 = :allianceId OR owner_2 = :allianceId;';
            $db->delete($sql, [':allianceId' => $allyId]);
        } else {
            $sql = 'UPDATE %%ALLIANCE%% SET ally_members = (SELECT COUNT(*) FROM %%USERS%% WHERE ally_id = :allianceId) WHERE id = :allianceId;';
            $db->update($sql, [':allianceId' => $allyId]);
        }
    }
}
