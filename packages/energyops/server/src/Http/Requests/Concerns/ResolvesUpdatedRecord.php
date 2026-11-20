<?php

namespace Fleetbase\EnergyOps\Http\Requests\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

/**
 * Helpers for uniqueness rules that mirror the extension's composite unique indexes,
 * so duplicates surface as validation errors instead of integrity constraint violations.
 */
trait ResolvesUpdatedRecord
{
    /**
     * Resolve the record being updated for a `PUT|PATCH .../{resource}/{id}` request.
     *
     * The controller validates a *copy* of the request (`FormRequest::createFrom`), so the route
     * binding is read from the copy, then the original request, then the last path segment.
     */
    protected function updatedRecord(string $modelClass): ?Model
    {
        if ($this->isMethod('POST')) {
            return null;
        }

        $id = $this->route('id') ?? request()->route('id') ?? basename($this->path());
        if (blank($id) || !is_string($id) || str_contains($id, '/')) {
            return null;
        }

        /** @var Model $modelClass */
        $query = $modelClass::query()->where(fn ($query) => $query->where('uuid', $id)->orWhere('public_id', $id));

        if (in_array('company_uuid', (new $modelClass())->getFillable(), true)) {
            $query->where('company_uuid', session('company'));
        }

        return $query->first();
    }

    /**
     * Read a value from either the wrapped (`{key}.*`) or flat request payload.
     */
    protected function payloadValue(string $wrapper, string $key): mixed
    {
        $value = $this->input("{$wrapper}.{$key}", $this->input($key));

        return filled($value) ? $value : null;
    }

    /**
     * Exclude the record being updated from a unique rule.
     */
    protected function ignoreUpdatedRecord(Unique $rule, ?Model $record): Unique
    {
        if ($record) {
            $rule->ignore($record->getAttribute('uuid'), 'uuid');
        }

        return $rule;
    }
}
