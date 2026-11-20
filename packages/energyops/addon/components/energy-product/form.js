import Component from '@glimmer/component';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';
import { VOLUME_UNITS, RESOURCE_STATUSES, HAZMAT_CLASSES, translateOptions } from '../../utils/energy-options';

export default class ProductFormComponent extends Component {
    @service intl;
    unitOptions = VOLUME_UNITS;
    statusOptions = RESOURCE_STATUSES;
    get hazmatOptions() {
        return translateOptions(this.intl, HAZMAT_CLASSES);
    }

    get resource() {
        return this.args.resource ?? this.args.product ?? this.args.model;
    }

    @action setOperation(operation) {
        this.resource.operation = operation;
        this.resource.operation_uuid = operation?.id ?? null;
        if (operation?.unit && !this.resource.default_unit) {
            this.resource.default_unit = operation.unit;
        }
    }

    @action setColor(event) {
        this.resource.color = event.target.value;
    }

    @action toggle(attribute, value) {
        this.resource[attribute] = value;
    }
}
