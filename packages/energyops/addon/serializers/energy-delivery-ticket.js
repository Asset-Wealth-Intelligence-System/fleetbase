import EnergySerializer from './energy';

export default class EnergyDeliveryTicketSerializer extends EnergySerializer {
    get attrs() {
        return {
            order: { deserialize: 'records', serialize: false },
            load_plan: { deserialize: 'records', serialize: false },
            stop_draw: { deserialize: 'records', serialize: false },
            compartment: { deserialize: 'records', serialize: false },
            product: { deserialize: 'records', serialize: false },
            customer_tank: { deserialize: 'records', serialize: false },
        };
    }
}
