import EnergySerializer from './energy';

export default class EnergyLoadPlanSerializer extends EnergySerializer {
    get attrs() {
        return {
            vehicle: { deserialize: 'records', serialize: false },
            operation: { deserialize: 'records', serialize: false },
            depot: { deserialize: 'records', serialize: false },
            files: { deserialize: 'records', serialize: false },
            lines: { deserialize: 'records', serialize: false },
            draws: { deserialize: 'records', serialize: false },
            tickets: { deserialize: 'records', serialize: false },
        };
    }
}
