import Engine from '@ember/engine';
import loadInitializers from 'ember-load-initializers';
import Resolver from 'ember-resolver';
import config from './config/environment';
import { services, externalRoutes } from '@fleetbase/ember-core/exports';

const modulePrefix = config?.modulePrefix ?? '@fleetbase/energyops-engine';
export default class EnergyOpsEngine extends Engine {
    modulePrefix = modulePrefix;
    Resolver = Resolver;
    dependencies = {
        services,
        externalRoutes,
    };
}

loadInitializers(EnergyOpsEngine, modulePrefix);
