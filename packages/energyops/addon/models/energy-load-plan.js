import Model, { attr, belongsTo, hasMany } from '@ember-data/model';
import { format as formatDate, isValid as isValidDate } from 'date-fns';

export default class EnergyLoadPlanModel extends Model {
    /** @ids */
    @attr('string') uuid;
    @attr('string') public_id;
    @attr('string') company_uuid;
    @attr('string') vehicle_uuid;
    @attr('string') operation_uuid;
    @attr('string') manifest_uuid;
    @attr('string') depot_place_uuid;

    /** @relationships */
    @belongsTo('vehicle', { async: false, inverse: null }) vehicle;
    @belongsTo('energy-operation', { async: false, inverse: null }) operation;
    @belongsTo('place', { async: false, inverse: null }) depot;
    @hasMany('file', { async: false, inverse: null }) files;
    @hasMany('energy-load-line', { async: false, inverse: 'load_plan' }) lines;
    @hasMany('energy-stop-draw', { async: false, inverse: null }) draws;
    @hasMany('energy-delivery-ticket', { async: false, inverse: null }) tickets;

    /** @attributes */
    @attr('string') status;
    @attr('number') total_planned_volume;
    @attr('number') total_loaded_volume;
    @attr('number') orders_count;
    @attr('raw') load_proof;
    @attr('raw') meta;

    /** @dates */
    @attr('date') packed_at;
    @attr('date') loaded_at;
    @attr('date') closed_at;
    @attr('date') created_at;
    @attr('date') updated_at;

    /** @computed */
    get displayName() {
        return this.public_id;
    }

    get vehicleName() {
        return this.vehicle?.displayName ?? this.vehicle?.display_name ?? this.vehicle?.plate_number ?? null;
    }

    get operationName() {
        return this.operation?.name ?? this.operation?.code ?? null;
    }

    get depotName() {
        return this.depot?.name ?? this.depot?.address ?? null;
    }

    get proofFiles() {
        return this.files ? [...this.files] : [];
    }

    get hasLoadProof() {
        return this.proofFiles.length > 0 || Boolean(this.load_proof?.invoice_number || this.load_proof?.ticket_number);
    }

    /** Pedidos may be added from the product on board once the truck is loaded. */
    get isOnBoard() {
        return ['loaded', 'in_transit'].includes(this.status);
    }

    get plannedVolume() {
        if (this.total_planned_volume != null) return Number(this.total_planned_volume);
        return this.lines.reduce((sum, line) => sum + (Number(line.planned_volume) || 0), 0);
    }

    get loadedVolume() {
        if (this.total_loaded_volume != null) return Number(this.total_loaded_volume);
        return this.lines.reduce((sum, line) => sum + (Number(line.loaded_volume) || 0), 0);
    }

    get isOpen() {
        return ['draft', 'packed', 'loaded', 'in_transit'].includes(this.status);
    }

    get canConfirmLoad() {
        return ['draft', 'packed'].includes(this.status);
    }

    get canClose() {
        return this.isOpen;
    }

    get packedAt() {
        return isValidDate(this.packed_at) ? formatDate(this.packed_at, 'PP HH:mm') : null;
    }

    get loadedAt() {
        return isValidDate(this.loaded_at) ? formatDate(this.loaded_at, 'PP HH:mm') : null;
    }

    get closedAt() {
        return isValidDate(this.closed_at) ? formatDate(this.closed_at, 'PP HH:mm') : null;
    }

    get createdAt() {
        return isValidDate(this.created_at) ? formatDate(this.created_at, 'PP HH:mm') : null;
    }
}
