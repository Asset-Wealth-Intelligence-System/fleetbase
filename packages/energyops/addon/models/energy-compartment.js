import Model, { attr, belongsTo } from '@ember-data/model';
import { format as formatDate, isValid as isValidDate } from 'date-fns';

export default class EnergyCompartmentModel extends Model {
    /** @ids */
    @attr('string') uuid;
    @attr('string') public_id;
    @attr('string') company_uuid;
    @attr('string') owner_type;
    @attr('string') owner_uuid;
    @attr('string') dedicated_product_uuid;
    @attr('string') last_product_uuid;

    /** @relationships */
    @belongsTo('energy-product', { async: false, inverse: null }) dedicated_product;
    @belongsTo('energy-product', { async: false, inverse: null }) last_product;

    /** @attributes */
    @attr('raw') owner;
    @attr('string') code;
    @attr('string') name;
    @attr('number') sequence;
    @attr('number') capacity_volume;
    @attr('string') unit;
    @attr('number') max_fill_percent;
    @attr('number') residual_volume;
    @attr('boolean') requires_flush;
    @attr('string') status;
    @attr('raw') meta;

    /** @dates */
    @attr('date') created_at;
    @attr('date') updated_at;

    /** @computed */
    get displayName() {
        return this.name || this.code || this.public_id;
    }

    get ownerName() {
        return this.owner?.display_name ?? this.owner?.name ?? this.owner?.plate_number ?? this.owner?.public_id ?? null;
    }

    get safeCapacity() {
        const pct = Number(this.max_fill_percent ?? 100);
        return (Number(this.capacity_volume) || 0) * (pct / 100);
    }

    get availableVolume() {
        return Math.max(0, this.safeCapacity - (Number(this.residual_volume) || 0));
    }

    get fillPercent() {
        const capacity = Number(this.capacity_volume) || 0;
        if (!capacity) return 0;
        return Math.min(100, Math.round(((Number(this.residual_volume) || 0) / capacity) * 100));
    }

    get lastProductName() {
        return this.last_product?.name ?? this.last_product?.code ?? null;
    }

    get dedicatedProductName() {
        return this.dedicated_product?.name ?? this.dedicated_product?.code ?? null;
    }

    get createdAt() {
        return isValidDate(this.created_at) ? formatDate(this.created_at, 'PP HH:mm') : null;
    }
}
