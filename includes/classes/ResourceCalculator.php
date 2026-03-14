<?php

namespace HiveNova\Core;

use HiveNova\Core\Database;
use HiveNova\Core\Config;

/**
 * Pure resource production calculations — no queue processing, no DB writes.
 * Extracted from ResourceUpdate to allow fast unit testing without DB.
 */
class ResourceCalculator
{
    private array $USER;
    private array $PLANET;
    private Config $config;
    private array $resource;
    private array $reslist;
    private float $productionTime;

    public function __construct(
        array $USER,
        array $PLANET,
        Config $config,
        array $resource,
        array $reslist,
        float $productionTime
    ) {
        $this->USER          = $USER;
        $this->PLANET        = $PLANET;
        $this->config        = $config;
        $this->resource      = $resource;
        $this->reslist       = $reslist;
        $this->productionTime = $productionTime;
    }

    public function getPlanet(): array
    {
        return $this->PLANET;
    }

    /**
     * Apply production math to metal/crystal/deuterium for the elapsed production time.
     */
    public function execCalc(): void
    {
        if ($this->PLANET['planet_type'] == 3) {
            return;
        }

        $MaxMetalStorage     = $this->PLANET['metal_max']     * $this->config->max_overflow;
        $MaxCristalStorage   = $this->PLANET['crystal_max']   * $this->config->max_overflow;
        $MaxDeuteriumStorage = $this->PLANET['deuterium_max'] * $this->config->max_overflow;

        $MetalTheoretical = $this->productionTime * (($this->config->metal_basic_income * $this->config->resource_multiplier) + $this->PLANET['metal_perhour']) / 3600;

        if ($MetalTheoretical < 0) {
            $this->PLANET['metal'] = max($this->PLANET['metal'] + $MetalTheoretical, 0);
        } elseif ($this->PLANET['metal'] <= $MaxMetalStorage) {
            $this->PLANET['metal'] = min($this->PLANET['metal'] + $MetalTheoretical, $MaxMetalStorage);
        }

        $CristalTheoretical = $this->productionTime * (($this->config->crystal_basic_income * $this->config->resource_multiplier) + $this->PLANET['crystal_perhour']) / 3600;
        if ($CristalTheoretical < 0) {
            $this->PLANET['crystal'] = max($this->PLANET['crystal'] + $CristalTheoretical, 0);
        } elseif ($this->PLANET['crystal'] <= $MaxCristalStorage) {
            $this->PLANET['crystal'] = min($this->PLANET['crystal'] + $CristalTheoretical, $MaxCristalStorage);
        }

        $DeuteriumTheoretical = $this->productionTime * (($this->config->deuterium_basic_income * $this->config->resource_multiplier) + $this->PLANET['deuterium_perhour']) / 3600;
        if ($DeuteriumTheoretical < 0) {
            $this->PLANET['deuterium'] = max($this->PLANET['deuterium'] + $DeuteriumTheoretical, 0);
        } elseif ($this->PLANET['deuterium'] <= $MaxDeuteriumStorage) {
            $this->PLANET['deuterium'] = min($this->PLANET['deuterium'] + $DeuteriumTheoretical, $MaxDeuteriumStorage);
        }

        $this->PLANET['metal']     = max($this->PLANET['metal'], 0);
        $this->PLANET['crystal']   = max($this->PLANET['crystal'], 0);
        $this->PLANET['deuterium'] = max($this->PLANET['deuterium'], 0);
    }

    /**
     * Build the production/storage cache for the planet (metal_perhour, crystal_perhour, etc.).
     * Reads $ProdGrid from global scope (populated by the game bootstrap).
     */
    public function reBuildCache(): void
    {
        global $ProdGrid;

        // eval()'d formulae from getProd() expect these as local variables
        $resource = $this->resource;
        $reslist  = $this->reslist;
        $USER     = $this->USER;
        $PLANET   = $this->PLANET;

        if ($this->PLANET['planet_type'] == 3) {
            $this->config->metal_basic_income     = 0;
            $this->config->crystal_basic_income   = 0;
            $this->config->deuterium_basic_income = 0;
        }

        $temp = [
            901 => ['max' => 0, 'plus' => 0, 'minus' => 0],
            902 => ['max' => 0, 'plus' => 0, 'minus' => 0],
            903 => ['max' => 0, 'plus' => 0, 'minus' => 0],
            911 => ['plus' => 0, 'minus' => 0],
        ];

        $BuildTemp   = $this->PLANET['temp_max'];
        $BuildEnergy = $this->USER[$this->resource[113]];

        foreach ($this->reslist['storage'] as $ProdID) {
            foreach ($this->reslist['resstype'][1] as $ID) {
                if (!isset($ProdGrid[$ProdID]['storage'][$ID])) {
                    continue;
                }
                $BuildLevel      = $this->PLANET[$this->resource[$ProdID]];
                $temp[$ID]['max'] += round(eval(self::getProd($ProdGrid[$ProdID]['storage'][$ID])));
            }
        }

        $ressIDs = array_merge([], $this->reslist['resstype'][1], $this->reslist['resstype'][2]);

        foreach ($this->reslist['prod'] as $ProdID) {
            $BuildLevelFactor = $this->PLANET[$this->resource[$ProdID] . '_porcent'];
            $BuildLevel       = $this->PLANET[$this->resource[$ProdID]];

            foreach ($ressIDs as $ID) {
                if (!isset($ProdGrid[$ProdID]['production'][$ID])) {
                    continue;
                }

                $Production = eval(self::getProd($ProdGrid[$ProdID]['production'][$ID]));

                if ($Production > 0) {
                    $temp[$ID]['plus'] += $Production;
                } else {
                    if (in_array($ID, $this->reslist['resstype'][1]) && $this->PLANET[$this->resource[$ID]] == 0) {
                        continue;
                    }
                    $temp[$ID]['minus'] += $Production;
                }
            }
        }

        $this->PLANET['metal_max']     = $temp[901]['max'] * $this->config->storage_multiplier * (1 + $this->USER['factor']['ResourceStorage']);
        $this->PLANET['crystal_max']   = $temp[902]['max'] * $this->config->storage_multiplier * (1 + $this->USER['factor']['ResourceStorage']);
        $this->PLANET['deuterium_max'] = $temp[903]['max'] * $this->config->storage_multiplier * (1 + $this->USER['factor']['ResourceStorage']);

        $this->PLANET['energy']      = round($temp[911]['plus'] * $this->config->energySpeed * (1 + $this->USER['factor']['Energy']));
        $this->PLANET['energy_used'] = $temp[911]['minus'] * $this->config->energySpeed;

        if ($this->PLANET['energy_used'] == 0) {
            $this->PLANET['metal_perhour']     = 0;
            $this->PLANET['crystal_perhour']   = 0;
            $this->PLANET['deuterium_perhour'] = 0;
        } else {
            $prodLevel = min(1, $this->PLANET['energy'] / abs($this->PLANET['energy_used']));

            $this->PLANET['metal_perhour']     = ($temp[901]['plus'] * (1 + $this->USER['factor']['Resource'] + 0.02 * $this->USER[$this->resource[131]]) * $prodLevel + $temp[901]['minus']) * $this->config->resource_multiplier;
            $this->PLANET['crystal_perhour']   = ($temp[902]['plus'] * (1 + $this->USER['factor']['Resource'] + 0.02 * $this->USER[$this->resource[132]]) * $prodLevel + $temp[902]['minus']) * $this->config->resource_multiplier;
            $this->PLANET['deuterium_perhour'] = ($temp[903]['plus'] * (1 + $this->USER['factor']['Resource'] + 0.02 * $this->USER[$this->resource[133]]) * $prodLevel + $temp[903]['minus']) * $this->config->resource_multiplier;
        }
    }

    /**
     * Build an eval-able production formula string from a $ProdGrid formula.
     * Called from within eval() context in reBuildCache() and externally from game pages.
     */
    public static function getProd(string $Calculation, mixed $Element = false): string
    {
        global $resource, $reslist, $USER, $PLANET;

        if ($Element) {
            $BuildEnergy      = $USER[$resource[113]];
            $BuildTemp        = $PLANET['temp_max'];
            $BuildLevelFactor = $PLANET[$resource[$Element] . '_porcent'];

            if (in_array($Element, array_merge($reslist['build'], $reslist['fleet'], $reslist['defense']))) {
                $BuildLevel = $PLANET[$resource[$Element]];
            } elseif (in_array($Element, array_merge($reslist['tech'], $reslist['officier']))) {
                $BuildLevel = $USER[$resource[$Element]];
            } else {
                $BuildLevel = 0;
            }

            $Calculation = str_replace('this->', '', $Calculation);
        }

        return 'return ' . $Calculation . ';';
    }

    /**
     * Return the list of research lab levels across the network for the intergalactic research network bonus.
     * Makes a DB call only when the intergalactic network tech is active.
     */
    public static function getNetworkLevel(array $USER, array $PLANET): array
    {
        global $resource;

        $researchLevelList = [$PLANET[$resource[31]]];

        if ($USER[$resource[123]] > 0) {
            $sql = 'SELECT ' . $resource[31] . ' FROM %%PLANETS%% WHERE id != :planetId AND id_owner = :userId AND destruyed = 0 ORDER BY ' . $resource[31] . ' DESC LIMIT :limit;';
            $researchResult = Database::get()->select($sql, [
                ':limit'    => (int) $USER[$resource[123]],
                ':planetId' => $PLANET['id'],
                ':userId'   => $USER['id'],
            ]);

            foreach ($researchResult as $researchRow) {
                $researchLevelList[] = $researchRow[$resource[31]];
            }
        }

        return $researchLevelList;
    }
}
