import Model, { attr, belongsTo } from '@ember-data/model';

export default class EnergyStopDrawModel extends Model {
    /** @ids */
    @attr('string') uuid;
    @attr('string') public_id;
    @attr('string') company_uuid;
    @attr('string') load_plan_uuid;
    @attr('string') load_line_uuid;
    @attr('string') order_uuid;
    @attr('string') manifest_stop_uuid;
    @attr('string') compartment_uuid;
    @attr('string') product_uuid;

    /** @relationships */
    @belongsTo('energy-compartment', { async: false, inverse: null }) compartment;
    @belongsTo('energy-product', { async: false, inverse: null }) product;
    @belongsTo('order', { async: false, inverse: null }) order;

    /** @attributes */
    @attr('number') planned_qty;
    @attr('number') metered_qty;
    @attr('number') sequence;
    @attr('raw') meta;

    /** @computed */
    get compartmentCode() {
        return this.compartment?.code ?? null;
    }

    get productName() {
        return this.product?.name ?? this.product?.code ?? null;
    }

    /** Single-line label for selects: "C1 · Propane · 1,200" */
    get label() {
        const qty = this.planned_qty != null ? Number(this.planned_qty).toLocaleString(undefined, { maximumFractionDigits: 1 }) : null;
        return [this.compartmentCode, this.productName, qty].filter(Boolean).join(' · ');
    }

    get orderId() {
        return this.order?.public_id ?? this.order_uuid;
    }

    get isDelivered() {
        return this.metered_qty != null;
    }
}
