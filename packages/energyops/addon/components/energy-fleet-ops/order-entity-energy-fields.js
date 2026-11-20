import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { getOwner } from '@ember/application';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { task } from 'ember-concurrency';
import registerEnergyOps from '../../utils/register-energy-ops';
import { VOLUME_UNITS } from '../../utils/energy-options';
import { energyFamilyFor, defaultUnitFor } from '../../utils/energy-order-config';

/**
 * Fleet-Ops slot `fleet-ops:component:order:form:payload:entity:form`.
 * Adds product / volume / unit / customer tank to an order item (stored on `entity.meta`).
 * These meta keys are what the CompartmentPacker reads server-side.
 */
export default class EnergyFleetOpsOrderEntityEnergyFieldsComponent extends Component {
    @service store;
    @service abilities;
    unitOptions = VOLUME_UNITS;

    @tracked product;
    @tracked tank;
    @tracked tanks = [];
    /** Product forced by the order config (GLP delivery -> the GLP product); null when the operator may choose. */
    @tracked lockedProduct = null;

    get entity() {
        return this.args.entity;
    }

    get order() {
        return this.args.order;
    }

    get canSee() {
        return this.abilities.can('energy-ops see extension') && Boolean(this.entity);
    }

    get meta() {
        const meta = this.entity?.meta;
        return meta && typeof meta === 'object' && !Array.isArray(meta) ? meta : {};
    }

    /** `glp` | `wop` | null from the order config chosen on the order. */
    get family() {
        return energyFamilyFor(this.order?.order_config);
    }

    get productLocked() {
        return Boolean(this.lockedProduct);
    }

    /** Tanks at the drop-off that hold the selected product; all site tanks when none match (or no product yet). */
    get tankOptions() {
        const productId = this.product?.id ?? this.meta.energyops_product_uuid;
        if (!productId) return this.tanks;
        const matching = this.tanks.filter((tank) => (tank.product_uuid ?? tank.product?.id) === productId);
        return matching.length ? matching : this.tanks;
    }

    get dropoffPlaceId() {
        const payload = this.order?.payload;
        return payload?.dropoff?.id ?? payload?.dropoff_uuid ?? null;
    }

    constructor(owner) {
        super(...arguments);
        // Rendered inside the Fleet-Ops engine: expose our components/services to that owner
        registerEnergyOps(owner ?? getOwner(this));
        if (this.canSee) {
            this.hydrate.perform();
        }
    }

    @task *hydrate() {
        const productId = this.meta.energyops_product_uuid;
        if (productId) {
            this.product = this.store.peekRecord('energy-product', productId) ?? (yield this.store.findRecord('energy-product', productId).catch(() => null));
        }
        yield this.applyFamilyPreset.perform();
        yield this.loadTanks.perform();
    }

    /**
     * GLP delivery: the only product is GLP -> preselect and lock it (volume stays editable).
     * WOP delivery: lock only when the operation has a single active product, otherwise free choice.
     */
    @task *applyFamilyPreset() {
        const family = this.family;
        if (!family) {
            this.lockedProduct = null;
            return;
        }

        let candidates = [];
        try {
            const products = yield this.store.query('energy-product', { status: 'active', family, limit: 25 });
            candidates = products.slice ? products.slice() : [...products];
        } catch {
            candidates = [];
        }

        if (family === 'glp') {
            const glp = candidates.find((product) => String(product.code).toUpperCase() === 'GLP') ?? candidates[0] ?? null;
            this.lockedProduct = glp;
        } else {
            this.lockedProduct = candidates.length === 1 ? candidates[0] : null;
        }

        if (!this.lockedProduct) return;
        if (this.product?.id !== this.lockedProduct.id) {
            this.setProduct(this.lockedProduct);
        }
        if (!this.meta.unit) {
            this.writeMeta({ unit: defaultUnitFor(family) });
        }
    }

    @task *loadTanks() {
        const placeId = this.dropoffPlaceId;
        if (!placeId) {
            this.tanks = [];
            return;
        }
        try {
            const tanks = yield this.store.query('energy-customer-tank', { place_uuid: placeId, limit: 50 });
            this.tanks = tanks.slice ? tanks.slice() : [...tanks];
            const tankId = this.meta.energyops_customer_tank_uuid;
            if (tankId) {
                this.tank = this.tanks.find((t) => t.id === tankId) ?? null;
            }
            if (!this.tank) {
                this.autoPickTank();
            }
        } catch {
            this.tanks = [];
        }
    }

    writeMeta(patch) {
        this.entity.meta = { ...this.meta, ...patch };
        if (typeof this.entity.notifyPropertyChange === 'function') {
            this.entity.notifyPropertyChange('meta');
        }
    }

    @action setProduct(product) {
        this.product = product;
        const patch = {
            energyops_product_uuid: product?.id ?? null,
            energyops_product_code: product?.code ?? null,
        };
        if (product?.default_unit && !this.meta.unit) {
            patch.unit = product.default_unit;
        }
        this.writeMeta(patch);
        if (product && !this.entity.name) {
            this.entity.name = product.name;
        }
        if (product && !this.entity.type) {
            this.entity.type = 'bulk-energy';
        }
        this.autoPickTank();
    }

    /** Select the tank automatically when the product narrows the drop-off tanks down to one. */
    autoPickTank() {
        const productId = this.product?.id;
        if (!productId) return;
        const current = this.tank;
        if (current && (current.product_uuid ?? current.product?.id) === productId) return;
        const matching = this.tanks.filter((tank) => (tank.product_uuid ?? tank.product?.id) === productId);
        if (matching.length === 1) {
            this.setTank(matching[0]);
        } else if (current) {
            // tank belongs to another product: clear it so the packer / ticket don't inherit it
            this.setTank(null);
        }
    }

    @action setVolume(event) {
        const value = parseFloat(event.target.value);
        this.writeMeta({ volume: Number.isFinite(value) ? value : null });
    }

    @action setUnit(unit) {
        this.writeMeta({ unit });
    }

    /** Native `<Select>` yields the option value (tank id); resolve it back to the record. */
    @action setTankById(id) {
        this.setTank(this.tanks.find((tank) => tank.id === id) ?? null);
    }

    @action setTank(tank) {
        this.tank = tank;
        this.writeMeta({ energyops_customer_tank_uuid: tank?.id ?? null });
        if (tank?.product && !this.product) {
            this.setProduct(tank.product);
        }
    }
}
