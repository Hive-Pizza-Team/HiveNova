<?php

use HiveNova\Core\AchievementService;
use HiveNova\Core\AllianceService;
use HiveNova\Core\Config;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/FakeAchievementDatabase.php';
require_once __DIR__ . '/../Support/SwapDatabaseInstance.php';

/**
 * In-memory alliance state for AllianceService unit tests.
 */
class AllianceServiceFakeDatabase extends FakeAchievementDatabase
{
    /** @var array<int, array<string, mixed>> */
    public array $alliances = [];

    /** @var array<int, array{applyID: int, userId: int, allianceId: int, text: string}> */
    public array $requests = [];

    public int $nextAllianceId = 100;

    public int $nextRequestId = 1;

    /** @var list<string> */
    public array $updateLog = [];

    /** @var list<string> */
    public array $insertLog = [];

    public function seedUser(int $id, array $overrides = []): void
    {
        $this->users[$id] = array_merge([
            'id'            => $id,
            'universe'      => 1,
            'lang'          => 'en',
            'ally_id'       => 0,
            'ally_rank_id'  => 0,
            'ally_register_time' => 0,
            'wons'          => 0,
            'loos'          => 0,
            'draws'         => 0,
            'desunits'      => 0,
            'hive_account'  => '',
            'register_time' => TIMESTAMP - (30 * 86400),
            'darkmatter'    => 0,
        ], $overrides);
    }

    public function seedAlliance(int $id, array $overrides = []): void
    {
        $this->alliances[$id] = array_merge([
            'id'           => $id,
            'ally_name'    => 'Alliance ' . $id,
            'ally_tag'     => 'A' . $id,
            'ally_owner'   => 1,
            'ally_members' => 1,
            'ally_universe' => 1,
        ], $overrides);
    }

    public function seedRequest(int $applyId, int $userId, int $allianceId, string $text = ''): void
    {
        $this->requests[$applyId] = [
            'applyID'    => $applyId,
            'userId'     => $userId,
            'allianceId' => $allianceId,
            'text'       => $text,
        ];
    }

    public function selectSingle($qry, array $params = [], $field = false)
    {
        if (str_contains($qry, 'COUNT(*)') && str_contains($qry, '%%ALLIANCE%%')
            && (str_contains($qry, 'ally_tag') || str_contains($qry, 'ally_name'))) {
            $universe = (int) ($params[':universe'] ?? 0);
            $tag = (string) ($params[':allianceTag'] ?? '');
            $name = (string) ($params[':allianceName'] ?? '');
            $count = 0;
            foreach ($this->alliances as $alliance) {
                if ((int) ($alliance['ally_universe'] ?? 0) !== $universe) {
                    continue;
                }
                if (($alliance['ally_tag'] ?? '') === $tag || ($alliance['ally_name'] ?? '') === $name) {
                    $count++;
                }
            }

            return $field === 'count' ? $count : ['count' => $count];
        }

        if (str_contains($qry, 'COUNT(*)') && str_contains($qry, '%%ALLIANCE_REQUEST%%')
            && str_contains($qry, 'userId')) {
            $userId = (int) ($params[':userId'] ?? 0);
            $count = 0;
            foreach ($this->requests as $request) {
                if ((int) $request['userId'] === $userId) {
                    $count++;
                }
            }

            return $field === 'count' ? $count : ['count' => $count];
        }

        if (str_contains($qry, '%%ALLIANCE_REQUEST%%') && str_contains($qry, 'applyID')) {
            $applyId = (int) ($params[':applyID'] ?? 0);
            $request = $this->requests[$applyId] ?? null;
            if ($request === null) {
                return $field === false ? null : false;
            }

            return $field === false ? $request : ($request[$field] ?? false);
        }

        if (str_contains($qry, 'FROM %%ALLIANCE%%') && str_contains($qry, 'ally_owner')) {
            $allianceId = (int) ($params[':allianceId'] ?? $params[':AllianceID'] ?? 0);
            $alliance = $this->alliances[$allianceId] ?? null;
            if ($alliance === null) {
                return $field === false ? null : false;
            }

            return $field === false ? $alliance : ($alliance[$field] ?? false);
        }

        if (str_contains($qry, 'FROM %%USERS%%') && str_contains($qry, 'WHERE id')) {
            $userId = (int) ($params[':userId'] ?? $params[':id'] ?? 0);
            $row = $this->users[$userId] ?? null;
            if ($row === null) {
                return $field === false ? null : false;
            }

            return $field === false ? $row : ($row[$field] ?? false);
        }

        return parent::selectSingle($qry, $params, $field);
    }

    public function insert($qry, array $params = [])
    {
        $this->insertLog[] = $qry;

        if (str_contains($qry, 'INSERT INTO %%ALLIANCE%%')) {
            $id = $this->nextAllianceId++;
            $this->alliances[$id] = [
                'id'                 => $id,
                'ally_name'          => $params[':allianceName'] ?? '',
                'ally_tag'           => $params[':allianceTag'] ?? '',
                'ally_owner'         => (int) ($params[':userId'] ?? 0),
                'ally_owner_range'   => $params[':allianceOwnerRange'] ?? '',
                'ally_members'       => 1,
                'ally_register_time' => (int) ($params[':time'] ?? TIMESTAMP),
                'ally_universe'      => (int) ($params[':universe'] ?? 1),
            ];
            $this->lastInsertAllianceId = $id;

            return true;
        }

        if (str_contains($qry, 'INSERT INTO %%ALLIANCE_REQUEST%%')) {
            $applyId = $this->nextRequestId++;
            $this->requests[$applyId] = [
                'applyID'    => $applyId,
                'userId'     => (int) ($params[':userId'] ?? 0),
                'allianceId' => (int) ($params[':allianceId'] ?? 0),
                'text'       => (string) ($params[':text'] ?? ''),
                'time'       => (int) ($params[':time'] ?? TIMESTAMP),
            ];

            return true;
        }

        return parent::insert($qry, $params);
    }

    public function update($qry, array $params = [])
    {
        $this->updateLog[] = $qry;

        if (str_contains($qry, 'UPDATE %%USERS%%') && str_contains($qry, 'ally_id')) {
            $userId = (int) ($params[':userId'] ?? $params[':id'] ?? 0);
            if (!isset($this->users[$userId])) {
                return true;
            }
            if (isset($params[':allianceId'])) {
                $this->users[$userId]['ally_id'] = (int) $params[':allianceId'];
                $this->users[$userId]['ally_rank_id'] = 0;
                $this->users[$userId]['ally_register_time'] = (int) ($params[':time'] ?? TIMESTAMP);
            } else {
                $this->users[$userId]['ally_id'] = 0;
                $this->users[$userId]['ally_rank_id'] = 0;
                $this->users[$userId]['ally_register_time'] = 0;
            }
        }

        if (str_contains($qry, 'UPDATE %%ALLIANCE%% SET') && !str_contains($qry, 'ally_members = (SELECT')) {
            $allianceId = (int) ($params[':AllianceID'] ?? 0);
            if (isset($this->alliances[$allianceId])) {
                foreach ($params as $key => $value) {
                    if ($key === ':AllianceID') {
                        continue;
                    }
                    $column = ltrim((string) $key, ':');
                    $this->alliances[$allianceId][$column] = $value;
                }
            }
        }

        return parent::update($qry, $params);
    }

    public function delete($qry, array $params = [])
    {
        if (str_contains($qry, '%%ALLIANCE_REQUEST%%')) {
            if (str_contains($qry, 'applyID')) {
                unset($this->requests[(int) ($params[':applyID'] ?? 0)]);
            } elseif (str_contains($qry, 'allianceId')) {
                $allianceId = (int) ($params[':allianceId'] ?? $params[':AllianceID'] ?? 0);
                foreach ($this->requests as $applyId => $request) {
                    if ((int) $request['allianceId'] === $allianceId) {
                        unset($this->requests[$applyId]);
                    }
                }
            }
        }

        if (str_contains($qry, 'DELETE FROM %%ALLIANCE%%')) {
            $allianceId = (int) ($params[':allianceId'] ?? $params[':AllianceID'] ?? 0);
            unset($this->alliances[$allianceId]);
        }

        return parent::delete($qry, $params);
    }

    public int $lastInsertAllianceId = 0;

    public function lastInsertId()
    {
        if ($this->lastInsertAllianceId > 0) {
            return $this->lastInsertAllianceId;
        }

        return parent::lastInsertId();
    }
}

class AllianceServiceTest extends TestCase
{
    use SwapDatabaseInstance;

    private AllianceServiceFakeDatabase $fake;

    protected function setUp(): void
    {
        AchievementService::resetSchemaReadyCache();
        AchievementService::get()->clearDefinitionCache();

        $modules = array_fill(0, 50, 1);
        Config::setInstance(new Config(['uni' => 1, 'moduls' => implode(';', $modules)]), 1);

        $this->fake = new AllianceServiceFakeDatabase();
        $this->swapDatabaseInstance($this->fake);
    }

    protected function tearDown(): void
    {
        $this->restoreDatabaseInstance();
        AchievementService::get()->clearDefinitionCache();
    }

    public function testCreateAllianceInsertsAllianceAndUpdatesUser(): void
    {
        $this->fake->seedUser(10);

        $allianceId = AllianceService::createAlliance(10, 1, 'NOVA', 'Nova Alliance', 'Leader');

        $this->assertSame(100, $allianceId);
        $this->assertSame('NOVA', $this->fake->alliances[100]['ally_tag']);
        $this->assertSame(10, $this->fake->alliances[100]['ally_owner']);
        $this->assertSame(100, $this->fake->users[10]['ally_id']);
        $this->assertSame(0, $this->fake->users[10]['ally_rank_id']);
    }

    public function testCreateAllianceThrowsWhenTagOrNameTaken(): void
    {
        $this->fake->seedUser(11);
        $this->fake->seedAlliance(50, [
            'ally_tag'     => 'TAKN',
            'ally_name'    => 'Taken Name',
            'ally_universe' => 1,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tag_or_name_taken');

        AllianceService::createAlliance(11, 1, 'TAKN', 'Fresh Name', 'Leader');
    }

    public function testApplyToAllianceInsertsRequest(): void
    {
        $this->fake->seedUser(20);
        $this->fake->seedAlliance(7);

        AllianceService::applyToAlliance(7, 20, 1, 'Please let me in');

        $this->assertCount(1, $this->fake->requests);
        $request = array_values($this->fake->requests)[0];
        $this->assertSame(20, $request['userId']);
        $this->assertSame(7, $request['allianceId']);
        $this->assertSame('Please let me in', $request['text']);
    }

    public function testApplyToAllianceThrowsWhenAlreadyMember(): void
    {
        $this->fake->seedUser(21, ['ally_id' => 8]);
        $this->fake->seedAlliance(8);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already_member');

        AllianceService::applyToAlliance(8, 21, 1, 'Apply again');
    }

    public function testApplyToAllianceThrowsWhenAlreadyApplied(): void
    {
        $this->fake->seedUser(22);
        $this->fake->seedAlliance(9);
        $this->fake->seedRequest(5, 22, 9, 'Pending');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already_applied');

        AllianceService::applyToAlliance(9, 22, 1, 'Duplicate apply');
    }

    public function testAcceptMemberAssignsUserAndRemovesRequest(): void
    {
        $this->fake->seedUser(30);
        $this->fake->seedAlliance(12);
        $this->fake->seedRequest(3, 30, 12, 'Join us');

        AllianceService::acceptMember(3, 12);

        $this->assertSame([], $this->fake->requests);
        $this->assertSame(12, $this->fake->users[30]['ally_id']);
        $updates = implode("\n", $this->fake->updateLog);
        $this->assertStringContainsString('UPDATE %%STATPOINTS%%', $updates);
        $this->assertStringContainsString('ally_members = (SELECT COUNT(*)', $updates);
    }

    public function testAcceptMemberThrowsWhenRequestNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('request_not_found');

        AllianceService::acceptMember(999, 12);
    }

    public function testRejectApplicationDeletesRequestRow(): void
    {
        $this->fake->seedRequest(4, 40, 15, 'Reject me');

        $this->fake->delete(
            'DELETE FROM %%ALLIANCE_REQUEST%% WHERE applyID = :applyID',
            [':applyID' => 4],
        );

        $this->assertSame([], $this->fake->requests);
    }

    public function testKickMemberClearsMembership(): void
    {
        $this->fake->seedUser(50, ['ally_id' => 20]);
        $this->fake->seedUser(51, ['ally_id' => 20]);
        $this->fake->seedAlliance(20, ['ally_members' => 2, 'ally_owner' => 51]);

        AllianceService::kickMember(50, 20, 51);

        $this->assertSame(0, $this->fake->users[50]['ally_id']);
        $this->assertSame(20, $this->fake->users[51]['ally_id']);
        $updates = implode("\n", $this->fake->updateLog);
        $this->assertStringContainsString('id_ally = 0', $updates);
    }

    public function testKickMemberThrowsWhenKickingSelf(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cannot_kick_self');

        AllianceService::kickMember(60, 21, 60);
    }

    public function testKickMemberThrowsWhenTargetNotInAlliance(): void
    {
        $this->fake->seedUser(61, ['ally_id' => 0]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not_in_alliance');

        AllianceService::kickMember(61, 22, 70);
    }

    public function testLeaveAllianceClearsMemberWhenNotLeader(): void
    {
        $this->fake->seedUser(70, ['ally_id' => 30]);
        $this->fake->seedAlliance(30, ['ally_owner' => 71, 'ally_members' => 2]);

        AllianceService::leaveAlliance(70, 30);

        $this->assertSame(0, $this->fake->users[70]['ally_id']);
        $this->assertArrayHasKey(30, $this->fake->alliances);
        $updates = implode("\n", $this->fake->updateLog);
        $this->assertStringContainsString('ally_members = (SELECT COUNT(*)', $updates);
    }

    public function testLeaveAllianceThrowsWhenUserIsLeader(): void
    {
        $this->fake->seedUser(80, ['ally_id' => 40]);
        $this->fake->seedAlliance(40, ['ally_owner' => 80, 'ally_members' => 1]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('leader_must_dissolve');

        AllianceService::leaveAlliance(80, 40);
    }

    public function testLeaveAllianceDissolvesWhenLastMember(): void
    {
        $this->fake->seedUser(81, ['ally_id' => 41]);
        $this->fake->seedAlliance(41, ['ally_owner' => 82, 'ally_members' => 1]);

        AllianceService::leaveAlliance(81, 41);

        $this->assertSame(0, $this->fake->users[81]['ally_id']);
        $this->assertArrayNotHasKey(41, $this->fake->alliances);
        $deletes = implode("\n", $this->fake->deleteLog);
        $this->assertStringContainsString('DELETE FROM %%ALLIANCE%%', $deletes);
        $this->assertStringContainsString('DELETE FROM %%ALLIANCE_REQUEST%%', $deletes);
        $this->assertStringContainsString('DELETE FROM %%DIPLO%%', $deletes);
    }

    public function testDissolveAllianceRequiresLeader(): void
    {
        $this->fake->seedAlliance(50, ['ally_owner' => 90]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not_leader');

        AllianceService::dissolveAlliance(50, 91);
    }

    public function testDissolveAllianceThrowsWhenNoAlliance(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no_alliance');

        AllianceService::dissolveAlliance(0, 1);
    }

    public function testDissolveAllianceRemovesAllianceAndRelatedRows(): void
    {
        $this->fake->seedUser(92, ['ally_id' => 51]);
        $this->fake->seedUser(93, ['ally_id' => 51]);
        $this->fake->seedAlliance(51, ['ally_owner' => 92, 'ally_members' => 2]);
        $this->fake->seedRequest(10, 94, 51);

        AllianceService::dissolveAlliance(51, 92);

        $this->assertArrayNotHasKey(51, $this->fake->alliances);
        $this->assertSame([], $this->fake->requests);
        $deletes = implode("\n", $this->fake->deleteLog);
        $this->assertStringContainsString('DELETE FROM %%ALLIANCE%%', $deletes);
        $this->assertStringContainsString('DELETE FROM %%DIPLO%%', $deletes);
        $this->assertStringContainsString('stat_type = 2', $deletes);
    }

    public function testEditAllianceNoOpWhenFieldsEmpty(): void
    {
        $this->fake->seedAlliance(60);

        AllianceService::editAlliance(60, []);

        $this->assertSame([], $this->fake->updateLog);
    }

    public function testEditAllianceUpdatesFields(): void
    {
        $this->fake->seedAlliance(61, ['ally_name' => 'Old Name']);

        AllianceService::editAlliance(61, ['ally_name' => 'New Name', 'ally_description' => 'Updated']);

        $this->assertSame('New Name', $this->fake->alliances[61]['ally_name']);
        $this->assertSame('Updated', $this->fake->alliances[61]['ally_description']);
        $updates = implode("\n", $this->fake->updateLog);
        $this->assertStringContainsString('UPDATE %%ALLIANCE%% SET', $updates);
    }
}
