<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WbdNode;
use App\Models\WbdVersion;
use App\Services\WbdService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WbdNodeController extends Controller
{
    public function __construct(private WbdService $wbdService) {}

    public function index(Request $request, WbdVersion $wbdVersion): JsonResponse
    {
        $nodes = $wbdVersion->nodes()->get();

        // Build tree structure
        $tree = $this->buildTree($nodes);

        return response()->json([
            'success' => true,
            'message' => 'WBD nodes fetched successfully',
            'data' => $tree,
        ]);
    }

    public function store(Request $request, WbdVersion $wbdVersion): JsonResponse
    {
        if (!$request->user()->canManageWbd()) {
            abort(403, 'You do not have permission to manage WBD nodes.');
        }

        if (!$wbdVersion->canBeEdited()) {
            abort(403, 'This WBD version cannot be edited. Only DRAFT versions are editable.');
        }

        $validated = $request->validate([
            'parent_node_id' => ['nullable', 'uuid', 'exists:wbd_nodes,id'],
            'node_type' => ['required', Rule::in(['GROUP', 'ITEM'])],
            'code' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'description' => ['nullable', 'string'],
            'unit' => ['nullable', 'string', 'max:50'],
            'volume' => ['nullable', 'numeric', 'min:0'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'duration_days' => ['nullable', 'integer', 'min:1'],
            'status' => ['sometimes', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        // Validate ITEM requires unit, volume, rate
        if ($validated['node_type'] === 'ITEM') {
            $request->validate([
                'unit' => ['required', 'string', 'max:50'],
                'volume' => ['required', 'numeric', 'min:0'],
                'rate' => ['required', 'numeric', 'min:0'],
                'start_date' => ['required', 'date'],
                'duration_days' => ['required', 'integer', 'min:1'],
            ]);
        }

        // Validate parent belongs to same version
        if (!empty($validated['parent_node_id'])) {
            $parent = WbdNode::find($validated['parent_node_id']);
            if ($parent->wbd_version_id !== $wbdVersion->id) {
                abort(422, 'Parent node must belong to the same WBD version.');
            }
        }

        // Unique code within version
        $codeExists = WbdNode::where('wbd_version_id', $wbdVersion->id)
            ->where('code', $validated['code'])
            ->exists();
        if ($codeExists) {
            abort(422, 'Node code must be unique within this WBD version.');
        }

        // Calculate end_date
        $endDate = null;
        if (!empty($validated['start_date']) && !empty($validated['duration_days'])) {
            $endDate = date('Y-m-d', strtotime($validated['start_date'] . ' +' . ($validated['duration_days'] - 1) . ' days'));
        }

        // Calculate planned_cost
        $plannedCost = 0;
        if ($validated['node_type'] === 'ITEM') {
            $plannedCost = ($validated['volume'] ?? 0) * ($validated['rate'] ?? 0);
        }

        $node = WbdNode::create([
            'wbd_version_id' => $wbdVersion->id,
            'parent_node_id' => $validated['parent_node_id'] ?? null,
            'node_type' => $validated['node_type'],
            'code' => $validated['code'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'unit' => $validated['unit'] ?? null,
            'volume' => $validated['volume'] ?? null,
            'rate' => $validated['rate'] ?? null,
            'planned_cost' => $plannedCost,
            'start_date' => $validated['start_date'] ?? null,
            'duration_days' => $validated['duration_days'] ?? null,
            'end_date' => $endDate,
            'status' => $validated['status'] ?? 'ACTIVE',
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        // Recalculate the version totals
        $this->wbdService->recalculate($wbdVersion);

        return response()->json([
            'success' => true,
            'message' => 'WBD node created successfully',
            'data' => $this->formatNode($node),
        ], 201);
    }

    public function update(Request $request, WbdNode $wbdNode): JsonResponse
    {
        if (!$request->user()->canManageWbd()) {
            abort(403, 'You do not have permission to manage WBD nodes.');
        }

        $version = $wbdNode->wbdVersion;
        if (!$version->canBeEdited()) {
            abort(403, 'This WBD version cannot be edited. Only DRAFT versions are editable.');
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:255'],
            'description' => ['nullable', 'string'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:50'],
            'volume' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'duration_days' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'status' => ['sometimes', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        // Recalculate end_date if dates changed
        if (isset($validated['start_date']) || isset($validated['duration_days'])) {
            $startDate = $validated['start_date'] ?? $wbdNode->start_date?->toDateString();
            $duration = $validated['duration_days'] ?? $wbdNode->duration_days;
            if ($startDate && $duration) {
                $validated['end_date'] = date('Y-m-d', strtotime($startDate . ' +' . ($duration - 1) . ' days'));
            }
        }

        // Recalculate planned_cost if volume or rate changed
        if (isset($validated['volume']) || isset($validated['rate'])) {
            $volume = $validated['volume'] ?? $wbdNode->volume;
            $rate = $validated['rate'] ?? $wbdNode->rate;
            $validated['planned_cost'] = ($volume ?? 0) * ($rate ?? 0);
        }

        $wbdNode->update($validated);

        $this->wbdService->recalculate($version);

        return response()->json([
            'success' => true,
            'message' => 'WBD node updated successfully',
            'data' => $this->formatNode($wbdNode->fresh()),
        ]);
    }

    public function destroy(Request $request, WbdNode $wbdNode): JsonResponse
    {
        if (!$request->user()->canManageWbd()) {
            abort(403, 'You do not have permission to delete WBD nodes.');
        }

        $version = $wbdNode->wbdVersion;
        if (!$version->canBeEdited()) {
            abort(403, 'This WBD version cannot be edited. Only DRAFT versions are editable.');
        }

        // Cannot delete node if it has progress entries
        if ($wbdNode->progressEntries()->exists()) {
            abort(422, 'Cannot delete WBD node that has associated progress entries.');
        }

        // Cannot delete group that has children
        if ($wbdNode->children()->exists()) {
            abort(422, 'Cannot delete WBD node that has children. Delete children first.');
        }

        $wbdNode->delete();
        $this->wbdService->recalculate($version);

        return response()->json([
            'success' => true,
            'message' => 'WBD node deleted successfully',
        ]);
    }

    private function buildTree($nodes, $parentId = null): array
    {
        $tree = [];
        foreach ($nodes as $node) {
            if ($node->parent_node_id === $parentId) {
                $item = $this->formatNode($node);
                $item['children'] = $this->buildTree($nodes, $node->id);
                $tree[] = $item;
            }
        }
        return $tree;
    }

    private function formatNode(WbdNode $node): array
    {
        return [
            'id' => $node->id,
            'wbd_version_id' => $node->wbd_version_id,
            'parent_node_id' => $node->parent_node_id,
            'node_type' => $node->node_type,
            'code' => $node->code,
            'name' => $node->name,
            'description' => $node->description,
            'unit' => $node->unit,
            'volume' => $node->volume,
            'rate' => $node->rate,
            'planned_cost' => $node->planned_cost,
            'component_percent' => $node->component_percent,
            'total_percent' => $node->total_percent,
            'start_date' => $node->start_date?->toDateString(),
            'duration_days' => $node->duration_days,
            'end_date' => $node->end_date?->toDateString(),
            'status' => $node->status,
            'sort_order' => $node->sort_order,
        ];
    }
}
