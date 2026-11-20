import Model, { attr, belongsTo } from '@ember-data/model';
import { format as formatDate, isValid as isValidDate } from 'date-fns';

export default class EnergyCompatibilityRuleModel extends Model {
    /** @ids */
    @attr('string') uuid;
    @attr('string') public_id;
    @attr('string') company_uuid;
    @attr('string') from_product_uuid;
    @attr('string') to_product_uuid;
    @attr('string') from_operation_uuid;
    @attr('string') to_operation_uuid;

    /** @relationships */
    @belongsTo('energy-product', { async: false, inverse: null }) from_product;
    @belongsTo('energy-product', { async: false, inverse: null }) to_product;
    @belongsTo('energy-operation', { async: false, inverse: null }) from_operation;
    @belongsTo('energy-operation', { async: false, inverse: null }) to_operation;

    /** @attributes */
    @attr('string') action;
    @attr('boolean') same_family_only;
    @attr('number') flush_volume;
    @attr('string') notes;
    @attr('string') status;
    @attr('raw') meta;

    /** @dates */
    @attr('date') created_at;
    @attr('date') updated_at;

    /** @computed */
    get displayName() {
        const from = this.from_product?.code ?? this.from_operation?.code ?? '*';
        const to = this.to_product?.code ?? this.to_operation?.code ?? '*';
        return `${from} → ${to}`;
    }

    get fromLabel() {
        return this.from_product?.name ?? this.from_operation?.name ?? 'Any';
    }

    get toLabel() {
        return this.to_product?.name ?? this.to_operation?.name ?? 'Any';
    }

    get createdAt() {
        return isValidDate(this.created_at) ? formatDate(this.created_at, 'PP HH:mm') : null;
    }
}
