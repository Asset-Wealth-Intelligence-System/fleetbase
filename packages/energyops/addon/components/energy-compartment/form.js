import Component from '@glimmer/component';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';
import { VOLUME_UNITS, RESOURCE_STATUSES, COMPARTMENT_OWNER_TYPES, translateOptions } from '../../utils/energy-options';

export default class EnergyCompartmentFormComponent extends Component {
    @service intl;
    unitOptions = VOLUME_UNITS;
    statusOptions = RESOURCE_STATUSES;
    get ownerTypeOptions() {
        return translateOptions(this.intl, COMPARTMENT_OWNER_TYPES);
    }

    get resource() {
        return this.args.resource ?? this.args.compartment ?? this.args.model ?? this.args.options?.resource;
    }

    /** Owner is fixed when created from a vehicle/asset context. */
    get ownerLocked() {
        return Boolean(this.resource?.owner_uuid) && this.args.allowOwnerChange !== true;
    }

    @action setDedicatedProduct(product) {
        this.resource.dedicated_product = product;
        this.resource.dedicated_product_uuid = product?.id ?? null;
    }

    @action setOwner(owner) {
        this.resource.owner_uuid = owner?.id ?? null;
        this.resource.owner = owner ? { name: owner.displayName ?? owner.name, public_id: owner.public_id } : null;
    }

    @action toggle(attribute, value) {
        this.resource[attribute] = value;
    }
}
