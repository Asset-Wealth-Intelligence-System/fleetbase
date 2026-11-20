const GLP_KEYS = ['glp-delivery', 'energyops:glp-delivery'];
const WOP_KEYS = ['wop-delivery', 'energyops:wop-delivery'];

function configKeys(orderConfig) {
    if (!orderConfig) return [];
    const config = typeof orderConfig.get === 'function' ? { key: orderConfig.get('key'), namespace: orderConfig.get('namespace') } : orderConfig;
    return [config.key, config.namespace].filter(Boolean);
}

/** `glp` | `wop` | null for the given Fleet-Ops order config. */
export function energyFamilyFor(orderConfig) {
    const keys = configKeys(orderConfig);
    if (keys.some((key) => GLP_KEYS.includes(key))) return 'glp';
    if (keys.some((key) => WOP_KEYS.includes(key))) return 'wop';
    return null;
}

export function isEnergyOrderConfig(orderConfig) {
    return energyFamilyFor(orderConfig) !== null;
}

/** Default volume unit per family (GLP is sold by the gallon in DR, white oils by the liter). */
export function defaultUnitFor(family) {
    return family === 'glp' ? 'gal' : 'L';
}
