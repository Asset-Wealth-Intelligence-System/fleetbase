import EnergyResourceActionService from './energy-resource-action';

export default class CompatibilityRuleActionsService extends EnergyResourceActionService {
    constructor() {
        super(...arguments);
        this.initializeEnergyResource('energy-compatibility-rule', {
            resourceRoute: 'compatibility-rules',
            componentPrefix: 'energy-compatibility-rule',
            resourceKey: 'rule',
            defaultAttributes: { status: 'active', action: 'flush_required', same_family_only: false },
        });
    }
}
