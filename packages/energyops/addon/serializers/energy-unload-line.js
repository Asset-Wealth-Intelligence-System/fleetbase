import EnergySerializer from './energy';

export default class EnergyUnloadLineSerializer extends EnergySerializer {
    get attrs() {
        return {
            unload: { deserialize: 'records', serialize: false },
            compartment: { deserialize: 'records', serialize: false },
            product: { deserialize: 'records', serialize: false },
        };
    }
}
