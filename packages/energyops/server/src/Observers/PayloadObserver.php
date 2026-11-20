<?php

namespace Fleetbase\EnergyOps\Observers;

use Fleetbase\EnergyOps\Models\Operation;
use Fleetbase\EnergyOps\Support\EnergyOps;
use Fleetbase\FleetOps\Models\Payload;

/**
 * Prefills the loading station (pickup) and the descarga site (return) on GLP / WOP payloads.
 *
 * The payload is inserted right after the order, with `type` = order config key, so this is the
 * first moment the route can be completed. The pickup is later re-pointed to the packed vehicle's
 * habitual depot by `LoadPlanService::persistPacks` when it was filled here automatically.
 */
class PayloadObserver
{
    public function creating(Payload $payload): void
    {
        $operationCode = $this->operationCode($payload->type);
        if (!$operationCode || !EnergyOps::enabled($payload->company_uuid)) {
            return;
        }

        $meta = is_array($payload->meta) ? $payload->meta : [];

        if (empty($payload->pickup_uuid)) {
            $operation = Operation::where('company_uuid', $payload->company_uuid)->where('code', $operationCode)->first();
            $depot     = EnergyOps::defaultDepotForVehicle(null, $operation);
            if ($depot) {
                $payload->pickup_uuid        = $depot->uuid;
                $meta['energyops_pickup_auto'] = true;
            }
        }

        if (empty($payload->return_uuid)) {
            $inventory = EnergyOps::inventoryPlace($payload->company_uuid);
            if ($inventory) {
                $payload->return_uuid          = $inventory->uuid;
                $meta['energyops_return_auto'] = true;
            }
        }

        if ($meta !== ($payload->meta ?? [])) {
            $payload->meta = $meta;
        }
    }

    protected function operationCode(?string $type): ?string
    {
        return match ($type) {
            EnergyOps::GLP_CONFIG_KEY, EnergyOps::GLP_CONFIG_NS => 'glp',
            EnergyOps::WOP_CONFIG_KEY, EnergyOps::WOP_CONFIG_NS => 'wop',
            default => null,
        };
    }
}
