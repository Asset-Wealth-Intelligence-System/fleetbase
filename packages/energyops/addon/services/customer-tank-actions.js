import EnergyResourceActionService from './energy-resource-action';

export default class CustomerTankActionsService extends EnergyResourceActionService {
    constructor() {
        super(...arguments);
        this.initializeEnergyResource('energy-customer-tank', {
            resourceRoute: 'customer-tanks',
            componentPrefix: 'energy-customer-tank',
            resourceKey: 'tank',
            defaultAttributes: { status: 'active', unit: 'L', safe_fill_percent: 85, last_known_volume: 0, tank_type: 'aboveground' },
        });
    }

    /**
     * Open the create panel pre-bound to a place (used from the Fleet-Ops place form).
     */
    createForPlace(place, options = {}) {
        return this.panel.create({ place, place_uuid: place?.id }, options);
    }
}
