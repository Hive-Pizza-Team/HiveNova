<?php

namespace HiveNova\Core;

use Exception;
use PDO;

/**
 * Web-installer upgrade: preview pending migrations, dump prefixed tables, apply via Migrator.
 */
class InstallerUpgrade
{
    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly SQLDumper $dumper,
        private readonly string $migrationsDir,
        private readonly string $prefix,
        private readonly int $requiredVersion,
        private readonly string $backupDir = 'includes/backups',
        private ?Migrator $migrator = null,
    ) {
    }

    public function migrator(): Migrator
    {
        if ($this->migrator instanceof Migrator) {
            return $this->migrator;
        }

        $pdo = $this->db->getHandle();
        if (!$pdo instanceof PDO) {
            throw new Exception('Database PDO handle is not available.');
        }

        $this->migrator = new Migrator(
            $pdo,
            $this->migrationsDir,
            $this->prefix,
            $this->requiredVersion,
        );

        return $this->migrator;
    }

    /**
     * @return array<string, string> filename => preview text
     */
    public function preview(): array
    {
        $migrator = $this->migrator();
        $pending  = $migrator->getPendingMigrations($migrator->getCurrentVersion());
        $updates  = [];

        foreach ($pending as $migration) {
            if ($migration['extension'] === 'php') {
                $updates[$migration['filename']] = '(PHP migration)';
                continue;
            }

            $sql = file_get_contents($migration['path']);
            $updates[$migration['filename']] = str_replace(
                '%PREFIX%',
                $this->prefix,
                $sql === false ? '' : $sql
            );
        }

        return $updates;
    }

    /**
     * @return list<string>
     */
    public function listPrefixedTables(string $databaseName): array
    {
        $rows      = $this->db->nativeQuery('SHOW TABLE STATUS FROM `' . $databaseName . '`;');
        $tables    = [];
        $prefixLen = strlen($this->prefix);

        foreach ($rows as $table) {
            $name = $table['Name'] ?? '';
            if ($name !== '' && $this->prefix === substr($name, 0, $prefixLen)) {
                $tables[] = $name;
            }
        }

        return $tables;
    }

    /**
     * @return array{applied: array, revision: int, backupPath: string}
     */
    public function apply(string $databaseName): array
    {
        $tables = $this->listPrefixedTables($databaseName);
        if ($tables === []) {
            throw new Exception('No tables found for dump.');
        }

        @set_time_limit(600);

        $timestamp  = defined('TIMESTAMP') ? TIMESTAMP : time();
        $backupPath = rtrim($this->backupDir, '/') . '/2MoonsBackup_'
            . date('Y_m_d_H_i_s', $timestamp) . '.sql';

        $this->dumper->dumpTablesToFile($tables, $backupPath);

        try {
            $applied = $this->migrator()->run();
            $this->migrator()->updateVersion();
        } catch (Exception $e) {
            throw $this->restoreOrFail($backupPath, $e->getMessage());
        }

        $revision = $this->requiredVersion;
        if ($applied !== []) {
            $last     = $applied[array_key_last($applied)];
            $revision = (int) $last['rev'];
        }

        return [
            'applied'    => $applied,
            'revision'   => $revision,
            'backupPath' => $backupPath,
        ];
    }

    private function restoreOrFail(string $backupPath, string $errorMessage): Exception
    {
        try {
            $this->dumper->restoreDatabase($backupPath);
            $message = 'Update error.<br><br>' . $errorMessage
                . '<br><br><b><i>Backup restored.</i></b>';
        } catch (Exception $e) {
            $message = 'Update error.<br><br>' . $errorMessage
                . '<br><br><b><i>Can not restore backup. Your game is maybe broken right now.</i></b>'
                . '<br><br>Restore error:<br>' . $e->getMessage();
        }

        return new Exception($message);
    }
}
