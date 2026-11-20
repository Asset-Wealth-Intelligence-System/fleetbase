import Model, { attr, belongsTo } from '@ember-data/model';
import { format as formatDate, isValid as isValidDate } from 'date-fns';

export default class EnergyProductModel extends Model {
    /** @ids */
    @attr('string') uuid;
    @attr('string') public_id;
    @attr('string') company_uuid;
    @attr('string') operation_uuid;

    /** @relationships */
    @belongsTo('energy-operation', { async: false, inverse: 'products' }) operation;

    /** @attributes */
    @attr('string') code;
    @attr('string') name;
    @attr('string') description;
    @attr('number') density;
    @attr('string') un_number;
    @attr('string') hazmat_class;
    @attr('string') default_unit;
    @attr('string') skill;
    @attr('string') color;
    @attr('boolean') active;
    @attr('string') status;
    @attr('raw') meta;

    /** @dates */
    @attr('date') created_at;
    @attr('date') updated_at;

    /** @computed */
    get displayName() {
        return this.name || this.code || this.public_id;
    }

    get operationName() {
        return this.operation?.name ?? this.operation?.code ?? null;
    }

    get createdAt() {
        return isValidDate(this.created_at) ? formatDate(this.created_at, 'PP HH:mm') : null;
    }

    get updatedAt() {
        return isValidDate(this.updated_at) ? formatDate(this.updated_at, 'PP HH:mm') : null;
    }
}
