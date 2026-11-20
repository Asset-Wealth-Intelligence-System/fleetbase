import Model, { attr, belongsTo } from '@ember-data/model';
import { format as formatDate, isValid as isValidDate } from 'date-fns';
import { getOwner } from '@ember/application';

export default class EnergyCustomerTankModel extends Model {
    /** @ids */
    @attr('string') uuid;
    @attr('string') public_id;
    @attr('string') company_uuid;
    @attr('string') place_uuid;
    @attr('string') product_uuid;

    /** @relationships */
    @belongsTo('place', { async: false, inverse: null }) place;
    @belongsTo('energy-product', { async: false, inverse: null }) product;

    /** @attributes */
    @attr('string') name;
    @attr('string') serial_number;
    @attr('string') tank_type;
    @attr('number') capacity_volume;
    @attr('string') unit;
    @attr('number') safe_fill_percent;
    @attr('number') last_known_volume;
    @attr('number') reorder_point;
    @attr('number') ullage;
    @attr('date') last_delivery_at;
    @attr('string') status;
    @attr('raw') meta;

    /** @dates */
    @attr('date') created_at;
    @attr('date') updated_at;

    /** @computed */
    get displayName() {
        return this.name || this.serial_number || this.public_id;
    }

    get placeName() {
        return this.place?.name ?? this.place?.address ?? this.place?.street1 ?? null;
    }

    get productName() {
        return this.product?.name ?? this.product?.code ?? null;
    }

    get safeFillVolume() {
        return (Number(this.capacity_volume) || 0) * ((Number(this.safe_fill_percent) || 0) / 100);
    }

    get computedUllage() {
        return Math.max(0, this.safeFillVolume - (Number(this.last_known_volume) || 0));
    }

    /** Single-line label for selects: "Tank A · Propane · 1,300 L ullage" */
    get label() {
        const intl = getOwner(this)?.lookup('service:intl');
        const volume = `${Math.round(this.computedUllage).toLocaleString()} ${this.unit ?? 'L'}`;
        const capacityKnown = (Number(this.capacity_volume) || 0) > 0;
        // unsurveyed tanks (capacity 0) have no meaningful ullage
        const ullage = !capacityKnown ? (intl?.t('energyops.tank.capacity-pending') ?? 'capacity pending') : intl ? intl.t('energyops.tank.ullage-label', { volume }) : `${volume} ullage`;
        return [this.name, this.productName, ullage].filter(Boolean).join(' · ');
    }

    get fillPercent() {
        const capacity = Number(this.capacity_volume) || 0;
        if (!capacity) return 0;
        return Math.min(100, Math.round(((Number(this.last_known_volume) || 0) / capacity) * 100));
    }

    get needsReorder() {
        return this.reorder_point != null && Number(this.last_known_volume) <= Number(this.reorder_point);
    }

    get createdAt() {
        return isValidDate(this.created_at) ? formatDate(this.created_at, 'PP HH:mm') : null;
    }

    get lastDeliveryAt() {
        return isValidDate(this.last_delivery_at) ? formatDate(this.last_delivery_at, 'PP HH:mm') : null;
    }
}
