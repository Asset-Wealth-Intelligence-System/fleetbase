import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { getOwner } from '@ember/application';
import { action } from '@ember/object';
import registerEnergyOps from '../../utils/register-energy-ops';

const ROLE_CUSTOMER = 'customer';
const ROLE_DEPOT = 'depot';
const ROLE_INVENTORY = 'inventory';
const ENERGY_ROLES = [ROLE_DEPOT, ROLE_INVENTORY];

/**
 * Fleet-Ops slot `fleet-ops:component:place:form`.
 * Lets the operator flag a place as a loading station (depot) or the inventory site (descarga),
 * and record the site's receiving hours which prefill the order time window.
 *
 * Writes `place.type` (`depot` | `inventory` | previous value) and `place.meta.receiving_hours {start,end}`.
 */
export default class EnergyFleetOpsPlaceEnergyRoleComponent extends Component {
    @service abilities;
    @service intl;

    get place() {
        return this.args.resource ?? this.args.place;
    }

    get canSee() {
        return this.abilities.can('energy-ops see extension') && Boolean(this.place);
    }

    get disabled() {
        return this.args.disabled ?? false;
    }

    get meta() {
        const meta = this.place?.meta;
        return meta && typeof meta === 'object' && !Array.isArray(meta) ? meta : {};
    }

    get role() {
        const type = this.place?.type;
        return ENERGY_ROLES.includes(type) ? type : ROLE_CUSTOMER;
    }

    get roleOptions() {
        return [
            { value: ROLE_CUSTOMER, label: this.intl.t('energyops.place.role.customer') },
            { value: ROLE_DEPOT, label: this.intl.t('energyops.place.role.depot') },
            { value: ROLE_INVENTORY, label: this.intl.t('energyops.place.role.inventory') },
        ];
    }

    get receivingHours() {
        const hours = this.meta.receiving_hours;
        return hours && typeof hours === 'object' ? hours : {};
    }

    constructor(owner) {
        super(...arguments);
        registerEnergyOps(owner ?? getOwner(this));
    }

    writeMeta(patch) {
        if (!this.place) return;
        this.place.meta = { ...this.meta, ...patch };
        if (typeof this.place.notifyPropertyChange === 'function') {
            this.place.notifyPropertyChange('meta');
        }
    }

    @action setRole(role) {
        if (!this.place) return;
        if (ENERGY_ROLES.includes(role)) {
            this.place.type = role;
            this.writeMeta({ energyops_depot: role === ROLE_DEPOT, energyops_inventory: role === ROLE_INVENTORY });
            return;
        }
        // back to a regular customer site: only clear the type when it was one of ours
        if (ENERGY_ROLES.includes(this.place.type)) {
            this.place.type = null;
        }
        this.writeMeta({ energyops_depot: false, energyops_inventory: false });
    }

    @action setHour(bound, event) {
        const value = event?.target?.value ?? null;
        const hours = { ...this.receivingHours, [bound]: value || null };
        const hasAny = Boolean(hours.start || hours.end);
        this.writeMeta({ receiving_hours: hasAny ? hours : null });
    }
}
