<?php

namespace HiveNova\Cronjob;

/**
 * Stub invoked by Cronjob::execute() when the DB stores a short class name.
 */
class NormalizeExecuteStubCronjob implements CronjobTask
{
    public static bool $ran = false;

    public function run()
    {
        self::$ran = true;
    }
}
