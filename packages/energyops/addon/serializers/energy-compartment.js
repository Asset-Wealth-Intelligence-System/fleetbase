import EnergySerializer from './energy';

export default class EnergyCompartmentSerializer extends EnergySerializer {
    get attrs() {
        return {
            dedicated_product: { deserialize: 'records', serialize: false },
            last_product: { deserialize: 'records', serialize: false },
        };
    }
}
