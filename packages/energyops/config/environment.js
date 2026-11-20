/* eslint-env node */
'use strict';
const { name, fleetbase } = require('../package');

module.exports = function (environment) {
    let ENV = {
        modulePrefix: name,
        environment,
        // Must match the `as` used when the console mounts this engine (package.json `fleetbase.route`).
        // ember-core resets `engineInstance.mountPoint` from this value; a mismatch breaks dynamic
        // segment params (`/:public_id`) for every deep link into the engine.
        mountedEngineRoutePrefix: getMountedEngineRoutePrefix(),
    };

    return ENV;
};

function getMountedEngineRoutePrefix() {
    let mountedEngineRoutePrefix = 'energy-ops';
    if (fleetbase && typeof fleetbase.route === 'string') {
        mountedEngineRoutePrefix = fleetbase.route;
    }

    return `console.${mountedEngineRoutePrefix}`;
}
