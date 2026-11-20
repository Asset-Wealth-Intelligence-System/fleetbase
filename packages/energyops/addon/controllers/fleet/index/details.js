import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';

export default class FleetIndexDetailsController extends Controller {
    @service hostRouter;
    @service intl;
    @tracked overlay;
    @tracked activeTab = 'profile';

    get tabs() {
        return [
            { id: 'profile', label: this.intl.t('energyops.fleet.tabs.profile'), icon: 'id-card' },
            { id: 'compartments', label: this.intl.t('energyops.fleet.tabs.compartments'), icon: 'layer-group' },
            { id: 'schematic', label: this.intl.t('energyops.fleet.tabs.schematic'), icon: 'truck-moving' },
            { id: 'load-history', label: this.intl.t('energyops.fleet.tabs.load-history'), icon: 'clock-rotate-left' },
        ];
    }

    get actionButtons() {
        return [
            {
                icon: 'truck',
                helpText: this.intl.t('energyops.fleet.open-vehicle'),
                fn: () => this.hostRouter.transitionTo('console.fleet-ops.management.vehicles.index.details', this.model),
            },
        ];
    }

    @action onTabChange(tab) {
        this.activeTab = tab?.id ?? tab;
    }
}
