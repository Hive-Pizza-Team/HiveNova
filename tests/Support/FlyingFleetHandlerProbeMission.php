<?php

namespace HiveNova\Mission;

class FlyingFleetHandlerProbeMission implements Mission
{
    public static int $started = 0;

    public static int $finished = 0;

    public static int $endStay = 0;

    public static int $returned = 0;

    public static ?int $throwOnFleetId = null;

    /**
     * @param array<string, mixed> $Fleet
     */
    public function __construct(private array $Fleet)
    {
    }

    public function TargetEvent()
    {
        self::$started++;
        if (self::$throwOnFleetId !== null && (int) $this->Fleet['fleet_id'] === self::$throwOnFleetId) {
            throw new \RuntimeException('probe boom');
        }
        self::$finished++;
    }

    public function EndStayEvent()
    {
        self::$endStay++;
    }

    public function ReturnEvent()
    {
        self::$returned++;
    }

    public static function reset(): void
    {
        self::$started = 0;
        self::$finished = 0;
        self::$endStay = 0;
        self::$returned = 0;
        self::$throwOnFleetId = null;
    }
}
