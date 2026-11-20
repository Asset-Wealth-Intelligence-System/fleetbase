import buildRoutes from 'ember-engines/routes';

function resourceRoutes(context, name, { edit = true, create = true } = {}) {
    context.route(name, function () {
        this.route('index', { path: '/' }, function () {
            if (create) {
                this.route('new');
            }
            this.route('details', { path: '/:public_id' });
            if (edit) {
                this.route('edit', { path: '/:public_id/edit' });
            }
        });
    });
}

export default buildRoutes(function () {
    // eslint-disable-next-line ember/no-shadow-route-definition -- false positive from the resourceRoutes() helper above
    this.route('home', { path: '/' });

    // Catalog
    resourceRoutes(this, 'operations');
    resourceRoutes(this, 'products');
    resourceRoutes(this, 'compatibility-rules');

    // Fleet
    this.route('fleet', function () {
        this.route('index', { path: '/' }, function () {
            this.route('details', { path: '/:public_id' });
        });
    });
    resourceRoutes(this, 'customer-tanks');

    // Dispatch
    resourceRoutes(this, 'load-plans', { edit: false, create: false });
    resourceRoutes(this, 'delivery-tickets', { edit: false });
    resourceRoutes(this, 'unloads', { edit: false });
});
