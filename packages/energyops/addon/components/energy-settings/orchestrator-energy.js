import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';

export const COMPARTMENT_ENGINE_ID = 'compartment';

/**
 * Fleet-Ops slot `fleet-ops:template:settings:orchestrator`.
 * Lets the operator switch the Fleet-Ops orchestrator to the compartment packer.
 * Saving is handled by the host orchestrator page ("Save changes").
 */
export default class EnergySettingsOrchestratorEnergyComponent extends Component {
    @service abilities;

    get controller() {
        return this.args.controller;
    }

    get canSee() {
        return this.abilities.can('energy-ops see extension') && Boolean(this.controller);
    }

    get isActive() {
        return this.controller?.allocationEngine === COMPARTMENT_ENGINE_ID;
    }

    @action toggle(enabled) {
        if (!this.controller) return;
        this.controller.allocationEngine = enabled ? COMPARTMENT_ENGINE_ID : 'vroom';
    }
}
