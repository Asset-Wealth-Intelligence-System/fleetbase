import ApplicationAdapter from '@fleetbase/ember-core/adapters/application';
import { pluralize } from 'ember-inflector';
import { dasherize } from '@ember/string';

/**
 * Base adapter for every EnergyOps model. All models are prefixed with `energy-`
 * to avoid colliding with other extensions (e.g. Storefront's `product`), while
 * the API keeps plain resource names under `energyops/int/v1`.
 */
export default class EnergyAdapter extends ApplicationAdapter {
    namespace = 'energyops/int/v1';

    pathForType(modelName) {
        return pluralize(dasherize(modelName).replace(/^energy-/, ''));
    }
}
