import Component from '@glimmer/component';

/**
 * Renders the tanker as a row of cells (front → back) with fill level and product color.
 */
export default class EnergyCompartmentSchematicComponent extends Component {
    get compartments() {
        return [...(this.args.compartments ?? [])].filter((c) => !c.isDeleted).sort((a, b) => (a.sequence ?? 0) - (b.sequence ?? 0));
    }

    get totalCapacity() {
        return this.compartments.reduce((sum, c) => sum + (Number(c.capacity_volume) || 0), 0);
    }

    get cells() {
        const total = this.totalCapacity || 1;
        return this.compartments.map((compartment) => {
            const product = compartment.last_product ?? compartment.dedicated_product;
            const lineProduct = this.lineProductFor(compartment);
            const planned = this.plannedVolumeFor(compartment);
            const fill = compartment.fillPercent;
            return {
                compartment,
                widthPercent: Math.max(8, ((Number(compartment.capacity_volume) || 0) / total) * 100),
                fillPercent: fill,
                plannedPercent: compartment.capacity_volume ? Math.min(100, Math.round((planned / Number(compartment.capacity_volume)) * 100)) : 0,
                color: lineProduct?.color ?? product?.color ?? '#60a5fa',
                productCode: lineProduct?.code ?? product?.code ?? null,
                planned,
            };
        });
    }

    lineProductFor(compartment) {
        const line = (this.args.lines ?? []).find((l) => (l.compartment_uuid ?? l.compartment?.id) === compartment.id);
        return line?.product ?? null;
    }

    plannedVolumeFor(compartment) {
        return (this.args.lines ?? []).filter((l) => (l.compartment_uuid ?? l.compartment?.id) === compartment.id).reduce((sum, l) => sum + (Number(l.planned_volume) || 0), 0);
    }
}
