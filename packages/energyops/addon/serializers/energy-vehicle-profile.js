import EnergySerializer from './energy';

export default class EnergyVehicleProfileSerializer extends EnergySerializer {
    get attrs() {
        return {
            vehicle: { deserialize: 'records', serialize: false },
            operation: { deserialize: 'records', serialize: false },
            coupled_asset: { deserialize: 'records', serialize: false },
            default_depot: { deserialize: 'records', serialize: false },
        };
    }
}
