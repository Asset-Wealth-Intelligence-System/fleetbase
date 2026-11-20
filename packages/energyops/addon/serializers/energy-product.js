import EnergySerializer from './energy';

export default class EnergyProductSerializer extends EnergySerializer {
    get attrs() {
        return {
            operation: { deserialize: 'records', serialize: false },
        };
    }
}
