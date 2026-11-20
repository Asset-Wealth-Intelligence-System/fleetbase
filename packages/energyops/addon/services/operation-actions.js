import EnergyResourceActionService from './energy-resource-action';

export default class OperationActionsService extends EnergyResourceActionService {
    constructor() {
        super(...arguments);
        this.initializeEnergyResource('energy-operation', {
            resourceRoute: 'operations',
            componentPrefix: 'energy-operation',
            defaultAttributes: { status: 'active', unit: 'L', family: 'glp', allow_split_delivery: true, block_over_ullage: true, default_service_seconds: 900 },
        });
    }
}
