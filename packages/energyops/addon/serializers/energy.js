import ApplicationSerializer from '@fleetbase/ember-core/serializers/application';
import { EmbeddedRecordsMixin } from '@ember-data/serializer/rest';
import { singularize } from 'ember-inflector';
import { underscore, dasherize } from '@ember/string';

const EnergySerializerBase = ApplicationSerializer.extend(EmbeddedRecordsMixin);

/**
 * Maps API payload keys (`product`, `products`) to prefixed model names (`energy-product`).
 */
export default class EnergySerializer extends EnergySerializerBase {
    modelNameFromPayloadKey(key) {
        const modelName = singularize(dasherize(key));
        return modelName.startsWith('energy-') ? modelName : `energy-${modelName}`;
    }

    payloadKeyFromModelName(modelName) {
        return underscore(modelName).replace(/^energy_/, '');
    }
}
