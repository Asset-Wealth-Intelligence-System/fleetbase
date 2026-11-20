import Model, { attr, belongsTo } from '@ember-data/model';

export default class EnergyLoadLineModel extends Model {
    /** @ids */
    @attr('string') uuid;
    @attr('string') public_id;
    @attr('string') company_uuid;
    @attr('string') load_plan_uuid;
    @attr('string') compartment_uuid;
    @attr('string') product_uuid;
    @attr('string') order_uuid;
    @attr('string') entity_uuid;

    /** @relationships */
    @belongsTo('energy-load-plan', { async: false, inverse: 'lines' }) load_plan;
    @belongsTo('energy-compartment', { async: false, inverse: null }) compartment;
    @belongsTo('energy-product', { async: false, inverse: null }) product;
    @belongsTo('order', { async: false, inverse: null }) order;

    /** @attributes */
    @attr('number') planned_volume;
    @attr('number') loaded_volume;
    @attr('number') sequence;
    @attr('raw') meta;

    /** @computed */
    get compartmentCode() {
        return this.compartment?.code ?? null;
    }

    get productName() {
        return this.product?.name ?? this.product?.code ?? null;
    }

    get orderId() {
        return this.order?.public_id ?? this.order_uuid;
    }

    get variance() {
        if (this.loaded_volume == null) return null;
        return Number(this.loaded_volume) - Number(this.planned_volume || 0);
    }
}
