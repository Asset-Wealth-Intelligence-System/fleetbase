<?php

namespace Fleetbase\EnergyOps\Expansions;

use Fleetbase\Build\Expansion;
use Fleetbase\EnergyOps\Models\CustomerTank;
use Fleetbase\EnergyOps\Models\Product;
use Fleetbase\EnergyOps\Support\EnergyOps;
use Fleetbase\FleetOps\Models\Entity;

/**
 * Reads the energy fields the order form stores on payload entities:
 * meta.energyops_product_uuid, meta.volume, meta.unit, meta.energyops_customer_tank_uuid.
 */
class EntityExpansion implements Expansion
{
    public static function target()
    {
        return Entity::class;
    }

    public static function isEnergyEntity()
    {
        return function () {
            /** @var Entity $this */
            return (bool) ($this->getMeta('energyops_product_uuid') ?? $this->getMeta('product_uuid'))
                || $this->type === 'bulk_product';
        };
    }

    public static function energyProduct()
    {
        return function () {
            /** @var Entity $this */
            $uuid = $this->getMeta('energyops_product_uuid') ?? $this->getMeta('product_uuid');
            if ($uuid) {
                return Product::where('uuid', $uuid)->orWhere('public_id', $uuid)->first();
            }

            if ($this->sku) {
                return Product::where('company_uuid', $this->company_uuid)->where('code', $this->sku)->first();
            }

            return null;
        };
    }

    public static function energyCustomerTank()
    {
        return function () {
            /** @var Entity $this */
            $uuid = $this->getMeta('energyops_customer_tank_uuid');

            return $uuid ? CustomerTank::where('uuid', $uuid)->orWhere('public_id', $uuid)->first() : null;
        };
    }

    public static function energyUnit()
    {
        return function () {
            /** @var Entity $this */
            return (string) ($this->getMeta('unit') ?? $this->energyProduct()?->default_unit ?? 'L');
        };
    }

    public static function energyQuantity()
    {
        return function () {
            /** @var Entity $this */
            return (float) ($this->getMeta('volume') ?? $this->getMeta('qty') ?? $this->weight ?? 0);
        };
    }

    public static function energyVolumeLiters()
    {
        return function () {
            /** @var Entity $this */
            return EnergyOps::toLiters($this->energyQuantity(), $this->energyUnit(), $this->energyProduct());
        };
    }
}
