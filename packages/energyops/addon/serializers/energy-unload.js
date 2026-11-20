import EnergySerializer from './energy';

export default class EnergyUnloadSerializer extends EnergySerializer {
    get attrs() {
        return {
            vehicle: { deserialize: 'records', serialize: false },
            driver: { deserialize: 'records', serialize: false },
            inventory_place: { deserialize: 'records', serialize: false },
            inventory_tank: { deserialize: 'records', serialize: false },
            lines: { deserialize: 'records', serialize: false },
            files: { deserialize: 'records', serialize: false },
        };
    }
}
