'use strict';

module.exports = function (environment) {
    return {
        modulePrefix: 'dummy',
        environment,
        rootURL: '/',
        locationType: 'history',
        EmberENV: { FEATURES: {}, EXTEND_PROTOTYPES: false },
        APP: {},
    };
};
