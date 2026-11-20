<?php

namespace Fleetbase\EnergyOps\Http\Controllers\Internal\v1;

use Fleetbase\EnergyOps\Http\Controllers\EnergyOpsController;
use Fleetbase\EnergyOps\Http\Requests\CreateCompatibilityRuleRequest;
use Fleetbase\EnergyOps\Http\Requests\UpdateCompatibilityRuleRequest;

class CompatibilityRuleController extends EnergyOpsController
{
    /**
     * The resource to query.
     *
     * @var string
     */
    public $resource = 'compatibility-rule';

    /**
     * The form request used to validate creation.
     */
    public $createRequest = CreateCompatibilityRuleRequest::class;

    /**
     * The form request used to validate updates.
     */
    public $updateRequest = UpdateCompatibilityRuleRequest::class;
}
