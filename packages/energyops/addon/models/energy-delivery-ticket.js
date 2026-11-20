import Model, { attr, belongsTo } from '@ember-data/model';
import { format as formatDate, isValid as isValidDate } from 'date-fns';

export default class EnergyDeliveryTicketModel extends Model {
    /** @ids */
    @attr('string') uuid;
    @attr('string') public_id;
    @attr('string') company_uuid;
    @attr('string') order_uuid;
    @attr('string') load_plan_uuid;
    @attr('string') stop_draw_uuid;
    @attr('string') compartment_uuid;
    @attr('string') product_uuid;
    @attr('string') customer_tank_uuid;

    /** @relationships */
    @belongsTo('order', { async: false, inverse: null }) order;
    @belongsTo('energy-load-plan', { async: false, inverse: null }) load_plan;
    @belongsTo('energy-stop-draw', { async: false, inverse: null }) stop_draw;
    @belongsTo('energy-compartment', { async: false, inverse: null }) compartment;
    @belongsTo('energy-product', { async: false, inverse: null }) product;
    @belongsTo('energy-customer-tank', { async: false, inverse: null }) customer_tank;

    /** @attributes */
    @attr('number') metered_qty;
    @attr('number') planned_qty;
    @attr('number') variance_qty;
    @attr('string') variance_reason;
    @attr('string') unit;
    @attr('string') seal_in;
    @attr('string') seal_out;
    @attr('string') invoice_number;
    @attr('string') meter_start;
    @attr('string') meter_end;
    @attr('string') exception_type;
    @attr('string') status;
    @attr('raw') meta;

    /** @dates */
    @attr('date') issued_at;
    @attr('date') created_at;
    @attr('date') updated_at;

    /** @computed */
    get displayName() {
        return this.public_id;
    }

    get orderId() {
        return this.order?.public_id ?? this.order_uuid;
    }

    get productName() {
        return this.product?.name ?? this.product?.code ?? null;
    }

    get compartmentCode() {
        return this.compartment?.code ?? null;
    }

    get customerTankName() {
        return this.customer_tank?.displayName ?? this.customer_tank?.name ?? null;
    }

    get variancePercent() {
        const planned = Number(this.planned_qty) || 0;
        if (!planned) return null;
        return Math.round(((Number(this.variance_qty) || 0) / planned) * 1000) / 10;
    }

    get isVoid() {
        return this.status === 'void';
    }

    get issuedAt() {
        const date = this.issued_at ?? this.created_at;
        return isValidDate(date) ? formatDate(date, 'PP HH:mm') : null;
    }

    get createdAt() {
        return isValidDate(this.created_at) ? formatDate(this.created_at, 'PP HH:mm') : null;
    }
}
