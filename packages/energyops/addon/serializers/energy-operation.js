import EnergySerializer from './energy';

export default class EnergyOperationSerializer extends EnergySerializer {
    get attrs() {
        return {
            depot: { deserialize: 'records', serialize: false },
            products: { deserialize: 'records', serialize: false },
        };
    }
}
