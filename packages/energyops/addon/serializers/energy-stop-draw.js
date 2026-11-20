import EnergySerializer from './energy';

export default class EnergyStopDrawSerializer extends EnergySerializer {
    get attrs() {
        return {
            compartment: { deserialize: 'records', serialize: false },
            product: { deserialize: 'records', serialize: false },
            order: { deserialize: 'records', serialize: false },
        };
    }
}
