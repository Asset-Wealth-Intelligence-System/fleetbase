import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { task } from 'ember-concurrency';

/**
 * Ember Data based compartment editor for a vehicle or asset (trailer).
 * Add / edit (modal) / delete / reorder compartments.
 */
export default class EnergyCompartmentEditorComponent extends Component {
    @service compartmentActions;
    @service abilities;
    @tracked internalCompartments = [];
    @tracked dragging;

    get owner() {
        return this.args.owner ?? this.args.resource;
    }

    get compartments() {
        return this.args.compartments ?? this.internalCompartments;
    }

    get sortedCompartments() {
        return [...this.compartments].filter((c) => !c.isDeleted).sort((a, b) => (a.sequence ?? 0) - (b.sequence ?? 0));
    }

    get totalCapacity() {
        return this.sortedCompartments.reduce((sum, c) => sum + (Number(c.capacity_volume) || 0), 0);
    }

    get safeCapacity() {
        return this.sortedCompartments.reduce((sum, c) => sum + c.safeCapacity, 0);
    }

    get unit() {
        return this.sortedCompartments[0]?.unit ?? 'L';
    }

    get ownerIsSaved() {
        return Boolean(this.owner?.id);
    }

    get canEdit() {
        return this.abilities.can('energy-ops update energy-compartment') || this.abilities.can('energy-ops create energy-compartment');
    }

    constructor() {
        super(...arguments);
        if (!this.args.compartments && this.ownerIsSaved) {
            this.load.perform();
        }
    }

    @task *load() {
        this.internalCompartments = yield this.compartmentActions.loadForOwner.perform(this.owner);
    }

    notifyChange(list = this.compartments) {
        const next = [...list];
        if (!this.args.compartments) {
            this.internalCompartments = next;
        }
        if (typeof this.args.onChange === 'function') {
            this.args.onChange(next);
        }
    }

    @action add() {
        const compartment = this.compartmentActions.buildForOwner(this.owner, this.sortedCompartments, {
            unit: this.unit,
        });
        this.compartmentActions.modalEdit(compartment, {
            onSaved: (saved) => this.notifyChange([...this.compartments, saved]),
        });
    }

    @action edit(compartment) {
        this.compartmentActions.modalEdit(compartment, {
            onSaved: () => this.notifyChange(),
        });
    }

    @action remove(compartment) {
        this.compartmentActions.delete(compartment, {
            onConfirm: () => this.notifyChange(this.compartments.filter((c) => c !== compartment)),
        });
    }

    @action flush(compartment) {
        return this.compartmentActions.flush.perform(compartment);
    }

    @action move(compartment, direction) {
        const list = this.sortedCompartments;
        const index = list.indexOf(compartment);
        const target = index + direction;
        if (index < 0 || target < 0 || target >= list.length) return;
        [list[index], list[target]] = [list[target], list[index]];
        this.compartmentActions.reorder.perform(list).then(() => this.notifyChange(list));
    }

    @action dragStart(compartment, event) {
        this.dragging = compartment;
        event.dataTransfer?.setData('text/plain', compartment.id ?? compartment.code);
        event.dataTransfer.effectAllowed = 'move';
    }

    @action dragOver(event) {
        event.preventDefault();
    }

    @action drop(target, event) {
        event.preventDefault();
        const source = this.dragging;
        this.dragging = null;
        if (!source || source === target) return;
        const list = this.sortedCompartments;
        const from = list.indexOf(source);
        const to = list.indexOf(target);
        list.splice(from, 1);
        list.splice(to, 0, source);
        this.compartmentActions.reorder.perform(list).then(() => this.notifyChange(list));
    }
}
