<?php

namespace Fleetbase\EnergyOps\Http\Controllers\Api\v1;

use Fleetbase\FleetOps\Http\Resources\v1\DeletedResource;
use Fleetbase\Http\Controllers\Controller;
use Fleetbase\Http\Requests\FleetbaseRequest;
use Fleetbase\Support\Http;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Minimal REST controller for the consumable (API key) EnergyOps API.
 * Subclasses configure the model, JSON resource and form requests.
 */
abstract class ApiResourceController extends Controller
{
    /** @var class-string<Model> */
    protected string $model;

    /** @var class-string<\Fleetbase\Http\Resources\FleetbaseResource> */
    protected string $resource;

    /** @var class-string<FleetbaseRequest>|null */
    protected ?string $createRequest = null;

    /** @var class-string<FleetbaseRequest>|null */
    protected ?string $updateRequest = null;

    /** Relations eager loaded on every response. */
    protected array $with = [];

    /** Human readable name for error messages. */
    protected string $label = 'Resource';

    public function query(Request $request)
    {
        $results = $this->model::queryWithRequest($request, function (&$query) {
            $query->where('company_uuid', session('company'));
            if (!empty($this->with)) {
                $query->with($this->with);
            }
        });

        return $this->resource::collection($results);
    }

    public function find($id, Request $request)
    {
        try {
            $record = $this->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->apiError($this->label . ' resource not found.', 404);
        }

        return new $this->resource($record);
    }

    public function create(Request $request)
    {
        $input = $this->validated($request, $this->createRequest);
        if (!is_array($input)) {
            return $input;
        }

        $input = $this->resolvePublicIds($input);
        $input['company_uuid']    = session('company');
        $input['created_by_uuid'] = $request->user()?->uuid;

        $record = $this->model::create($input);

        return new $this->resource($record->fresh($this->with));
    }

    public function update($id, Request $request)
    {
        try {
            $record = $this->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->apiError($this->label . ' resource not found.', 404);
        }

        $input = $this->validated($request, $this->updateRequest);
        if (!is_array($input)) {
            return $input;
        }

        $record->update($this->resolvePublicIds($input));

        return new $this->resource($record->fresh($this->with));
    }

    public function delete($id, Request $request)
    {
        try {
            $record = $this->findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->apiError($this->label . ' resource not found.', 404);
        }

        $record->delete();

        return new DeletedResource($record);
    }

    public function bulkDelete(Request $request)
    {
        $ids     = (array) $request->input('ids', []);
        $records = $this->baseQuery()->where(fn ($q) => $q->whereIn('uuid', $ids)->orWhereIn('public_id', $ids))->get();
        $records->each->delete();

        return response()->json(['deleted' => $records->count()]);
    }

    protected function baseQuery()
    {
        return $this->model::query()->where('company_uuid', session('company'))->with($this->with);
    }

    protected function findOrFail(string $id): Model
    {
        return $this->baseQuery()->where(fn ($q) => $q->where('uuid', $id)->orWhere('public_id', $id))->firstOrFail();
    }

    /**
     * Validate the request with the configured form request; returns the validated input array or an error response.
     */
    protected function validated(Request $request, ?string $requestClass)
    {
        $input = $request->except(['company_uuid', 'created_by_uuid', 'uuid', 'public_id']);
        if (!$requestClass) {
            return $input;
        }

        /** @var FleetbaseRequest $formRequest */
        $formRequest = $requestClass::createFrom($request);
        $formRequest->setContainer(app());

        $validator = Validator::make($input, $formRequest->rules(), method_exists($formRequest, 'messages') ? $formRequest->messages() : []);
        if ($validator->fails()) {
            return response()->apiError($validator->errors()->all());
        }

        return $validator->validated();
    }

    /**
     * Public API consumers pass public ids for relations (`vehicle`, `place`, `product` …);
     * map them onto the `*_uuid` columns the models expect.
     */
    protected function resolvePublicIds(array $input): array
    {
        foreach ($this->publicIdMap() as $key => [$column, $modelClass]) {
            if (isset($input[$key]) && is_string($input[$key]) && empty($input[$column])) {
                $related = $modelClass::where('public_id', $input[$key])->orWhere('uuid', $input[$key])->first();
                if ($related) {
                    $input[$column] = $related->uuid;
                }
                unset($input[$key]);
            }
        }

        return $input;
    }

    /**
     * @return array<string, array{0:string,1:class-string<Model>}>
     */
    protected function publicIdMap(): array
    {
        return [];
    }
}
