<?php

namespace Fleetbase\EnergyOps\Http\Filter;

use Fleetbase\FleetOps\Support\Utils;
use Fleetbase\Http\Filter\Filter;
use Illuminate\Support\Str;

/**
 * Shared filter behaviour for EnergyOps resources: company isolation, search,
 * status / date range filters and relation lookups by uuid or public id.
 */
abstract class EnergyOpsFilter extends Filter
{
    public function queryForInternal(): void
    {
        $this->builder->where($this->builder->qualifyColumn('company_uuid'), $this->session->get('company'));
    }

    public function queryForPublic(): void
    {
        $this->builder->where($this->builder->qualifyColumn('company_uuid'), $this->session->get('company'));
    }

    public function query(?string $searchQuery): void
    {
        $this->builder->search($searchQuery);
    }

    public function publicId(?string $publicId): void
    {
        $this->builder->searchWhere('public_id', $publicId);
    }

    public function status(string|array $status): void
    {
        $this->builder->whereIn('status', (array) $status);
    }

    public function createdAt($createdAt): void
    {
        $this->applyDateFilter('created_at', $createdAt);
    }

    public function updatedAt($updatedAt): void
    {
        $this->applyDateFilter('updated_at', $updatedAt);
    }

    protected function applyDateFilter(string $column, $value): void
    {
        $range = Utils::dateRange($value);

        if (is_array($range)) {
            $this->builder->whereBetween($column, $range);
        } else {
            $this->builder->whereDate($column, $range);
        }
    }

    /**
     * Filter by a related record given either its uuid or public id.
     */
    protected function whereRelation(string $column, string $relation, string|array $value): void
    {
        $values = (array) $value;

        $this->builder->where(function ($query) use ($column, $relation, $values) {
            $uuids = array_filter($values, fn ($v) => Str::isUuid($v));
            $ids   = array_diff($values, $uuids);

            if ($uuids) {
                $query->whereIn($column, $uuids);
            }
            if ($ids) {
                $query->orWhereHas($relation, fn ($q) => $q->whereIn('public_id', array_values($ids)));
            }
        });
    }
}
