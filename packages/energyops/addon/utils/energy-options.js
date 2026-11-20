/**
 * Shared option lists for EnergyOps forms.
 */
export const VOLUME_UNITS = ['L', 'gal', 'kg', 'm3'];

export const RESOURCE_STATUSES = ['active', 'inactive'];

export const OPERATION_FAMILIES = [
    { value: 'glp', labelKey: 'energyops.options.operation-families.glp' },
    { value: 'wop', labelKey: 'energyops.options.operation-families.wop' },
    { value: 'lubricants', labelKey: 'energyops.options.operation-families.lubricants' },
    { value: 'other', labelKey: 'energyops.options.operation-families.other' },
];

export const HAZMAT_CLASSES = [
    { value: '2.1', labelKey: 'energyops.options.hazmat-classes.2-1' },
    { value: '2.2', labelKey: 'energyops.options.hazmat-classes.2-2' },
    { value: '3', labelKey: 'energyops.options.hazmat-classes.3' },
    { value: '8', labelKey: 'energyops.options.hazmat-classes.8' },
    { value: '9', labelKey: 'energyops.options.hazmat-classes.9' },
];

export const COMPATIBILITY_ACTIONS = [
    { value: 'allowed', labelKey: 'energyops.options.compatibility-actions.allowed' },
    { value: 'flush_required', labelKey: 'energyops.options.compatibility-actions.flush-required' },
    { value: 'forbidden', labelKey: 'energyops.options.compatibility-actions.forbidden' },
];

export const COMPARTMENT_OWNER_TYPES = [
    { value: 'vehicle', labelKey: 'energyops.options.compartment-owner-types.vehicle' },
    { value: 'asset', labelKey: 'energyops.options.compartment-owner-types.asset' },
];

export const TANK_TYPES = [
    { value: 'aboveground', labelKey: 'energyops.options.tank-types.aboveground' },
    { value: 'underground', labelKey: 'energyops.options.tank-types.underground' },
    { value: 'cylinder_bank', labelKey: 'energyops.options.tank-types.cylinder-bank' },
    { value: 'mobile', labelKey: 'energyops.options.tank-types.mobile' },
];

export const LOAD_PLAN_STATUSES = ['draft', 'packed', 'loaded', 'in_transit', 'closed', 'canceled'];

export const TICKET_STATUSES = ['issued', 'short_fill', 'reject', 'contamination', 'void'];

export const TICKET_EXCEPTION_TYPES = [
    { value: 'short_fill', labelKey: 'energyops.options.ticket-exception-types.short-fill' },
    { value: 'reject', labelKey: 'energyops.options.ticket-exception-types.reject' },
    { value: 'contamination', labelKey: 'energyops.options.ticket-exception-types.contamination' },
];

/**
 * Resolve `labelKey` option lists to `{ value, label }` for `<Select>` / column filters in the active locale.
 */
export function translateOptions(intl, options) {
    return options.map((option) => ({ ...option, label: option.labelKey ? intl.t(option.labelKey) : option.label }));
}

export function volumeLabel(value, unit = 'L') {
    const number = Number(value ?? 0);
    return `${Number.isFinite(number) ? number.toLocaleString(undefined, { maximumFractionDigits: 2 }) : 0} ${unit}`;
}

const LITERS_PER_GALLON = 3.785411784;

/** Convert a volume in `unit` to liters (mirrors server-side `EnergyOps::toLiters`). */
export function toLiters(qty, unit = 'L', density = 0.54) {
    const n = Number(qty) || 0;
    switch (String(unit || 'L').toLowerCase()) {
        case 'gal':
            return n * LITERS_PER_GALLON;
        case 'm3':
        case 'm³':
            return n * 1000;
        case 'kg':
            return n / (Number(density) > 0 ? Number(density) : 0.54);
        default:
            return n;
    }
}

/** Convert liters to `unit` (mirrors server-side `EnergyOps::fromLiters`). */
export function fromLiters(liters, unit = 'L', density = 0.54) {
    const n = Number(liters) || 0;
    switch (String(unit || 'L').toLowerCase()) {
        case 'gal':
            return n / LITERS_PER_GALLON;
        case 'm3':
        case 'm³':
            return n / 1000;
        case 'kg':
            return n * (Number(density) > 0 ? Number(density) : 0.54);
        default:
            return n;
    }
}
