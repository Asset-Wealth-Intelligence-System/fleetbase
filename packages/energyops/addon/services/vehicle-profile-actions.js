import EnergyResourceActionService from './energy-resource-action';
import { task } from 'ember-concurrency';

export default class VehicleProfileActionsService extends EnergyResourceActionService {
    constructor() {
        super(...arguments);
        this.initializeEnergyResource('energy-vehicle-profile', {
            resourceRoute: 'fleet',
            componentPrefix: 'energy-vehicle-profile',
            resourceKey: 'profile',
            defaultAttributes: { status: 'active', is_tractor: false, has_meter: true },
        });
    }

    /**
     * Find or build the energy profile for a Fleet-Ops vehicle.
     */
    @task *loadForVehicle(vehicle) {
        if (!vehicle?.id) return null;

        const existing = yield this.store.query('energy-vehicle-profile', { vehicle_uuid: vehicle.id, limit: 1 });
        const profile = existing?.firstObject ?? existing?.[0];
        if (profile) {
            return profile;
        }

        return this.createNewInstance({ vehicle, vehicle_uuid: vehicle.id });
    }
}
