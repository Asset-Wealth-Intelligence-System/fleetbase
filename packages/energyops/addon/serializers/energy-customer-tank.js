import EnergySerializer from './energy';

export default class EnergyCustomerTankSerializer extends EnergySerializer {
    get attrs() {
        return {
            place: { deserialize: 'records', serialize: false },
            product: { deserialize: 'records', serialize: false },
        };
    }
}
