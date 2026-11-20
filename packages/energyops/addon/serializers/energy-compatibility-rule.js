import EnergySerializer from './energy';

export default class EnergyCompatibilityRuleSerializer extends EnergySerializer {
    get attrs() {
        return {
            from_product: { deserialize: 'records', serialize: false },
            to_product: { deserialize: 'records', serialize: false },
            from_operation: { deserialize: 'records', serialize: false },
            to_operation: { deserialize: 'records', serialize: false },
        };
    }
}
