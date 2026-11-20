import Component from '@glimmer/component';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';
import { VOLUME_UNITS, RESOURCE_STATUSES, TANK_TYPES, translateOptions } from '../../utils/energy-options';

export default class CustomerTankFormComponent extends Component {
    @service intl;
    unitOptions = VOLUME_UNITS;
    statusOptions = RESOURCE_STATUSES;
    get tankTypeOptions() {
        return translateOptions(this.intl, TANK_TYPES);
    }

    get resource() {
        return this.args.resource ?? this.args.tank ?? this.args.model;
    }

    /** When rendered inside a place form we lock the place. */
    get placeLocked() {
        return this.args.lockPlace === true || this.args.overlay?.lockPlace === true;
    }

    @action setPlace(place) {
        this.resource.place = place;
        this.resource.place_uuid = place?.id ?? null;
    }

    @action setProduct(product) {
        this.resource.product = product;
        this.resource.product_uuid = product?.id ?? null;
        if (product?.default_unit && !this.resource.unit) {
            this.resource.unit = product.default_unit;
        }
    }
}
