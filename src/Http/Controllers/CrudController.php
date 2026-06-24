<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class CrudController extends BaseController
{
    protected array $validationRules = [];

    protected array $searchableFields = [];

    protected array $relationships = [];

    /**
     * Columns that may be used in `?sort_by=`. Empty disables sorting
     * (prevents arbitrary-column ordering / SQL identifier injection).
     *
     * @var list<string>
     */
    protected array $sortableFields = [];

    protected int $perPage = 15;

    protected int $maxPerPage = 100;

    public function __construct(protected Model $model) {}

    public function getAllRecords(Request $request): JsonResponse
    {
        $query = $this->model->query();

        if (!empty($this->searchableFields) && $request->filled('search')) {
            // Escape LIKE wildcards so user input can't broaden the match.
            $term = addcslashes((string) $request->get('search'), '%_\\');
            $query->where(function ($q) use ($term) {
                foreach ($this->searchableFields as $field) {
                    $q->orWhere($field, 'LIKE', "%{$term}%");
                }
            });
        }

        // Load relationships if defined
        if (!empty($this->relationships)) {
            $query->with($this->relationships);
        }

        if ($request->filled('sort_by') && in_array($request->get('sort_by'), $this->sortableFields, true)) {
            $direction = strtolower((string) $request->get('sort_direction', 'asc')) === 'desc' ? 'desc' : 'asc';
            $query->orderBy($request->get('sort_by'), $direction);
        }

        $records = $query->paginate($this->resolvePerPage($request));

        return response()->json([
            'data' => $records->items(),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
        ]);
    }

    public function getRecordById($id): JsonResponse
    {
        $query = $this->model->query();

        if (!empty($this->relationships)) {
            $query->with($this->relationships);
        }

        $record = $query->findOrFail($id);

        return response()->json(['data' => $record]);
    }

    public function storeRecord(Request $request): JsonResponse
    {
        $validated = $this->validateRequest($request);

        $record = $this->model->create($validated);

        if (!empty($this->relationships)) {
            $record->load($this->relationships);
        }

        return response()->json([
            'message' => 'Record created successfully',
            'data' => $record,
        ], 201);
    }

    public function updateRecord(Request $request, $id): JsonResponse
    {
        $record = $this->model->findOrFail($id);

        $validated = $this->validateRequest($request, $id);

        $record->update($validated);

        if (!empty($this->relationships)) {
            $record->load($this->relationships);
        }

        return response()->json([
            'message' => 'Record updated successfully',
            'data' => $record,
        ]);
    }

    public function deleteRecord($id): JsonResponse
    {
        $record = $this->model->findOrFail($id);
        $record->delete();

        return response()->json([
            'message' => 'Record deleted successfully',
        ], 204);
    }

    protected function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->get('per_page', $this->perPage);

        // Clamp to [1, maxPerPage] so a client cannot request an unbounded page.
        return max(1, min($perPage, $this->maxPerPage));
    }

    protected function validateRequest(Request $request, $id = null): array
    {
        // Without explicit rules, never mass-assign arbitrary input: restrict to
        // the model's fillable attributes (empty fillable => nothing assignable).
        if (empty($this->validationRules)) {
            $fillable = $this->model->getFillable();

            return $fillable === [] ? [] : $request->only($fillable);
        }

        $rules = $this->validationRules;

        if ($id !== null) {
            $rules = $this->applyUniqueIgnore($rules, $id);
        }

        return $request->validate($rules);
    }

    /**
     * Rewrite `unique:` rules to ignore the record being updated, defaulting the
     * column to the field name when omitted (avoids malformed rule strings).
     *
     * @param array<string, mixed> $rules
     * @param int|string           $id
     *
     * @return array<string, mixed>
     */
    private function applyUniqueIgnore(array $rules, $id): array
    {
        foreach ($rules as $field => $rule) {
            if (!is_string($rule) || !str_contains($rule, 'unique:')) {
                continue;
            }

            $segments = array_map(function (string $segment) use ($field, $id) {
                if (!str_starts_with($segment, 'unique:')) {
                    return $segment;
                }

                $params = explode(',', substr($segment, strlen('unique:')));
                $table = $params[0];
                $column = $params[1] ?? (string) $field;

                return "unique:{$table},{$column},{$id},id";
            }, explode('|', $rule));

            $rules[$field] = implode('|', $segments);
        }

        return $rules;
    }
}
