import EnergyResourceActionService from './energy-resource-action';

export default class ProductActionsService extends EnergyResourceActionService {
    constructor() {
        super(...arguments);
        this.initializeEnergyResource('energy-product', {
            resourceRoute: 'products',
            componentPrefix: 'energy-product',
            defaultAttributes: { status: 'active', active: true, default_unit: 'L', density: 0.54 },
        });
    }
}
