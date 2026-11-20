import Component from '@glimmer/component';
import { inject as service } from '@ember/service';

export default class EnergyUnloadDetailsComponent extends Component {
    @service unloadActions;
    @service hostRouter;

    get resource() {
        return this.args.resource ?? this.args.unload ?? this.args.model;
    }

    get lines() {
        return this.resource?.sortedLines ?? [];
    }
}
