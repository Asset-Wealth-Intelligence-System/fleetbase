import Component from '@glimmer/component';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';
import { OPERATION_FAMILIES, VOLUME_UNITS, RESOURCE_STATUSES, translateOptions } from '../../utils/energy-options';

export default class OperationFormComponent extends Component {
    @service intl;
    get familyOptions() {
        return translateOptions(this.intl, OPERATION_FAMILIES);
    }
    unitOptions = VOLUME_UNITS;
    statusOptions = RESOURCE_STATUSES;

    get resource() {
        return this.args.resource ?? this.args.operation ?? this.args.model;
    }

    @action setDepot(place) {
        this.resource.depot = place;
        this.resource.depot_place_uuid = place?.id ?? null;
    }

    @action toggle(attribute, value) {
        this.resource[attribute] = value;
    }
}
