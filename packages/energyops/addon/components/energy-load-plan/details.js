import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { task } from 'ember-concurrency';

export default class EnergyLoadPlanDetailsComponent extends Component {
    @service loadPlanActions;
    @service deliveryTicketActions;
    @service compartmentActions;
    @service hostRouter;
    @service unloadActions;
    @tracked compartments = [];
    @tracked unloads = [];

    get resource() {
        return this.args.resource ?? this.args.loadPlan ?? this.args.model;
    }

    get lines() {
        return [...(this.resource?.lines ?? [])].sort((a, b) => (a.sequence ?? 0) - (b.sequence ?? 0));
    }

    get draws() {
        return [...(this.resource?.draws ?? [])].sort((a, b) => (a.sequence ?? 0) - (b.sequence ?? 0));
    }

    get tickets() {
        return this.resource?.tickets ?? [];
    }

    get loadProof() {
        const proof = this.resource?.load_proof;
        return proof && typeof proof === 'object' ? proof : {};
    }

    get orderIds() {
        return [...new Set(this.lines.map((line) => line.orderId).filter(Boolean))];
    }

    constructor() {
        super(...arguments);
        this.loadCompartments.perform();
        this.loadUnloads.perform();
    }

    @action viewDepot() {
        if (!this.resource?.depot) return;
        this.hostRouter.transitionTo('console.fleet-ops.management.places.index.details', this.resource.depot);
    }

    @task *loadUnloads() {
        if (!this.resource?.id) return;
        this.unloads = yield this.unloadActions.forLoadPlan.perform(this.resource);
    }

    @task *loadCompartments() {
        if (!this.resource?.vehicle) return;
        this.compartments = yield this.compartmentActions.loadForOwner.perform(this.resource.vehicle);
    }
}
