import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';
import { set } from '@ember/object';
import { isPresent } from '@ember/utils';

export default class FleetIndexRoute extends Route {
    @service store;
    @service fetch;

    queryParams = {
        page: { refreshModel: true },
        limit: { refreshModel: true },
        sort: { refreshModel: true },
        query: { refreshModel: true },
        status: { refreshModel: true },
        plate_number: { refreshModel: true },
        operation: { refreshModel: true },
        configured: { refreshModel: true },
    };

    async model(params) {
        const { operation, configured, ...vehicleParams } = params;
        const vehicles = await this.store.query('vehicle', vehicleParams);
        const ids = vehicles.map((vehicle) => vehicle.id);

        const fallback = { compartments_count: 0, total_capacity: 0, profile_status: 'no_profile' };
        const filtering = isPresent(operation) || isPresent(configured);

        if (ids.length) {
            // `fetch.get` serializes with URLSearchParams, so send a comma separated list and omit empty filters
            const query = { vehicles: ids.join(',') };
            if (isPresent(operation)) query.operation = operation;
            if (isPresent(configured)) query.configured = configured;

            try {
                const summary = await this.fetch.get('fleet/summary', query, { namespace: 'energyops/int/v1' });
                vehicles.forEach((vehicle) => set(vehicle, 'energy_summary', summary?.[vehicle.id] ?? { ...fallback }));

                if (filtering) {
                    // Energy filters are applied server side on the summary; hide vehicles that were excluded
                    const filtered = vehicles.filter((vehicle) => summary?.[vehicle.id]);
                    set(filtered, 'meta', vehicles.meta);
                    return filtered;
                }
            } catch {
                vehicles.forEach((vehicle) => set(vehicle, 'energy_summary', { ...fallback }));
            }
        }

        return vehicles;
    }
}
