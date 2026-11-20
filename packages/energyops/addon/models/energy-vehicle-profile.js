import Model, { attr, belongsTo } from '@ember-data/model';

export default class EnergyVehicleProfileModel extends Model {
    /** @ids */
    @attr('string') uuid;
    @attr('string') public_id;
    @attr('string') company_uuid;
    @attr('string') vehicle_uuid;
    @attr('string') operation_uuid;
    @attr('string') coupled_asset_uuid;
    @attr('string') default_depot_place_uuid;

    /** @relationships */
    @belongsTo('vehicle', { async: false, inverse: null }) vehicle;
    @belongsTo('energy-operation', { async: false, inverse: null }) operation;
    @belongsTo('asset', { async: false, inverse: null }) coupled_asset;
    @belongsTo('place', { async: false, inverse: null }) default_depot;

    /** @attributes */
    @attr('boolean') is_tractor;
    @attr('number') max_payload_kg;
    @attr('number') pump_rate_lpm;
    @attr('boolean') has_meter;
    @attr('string') meter_serial;
    @attr('number') compartments_count;
    @attr('number') total_capacity;
    @attr('string') status;
    @attr('raw') meta;

    /** @dates */
    @attr('date') created_at;
    @attr('date') updated_at;

    /** @computed */
    get displayName() {
        return this.vehicle?.displayName ?? this.vehicle?.display_name ?? this.public_id;
    }

    get operationName() {
        return this.operation?.name ?? this.operation?.code ?? null;
    }

    get coupledAssetName() {
        return this.coupled_asset?.name ?? this.coupled_asset?.code ?? null;
    }

    get defaultDepotName() {
        return this.default_depot?.name ?? this.default_depot?.address ?? null;
    }
}
