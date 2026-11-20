import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { getOwner } from '@ember/application';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import registerEnergyOps from '../../utils/register-energy-ops';
import { isEnergyOrderConfig, energyFamilyFor } from '../../utils/energy-order-config';

/**
 * Fleet-Ops slot `fleet-ops:component:order:form` (rendered between Route and Payload).
 *
 * - Ventana horaria: when the drop-off changes and the place carries `meta.receiving_hours {start,end}`,
 *   prefill `order.time_window_start/end` on the order's scheduled date (only when empty or previously
 *   prefilled by us, so manual edits stick).
 * - Articulo: for GLP / WOP order configs make sure the payload has at least one entity, so the
 *   `order-entity-energy-fields` slot can preselect and lock the product.
 */
export default class EnergyFleetOpsOrderEnergyScheduleComponent extends Component {
    @service store;
    @service abilities;
    @service intl;

    @tracked appliedFromPlaceId = null;
    lastDropoffId = undefined;
    lastConfigId = undefined;

    get order() {
        return this.args.order ?? this.args.resource;
    }

    get canSee() {
        return this.abilities.can('energy-ops see extension') && Boolean(this.order);
    }

    get isEnergyOrder() {
        return isEnergyOrderConfig(this.order?.order_config);
    }

    get family() {
        return energyFamilyFor(this.order?.order_config);
    }

    get dropoff() {
        return this.order?.payload?.dropoff ?? null;
    }

    get receivingHours() {
        const meta = this.dropoff?.meta;
        const hours = meta && typeof meta === 'object' ? meta.receiving_hours : null;
        return hours && typeof hours === 'object' && (hours.start || hours.end) ? hours : null;
    }

    get hasTimeWindow() {
        return Boolean(this.order?.time_window_start || this.order?.time_window_end);
    }

    get prefilledFromDropoff() {
        return Boolean(this.appliedFromPlaceId) && this.appliedFromPlaceId === this.dropoff?.id;
    }

    get dropoffId() {
        return this.dropoff?.id ?? null;
    }

    get configId() {
        return this.order?.order_config?.id ?? this.order?.order_config_uuid ?? null;
    }

    constructor(owner) {
        super(...arguments);
        registerEnergyOps(owner ?? getOwner(this));
    }

    /** Invoked by `{{energy-on-change this.sync this.dropoffId this.configId}}` after render. */
    @action sync(dropoffId, configId) {
        if (this.isDestroyed || this.isDestroying || !this.order || this.order.isDestroyed || this.order.isDestroying) return;

        if (dropoffId !== this.lastDropoffId) {
            this.lastDropoffId = dropoffId;
            this.applyReceivingHours();
        }

        if (configId !== this.lastConfigId) {
            this.lastConfigId = configId;
            this.ensureFirstEntity();
        }
    }

    referenceDate() {
        const raw = this.order?.scheduled_at ?? this.order?.created_at ?? new Date();
        const date = raw instanceof Date ? new Date(raw) : new Date(raw);
        return Number.isNaN(date.getTime()) ? new Date() : date;
    }

    timeOn(reference, hhmm) {
        if (!hhmm || typeof hhmm !== 'string') return null;
        const [hours, minutes] = hhmm.split(':').map((part) => parseInt(part, 10));
        if (!Number.isFinite(hours)) return null;
        const date = new Date(reference);
        date.setHours(hours, Number.isFinite(minutes) ? minutes : 0, 0, 0);
        return date;
    }

    /** Apply the drop-off receiving hours unless the operator already typed a window by hand. */
    @action applyReceivingHours(force = false) {
        const hours = this.receivingHours;
        const dropoffId = this.dropoff?.id ?? null;
        if (!hours || !dropoffId) return;

        const untouched = !this.hasTimeWindow || this.appliedFromPlaceId !== null;
        if (!force && !untouched) return;

        const reference = this.referenceDate();
        const start = this.timeOn(reference, hours.start);
        const end = this.timeOn(reference, hours.end);
        if (start) this.order.time_window_start = start;
        if (end) {
            // overnight windows (e.g. 22:00 - 06:00) end the following day
            if (start && end <= start) end.setDate(end.getDate() + 1);
            this.order.time_window_end = end;
        }
        this.appliedFromPlaceId = dropoffId;
    }

    @action clearTimeWindow() {
        if (!this.order) return;
        this.order.time_window_start = null;
        this.order.time_window_end = null;
        this.appliedFromPlaceId = null;
    }

    /** Energy orders always carry their product line: add the first item so the GLP/WOP fields render. */
    ensureFirstEntity() {
        if (!this.isEnergyOrder) return;
        const payload = this.order?.payload;
        if (!payload || !payload.entities || typeof payload.entities.pushObject !== 'function') return;
        if (payload.entities.length > 0) return;
        if (typeof this.order.isNew === 'boolean' && !this.order.isNew) return;

        const entity = this.store.createRecord('entity', { type: 'bulk-energy', meta: { unit: this.family === 'glp' ? 'gal' : 'L' } });
        payload.entities.pushObject(entity);
    }
}
