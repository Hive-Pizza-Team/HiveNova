<?php

/**
 *  2Moons 
 *   by Jan-Otto Kröpke 2009-2016
 *
 * For the full copyright and license information, please view the LICENSE
 *
 * @package HiveNova
 * @author TheCrazyGM <TheCrazyGM@gmail.com>
 * @copyright 2025 TheCrazyGM <TheCrazyGM@gmail.com>
 * @licence MIT
 * @version 1.8.0
 * @link https://github.com/Hive-Pizza-Team/HiveNova/
 */

require_once 'includes/classes/cronjob/CronjobTask.interface.php';

class BackupCleanupCronjob implements CronjobTask
{
    /**
     * Number of days to keep backups
     * @var int
     */
    const BACKUP_RETENTION_DAYS = 7; // Keep backups for 7 days by default

    /**
     * Directory where backups are stored
     * @var string
     */
    const BACKUP_DIR = 'includes/backups/';

    /**
     * Pattern to match backup files
     * @var string
     */
    const BACKUP_PATTERN = '2MoonsBackup_*.sql';

    /**
     * Run the backup cleanup process
     * 
     * @throws Exception If there's an error during the cleanup process
     */
    public function run()
    {
        $backupDir = ROOT_PATH . self::BACKUP_DIR;

        // Check if backup directory exists and is readable
        if (!is_dir($backupDir) || !is_readable($backupDir)) {
            throw new Exception('Backup directory does not exist or is not readable: ' . $backupDir);
        }

        // Get all backup files
        $backupFiles = glob($backupDir . self::BACKUP_PATTERN);

        if (empty($backupFiles)) {
            return; // No backup files found, nothing to do
        }

        $now = time();
        $deletedCount = 0;
        $errorCount = 0;

        foreach ($backupFiles as $file) {
            // Skip directories (shouldn't happen with the pattern, but just in case)
            if (!is_file($file)) {
                continue;
            }

            // Get file modification time
            $fileTime = filemtime($file);
            $fileAgeInDays = ($now - $fileTime) / (60 * 60 * 24);

            // Delete files older than retention period
            if ($fileAgeInDays > self::BACKUP_RETENTION_DAYS) {
                if (@unlink($file)) {
                    $deletedCount++;
                } else {
                    $errorCount++;
                    error_log(sprintf('Failed to delete old backup file: %s', $file));
                }
            }
        }

        // Log the cleanup results
        if ($deletedCount > 0 || $errorCount > 0) {
            $message = sprintf(
                'Backup cleanup completed. Deleted %d files, %d errors.',
                $deletedCount,
                $errorCount
            );
            error_log($message);
        }
    }
}
