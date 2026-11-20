import Component from '@glimmer/component';
import { inject as service } from '@ember/service';

export default class ProductDetailsComponent extends Component {
    @service operationActions;

    get resource() {
        return this.args.resource ?? this.args.product ?? this.args.model;
    }
}
