import Model, { attr, belongsTo, hasMany } from '@ember-data/model';
import { format as formatDate, isValid as isValidDate } from 'date-fns';

export default class EnergyUnloadModel extends Model {
    /** @ids */
    @attr('string') uuid;
    @attr('string') public_id;
    @attr('string') company_uuid;
    @attr('string') created_by_uuid;
    @attr('string') recorded_by_uuid;
    @attr('string') vehicle_uuid;
    @attr('string') driver_uuid;
    @attr('string') load_plan_uuid;
    @attr('string') manifest_uuid;
    @attr('string') inventory_place_uuid;
    @attr('string') inventory_tank_uuid;

    /** @relationships */
    @belongsTo('vehicle', { async: false, inverse: null }) vehicle;
    @belongsTo('driver', { async: false, inverse: null }) driver;
    @belongsTo('place', { async: false, inverse: null }) inventory_place;
    @belongsTo('energy-customer-tank', { async: false, inverse: null }) inventory_tank;
    @hasMany('energy-unload-line', { async: false, inverse: null }) lines;
    @hasMany('file', { async: false, inverse: null }) files;

    /** @attributes */
    @attr('raw') load_plan;
    @attr('string') unit;
    @attr('number') total_before_volume;
    @attr('number') total_unloaded_volume;
    @attr('number') total_remaining_volume;
    @attr('string') source;
    @attr('string') status;
    @attr('string') notes;
    @attr('raw') meta;

    /** @dates */
    @attr('date') unloaded_at;
    @attr('date') confirmed_at;
    @attr('date') voided_at;
    @attr('date') created_at;
    @attr('date') updated_at;

    /** @computed */
    get displayName() {
        return this.public_id;
    }

    get vehicleName() {
        return this.vehicle?.displayName ?? this.vehicle?.display_name ?? this.vehicle?.name ?? this.vehicle?.plate_number ?? null;
    }

    get driverName() {
        return this.driver?.name ?? null;
    }

    get inventoryPlaceName() {
        return this.inventory_place?.name ?? null;
    }

    get loadPlanId() {
        return this.load_plan?.public_id ?? null;
    }

    get sortedLines() {
        return [...(this.lines ?? [])].sort((a, b) => (a.sequence ?? 0) - (b.sequence ?? 0));
    }

    get proofFiles() {
        return this.files ? [...this.files] : [];
    }

    get photoFiles() {
        return this.proofFiles.filter((file) => file.type === 'unload_photo');
    }

    get signatureFiles() {
        return this.proofFiles.filter((file) => file.type === 'unload_signature');
    }

    get hasProof() {
        return this.proofFiles.length > 0;
    }

    get isDraft() {
        return this.status === 'draft';
    }

    get isConfirmed() {
        return this.status === 'confirmed';
    }

    get isVoided() {
        return this.status === 'voided';
    }

    get unloadedAt() {
        return isValidDate(this.unloaded_at) ? formatDate(this.unloaded_at, 'PP HH:mm') : null;
    }

    get confirmedAt() {
        return isValidDate(this.confirmed_at) ? formatDate(this.confirmed_at, 'PP HH:mm') : null;
    }

    get createdAt() {
        return isValidDate(this.created_at) ? formatDate(this.created_at, 'PP HH:mm') : null;
    }
}
