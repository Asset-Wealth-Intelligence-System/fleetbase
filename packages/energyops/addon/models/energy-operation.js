import Model, { attr, belongsTo, hasMany } from '@ember-data/model';
import { format as formatDate, isValid as isValidDate } from 'date-fns';

export default class EnergyOperationModel extends Model {
    /** @ids */
    @attr('string') uuid;
    @attr('string') public_id;
    @attr('string') company_uuid;
    @attr('string') depot_place_uuid;

    /** @relationships */
    @belongsTo('place', { async: false, inverse: null }) depot;
    @hasMany('energy-product', { async: false, inverse: 'operation' }) products;

    /** @attributes */
    @attr('string') code;
    @attr('string') name;
    @attr('string') family;
    @attr('string') unit;
    @attr('boolean') allow_split_delivery;
    @attr('boolean') block_over_ullage;
    @attr('number') default_service_seconds;
    @attr('number') products_count;
    @attr('string') status;
    @attr('raw') meta;

    /** @dates */
    @attr('date') created_at;
    @attr('date') updated_at;

    /** @computed */
    get displayName() {
        return this.name || this.code || this.public_id;
    }

    get depotName() {
        return this.depot?.name ?? this.depot?.address ?? null;
    }

    get createdAt() {
        return isValidDate(this.created_at) ? formatDate(this.created_at, 'PP HH:mm') : null;
    }

    get updatedAt() {
        return isValidDate(this.updated_at) ? formatDate(this.updated_at, 'PP HH:mm') : null;
    }
}
