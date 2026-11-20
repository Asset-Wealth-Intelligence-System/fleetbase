import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { task } from 'ember-concurrency';

/**
 * Energy profile for a Fleet-Ops vehicle. Self-saving so it can live inside
 * the tanker configuration panel and the Fleet-Ops vehicle form slot.
 */
export default class EnergyVehicleProfileFormComponent extends Component {
    @service vehicleProfileActions;
    @service notifications;
    @service intl;

    get resource() {
        return this.args.resource ?? this.args.profile ?? this.args.model;
    }

    get vehicle() {
        return this.args.vehicle ?? this.resource?.vehicle;
    }

    get canSave() {
        return this.resource && (this.resource.isNew || this.resource.hasDirtyAttributes) && !this.save.isRunning;
    }

    @action setOperation(operation) {
        this.resource.operation = operation;
        this.resource.operation_uuid = operation?.id ?? null;
    }

    @action setDefaultDepot(place) {
        this.resource.default_depot = place;
        this.resource.default_depot_place_uuid = place?.id ?? null;
    }

    @action setCoupledAsset(asset) {
        this.resource.coupled_asset = asset;
        this.resource.coupled_asset_uuid = asset?.id ?? null;
    }

    @action toggle(attribute, value) {
        this.resource[attribute] = value;
        if (attribute === 'is_tractor' && !value) {
            this.setCoupledAsset(null);
        }
    }

    @task *save() {
        if (!this.resource) return;
        if (this.vehicle?.id && !this.resource.vehicle_uuid) {
            this.resource.vehicle_uuid = this.vehicle.id;
        }
        try {
            yield this.resource.save();
            this.notifications.success(this.intl.t('energyops.vehicle-profile.saved'));
            if (typeof this.args.onSaved === 'function') {
                this.args.onSaved(this.resource);
            }
        } catch (error) {
            this.notifications.serverError(error);
        }
    }
}
