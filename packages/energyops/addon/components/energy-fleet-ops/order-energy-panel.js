import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { getOwner } from '@ember/application';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { task } from 'ember-concurrency';
import registerEnergyOps from '../../utils/register-energy-ops';
import { fromLiters } from '../../utils/energy-options';

/**
 * Fleet-Ops slot `fleet-ops:component:order:details`.
 * Shows the load plan + delivery tickets for an energy order and exposes pack / issue ticket.
 */
export default class EnergyFleetOpsOrderEnergyPanelComponent extends Component {
    @service loadPlanActions;
    @service deliveryTicketActions;
    @service abilities;
    @service hostRouter;
    @service intl;
    @tracked loadPlan = null;
    @tracked tickets = [];

    get order() {
        return this.args.resource ?? this.args.order;
    }

    get canSee() {
        return this.abilities.can('energy-ops see extension') && Boolean(this.order?.id);
    }

    get energyEntities() {
        const entities = this.order?.payload?.entities ?? [];
        return entities.filter((entity) => entity?.meta?.energyops_product_uuid);
    }

    get isEnergyOrder() {
        const ns = this.order?.order_config?.namespace ?? this.order?.orderConfig?.namespace ?? this.order?.meta?.energyops_config;
        return (typeof ns === 'string' && ns.startsWith('energyops:')) || this.energyEntities.length > 0;
    }

    get orderLines() {
        return [...(this.loadPlan?.lines ?? [])].filter((line) => (line.order_uuid ?? line.order?.id) === this.order.id).sort((a, b) => (a.sequence ?? 0) - (b.sequence ?? 0));
    }

    get orderDraws() {
        return [...(this.loadPlan?.draws ?? [])].filter((draw) => (draw.order_uuid ?? draw.order?.id) === this.order.id);
    }

    get actionButtons() {
        const buttons = [];
        if (!this.loadPlan) {
            buttons.push({
                text: this.intl.t('energyops.order.pack-now'),
                icon: 'boxes-packing',
                size: 'xs',
                type: 'primary',
                onClick: this.packNow,
                permission: 'energy-ops pack energy-load-plan',
            });
        } else {
            buttons.push({
                text: this.intl.t('energyops.order.issue-ticket'),
                icon: 'receipt',
                size: 'xs',
                type: 'primary',
                onClick: this.issueTicket,
                permission: 'energy-ops create energy-delivery-ticket',
            });
        }
        return buttons;
    }

    constructor(owner) {
        super(...arguments);
        // Rendered inside the Fleet-Ops engine: expose our components/services to that owner
        registerEnergyOps(owner ?? getOwner(this));
        if (this.canSee) {
            this.load.perform();
        }
    }

    @task *load() {
        this.loadPlan = yield this.loadPlanActions.forOrder.perform(this.order);
        this.tickets = yield this.deliveryTicketActions.forOrder.perform(this.order);
    }

    @action packNow() {
        this.loadPlanActions.packOrders({
            resource: { orders: [this.order], vehicles: [] },
            callback: () => this.load.perform(),
        });
    }

    /** Unit the operator entered the order in (entity meta), so the ticket is issued in the same unit. */
    get orderUnit() {
        const units = [...new Set(this.energyEntities.map((entity) => entity?.meta?.unit).filter(Boolean))];
        return units.length === 1 ? units[0] : 'L';
    }

    @action issueTicket() {
        const draw = this.orderDraws[0];
        const unit = this.orderUnit;
        // stop draws are stored in liters; present the ticket in the order's unit
        const planned = draw?.planned_qty != null ? Math.round(fromLiters(draw.planned_qty, unit, draw?.product?.density) * 1000) / 1000 : null;
        const tankId = this.energyEntities.find((entity) => entity?.meta?.energyops_customer_tank_uuid)?.meta?.energyops_customer_tank_uuid ?? null;
        this.deliveryTicketActions.issueForOrder(
            this.order,
            {
                load_plan: this.loadPlan,
                load_plan_uuid: this.loadPlan?.id,
                stop_draw: draw,
                stop_draw_uuid: draw?.id,
                compartment: draw?.compartment,
                compartment_uuid: draw?.compartment_uuid ?? draw?.compartment?.id,
                product: draw?.product,
                product_uuid: draw?.product_uuid ?? draw?.product?.id,
                planned_qty: planned,
                metered_qty: planned,
                unit,
                customer_tank_uuid: tankId,
            },
            { lockOrder: true, callback: () => this.load.perform() }
        );
    }

    @action viewLoadPlan() {
        if (this.loadPlan) {
            this.loadPlanActions.panel.view(this.loadPlan);
        }
    }
}
