import Helper from '@ember/component/helper';
import { next } from '@ember/runloop';

/**
 * `{{energy-on-change fn a b}}` — dependency-free "did-update": recomputes whenever one of the
 * positional args changes and calls `fn(...args)` after render. Renders nothing.
 *
 * Used by slot components rendered inside Fleet-Ops templates to react to `order.payload.dropoff`
 * or `order.order_config` changes without `ember-render-modifiers`.
 */
export default class EnergyOnChangeHelper extends Helper {
    compute([callback, ...args]) {
        if (typeof callback === 'function') {
            next(() => callback(...args));
        }
        return '';
    }
}
