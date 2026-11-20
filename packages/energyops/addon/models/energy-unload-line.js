import Model, { attr, belongsTo } from '@ember-data/model';

export default class EnergyUnloadLineModel extends Model {
    /** @ids */
    @attr('string') uuid;
    @attr('string') public_id;
    @attr('string') unload_uuid;
    @attr('string') compartment_uuid;
    @attr('string') product_uuid;

    /** @relationships */
    @belongsTo('energy-unload', { async: false, inverse: null }) unload;
    @belongsTo('energy-compartment', { async: false, inverse: null }) compartment;
    @belongsTo('energy-product', { async: false, inverse: null }) product;

    /** @attributes */
    @attr('number') before_volume;
    @attr('number') unloaded_volume;
    @attr('number') remaining_volume;
    @attr('string') unit;
    @attr('number') sequence;
    @attr('raw') meta;

    /** @dates */
    @attr('date') created_at;
    @attr('date') updated_at;

    /** @computed */
    get compartmentCode() {
        return this.compartment?.code ?? this.compartment?.name ?? null;
    }

    get productName() {
        return this.product?.name ?? this.product?.code ?? null;
    }
}
