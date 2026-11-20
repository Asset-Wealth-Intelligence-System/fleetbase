import Component from '@glimmer/component';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';
import { COMPATIBILITY_ACTIONS, RESOURCE_STATUSES, translateOptions } from '../../utils/energy-options';

export default class CompatibilityRuleFormComponent extends Component {
    @service intl;
    get actionOptions() {
        return translateOptions(this.intl, COMPATIBILITY_ACTIONS);
    }
    statusOptions = RESOURCE_STATUSES;

    get resource() {
        return this.args.resource ?? this.args.rule ?? this.args.model;
    }

    @action setRelation(relationship, record) {
        this.resource[relationship] = record;
        this.resource[`${relationship}_uuid`] = record?.id ?? null;
    }

    @action toggle(attribute, value) {
        this.resource[attribute] = value;
    }
}
