import Component from '@glimmer/component';

/**
 * Read-only compartment inventory table (capacity / residual / available / last product).
 */
export default class EnergyCompartmentInventoryComponent extends Component {
    get compartments() {
        return [...(this.args.compartments ?? [])].filter((c) => !c.isDeleted).sort((a, b) => (a.sequence ?? 0) - (b.sequence ?? 0));
    }

    get totals() {
        return this.compartments.reduce(
            (acc, c) => {
                acc.capacity += Number(c.capacity_volume) || 0;
                acc.residual += Number(c.residual_volume) || 0;
                acc.available += c.availableVolume;
                return acc;
            },
            { capacity: 0, residual: 0, available: 0 }
        );
    }

    get unit() {
        return this.compartments[0]?.unit ?? 'L';
    }
}
