<?php

/**
 * Handles discovery and application of SQL/PHP database migrations.
 */
class Migrator
{
    public function __construct(
        private readonly PDO    $pdo,
        private readonly string $migrationsDir,
        private readonly string $prefix,
        private readonly int    $requiredVersion,
    ) {}

    /**
     * Returns the current dbVersion stored in the system table, or 0 on error.
     */
    public function getCurrentVersion(): int
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT dbVersion FROM `{$this->prefix}system` LIMIT 1"
            );
            return (int) $stmt->fetchColumn();
        } catch (PDOException) {
            return 0;
        }
    }

    /**
     * Returns migration descriptors that are pending (> current, <= required),
     * sorted by revision number ascending.
     *
     * Each entry: ['rev' => int, 'filename' => string, 'path' => string, 'extension' => string]
     */
    public function getPendingMigrations(int $currentVersion): array
    {
        $migrations = [];
        $iterator   = new DirectoryIterator($this->migrationsDir);

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            if (!preg_match('/^migration_(\d+)\.(sql|php)$/', $fileInfo->getFilename(), $m)) {
                continue;
            }
            $rev = (int) $m[1];
            if ($rev <= $currentVersion || $rev > $this->requiredVersion) {
                continue;
            }
            $migrations[$rev] = [
                'rev'       => $rev,
                'filename'  => $fileInfo->getFilename(),
                'path'      => $fileInfo->getPathname(),
                'extension' => $m[2],
            ];
        }

        ksort($migrations);
        return array_values($migrations);
    }

    /**
     * Splits a SQL file's content into individual queries, replacing the table prefix.
     *
     * @return string[]
     */
    public function parseSql(string $sql): array
    {
        $sql = str_replace('%PREFIX%', $this->prefix, $sql);
        return array_values(array_filter(array_map('trim', explode(';', $sql))));
    }

    /**
     * Applies a single SQL migration. Individual query errors are logged and skipped
     * (mirrors web-runner behaviour for ALTER TABLE IF NOT EXISTS scenarios).
     */
    public function applySqlMigration(array $migration): void
    {
        $sql     = file_get_contents($migration['path']);
        $queries = $this->parseSql($sql);

        foreach ($queries as $query) {
            try {
                $this->pdo->exec($query);
            } catch (PDOException $e) {
                error_log("Migration [{$migration['filename']}] query skipped: " . $e->getMessage());
            }
        }
    }

    /**
     * Applies a single PHP migration by including it.
     * The included file has access to $this->pdo via $pdo (assigned before include).
     */
    public function applyPhpMigration(array $migration): void
    {
        $pdo = $this->pdo; /** @phpstan-ignore-line — $pdo is used by the included migration script */
        include $migration['path'];
    }

    /**
     * Bumps dbVersion to $requiredVersion in the system table.
     */
    public function updateVersion(): void
    {
        $this->pdo->exec(
            "UPDATE `{$this->prefix}system` SET dbVersion = {$this->requiredVersion}"
        );
    }

    /**
     * Discovers and applies all pending migrations.
     *
     * @return array Applied migration descriptors (empty if dry run or nothing pending)
     */
    public function run(bool $dryRun = false): array
    {
        $currentVersion = $this->getCurrentVersion();
        $pending        = $this->getPendingMigrations($currentVersion);

        if (empty($pending) || $dryRun) {
            return $pending;
        }

        foreach ($pending as $migration) {
            match ($migration['extension']) {
                'sql' => $this->applySqlMigration($migration),
                'php' => $this->applyPhpMigration($migration),
            };
        }

        $this->updateVersion();
        return $pending;
    }
}
