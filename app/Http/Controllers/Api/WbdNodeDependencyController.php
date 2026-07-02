<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WbdNode;
use App\Models\WbdNodeDependency;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Manage task dependencies between WBD nodes (ITEM or GROUP).
 * Only allowed when the WBD version is in DRAFT status.
 * Automatically adjusts dates upon dependency creation (cascade).
 */
class WbdNodeDependencyController extends Controller
{
    // POST /v1/wbd-nodes/{node}/dependencies
    public function store(Request $request, WbdNode $node): JsonResponse
    {
        $request->validate([
            'predecessor_node_id' => ['required', 'uuid', 'exists:wbd_nodes,id'],
            'dependency_type'     => ['required', 'in:FS,SS,FF,SF'],
        ]);

        $version = $node->wbdVersion;
        if ($version->status !== 'DRAFT') {
            return response()->json(['message' => 'Dependensi hanya bisa diubah saat WBD dalam status DRAFT.'], 422);
        }

        $predecessorId = $request->predecessor_node_id;

        if ($predecessorId === $node->id) {
            return response()->json(['message' => 'Task tidak bisa bergantung pada dirinya sendiri.'], 422);
        }

        // Deep cycle detection using BFS through the full dependency graph
        if ($this->wouldCreateCycle($node->id, $predecessorId)) {
            return response()->json(['message' => 'Relasi ini akan membuat siklus (circular dependency).'], 422);
        }

        DB::transaction(function () use ($node, $predecessorId, $request, &$dep) {
            $dep = WbdNodeDependency::updateOrCreate(
                ['predecessor_node_id' => $predecessorId, 'successor_node_id' => $node->id],
                ['dependency_type' => $request->dependency_type]
            );

            $predecessor = WbdNode::find($predecessorId);

            // Adjust successor dates based on dependency type, then cascade
            $this->adjustAndCascade($predecessor, $node, $request->dependency_type, []);
        });

        $dep->load('predecessor');

        return response()->json([
            'success' => true,
            'message' => 'Dependensi berhasil ditambahkan dan tanggal disesuaikan.',
            'data'    => $this->format($dep),
        ], 201);
    }

    // Trigger cascade from an already-updated predecessor (called after node date edit).
    public function cascadeFrom(WbdNode $node): void
    {
        $this->cascadeSuccessors($node->fresh(), []);
    }

    // DELETE /v1/wbd-node-dependencies/{dependency}
    public function destroy(WbdNodeDependency $dependency): JsonResponse
    {
        $version = $dependency->successor->wbdVersion;
        if ($version->status !== 'DRAFT') {
            return response()->json(['message' => 'Dependensi hanya bisa diubah saat WBD dalam status DRAFT.'], 422);
        }

        $dependency->delete();

        return response()->json(['success' => true, 'message' => 'Dependensi berhasil dihapus.']);
    }

    // ─── Date cascade logic ───────────────────────────────────────────────────

    /**
     * Adjust the successor's dates based on the dependency type and predecessor's dates,
     * then recursively cascade to all successors of the successor.
     *
     * @param WbdNode $predecessor
     * @param WbdNode $successor
     * @param string  $depType  FS|SS|FF|SF
     * @param array   $visited  Node IDs already processed (cycle guard)
     */
    private function adjustAndCascade(WbdNode $predecessor, WbdNode $successor, string $depType, array $visited): void
    {
        if (in_array($successor->id, $visited)) return;
        $visited[] = $successor->id;

        $predStart = $this->effectiveStartDate($predecessor);
        $predEnd   = $this->effectiveEndDate($predecessor);

        if (!$predStart && !$predEnd) return; // predecessor has no dates — nothing to adjust

        if ($successor->node_type === 'ITEM') {
            $this->adjustItemDates($successor, $predStart, $predEnd, $depType);
            $successor->refresh();
        } else {
            // GROUP successor: find earliest-start ITEM inside the group
            $items      = $this->allDescendantItems($successor);
            $earliest   = $items->sortBy(fn ($n) => $n->start_date?->timestamp ?? PHP_INT_MAX)->first();
            if ($earliest) {
                $this->adjustItemDates($earliest, $predStart, $predEnd, $depType);
                $earliest->refresh();
                // Cascade through the earliest item's own successors (in-group chain)
                $this->cascadeSuccessors($earliest, $visited);
            }
            return; // GROUP itself doesn't store start/end, children handle it
        }

        // Cascade to all direct successors of the (now-updated) successor
        $this->cascadeSuccessors($successor, $visited);
    }

    /**
     * For a given node (already date-updated), propagate its new dates to
     * all its dependency successors.
     */
    private function cascadeSuccessors(WbdNode $node, array $visited): void
    {
        $deps = WbdNodeDependency::where('predecessor_node_id', $node->id)->with('successor')->get();
        foreach ($deps as $dep) {
            $this->adjustAndCascade($node, $dep->successor, $dep->dependency_type, $visited);
        }
    }

    /**
     * Apply date adjustment to an ITEM node based on dependency type.
     */
    private function adjustItemDates(WbdNode $item, ?string $predStart, ?string $predEnd, string $depType): void
    {
        $duration = max(1, (int) $item->duration_days);

        switch ($depType) {
            case 'FS':
                // Successor starts the day after predecessor ends
                if (!$predEnd) return;
                $newStart = Carbon::parse($predEnd)->addDay();
                $newEnd   = $newStart->copy()->addDays($duration - 1);
                break;

            case 'SS':
                // Successor starts when predecessor starts
                if (!$predStart) return;
                $newStart = Carbon::parse($predStart);
                $newEnd   = $newStart->copy()->addDays($duration - 1);
                break;

            case 'FF':
                // Successor ends when predecessor ends
                if (!$predEnd) return;
                $newEnd   = Carbon::parse($predEnd);
                $newStart = $newEnd->copy()->subDays($duration - 1);
                break;

            case 'SF':
                // Successor ends when predecessor starts
                if (!$predStart) return;
                $newEnd   = Carbon::parse($predStart);
                $newStart = $newEnd->copy()->subDays($duration - 1);
                break;

            default:
                return;
        }

        $item->update([
            'start_date' => $newStart->toDateString(),
            'end_date'   => $newEnd->toDateString(),
        ]);
    }

    // ─── Effective dates (handles GROUP by looking at descendants) ────────────

    private function effectiveStartDate(WbdNode $node): ?string
    {
        if ($node->node_type === 'ITEM') {
            return $node->start_date?->toDateString();
        }
        $dates = $this->allDescendantItems($node)->pluck('start_date')->filter()->map(fn ($d) => $d->toDateString());
        return $dates->isNotEmpty() ? $dates->min() : null;
    }

    private function effectiveEndDate(WbdNode $node): ?string
    {
        if ($node->node_type === 'ITEM') {
            return $node->end_date?->toDateString();
        }
        $dates = $this->allDescendantItems($node)->pluck('end_date')->filter()->map(fn ($d) => $d->toDateString());
        return $dates->isNotEmpty() ? $dates->max() : null;
    }

    /**
     * Recursively collect all ITEM descendants of a GROUP node.
     */
    private function allDescendantItems(WbdNode $group): \Illuminate\Support\Collection
    {
        $result   = collect();
        $children = WbdNode::where('parent_node_id', $group->id)->get();
        foreach ($children as $child) {
            if ($child->node_type === 'ITEM') {
                $result->push($child);
            } else {
                $result = $result->merge($this->allDescendantItems($child));
            }
        }
        return $result;
    }

    // ─── Cycle detection (deep BFS) ───────────────────────────────────────────

    /**
     * Would adding (predecessorId → successorId) create a cycle?
     * Walks all reachable successors from $newSuccessorId; if $predecessorId appears, it's a cycle.
     */
    private function wouldCreateCycle(string $newSuccessorId, string $predecessorId): bool
    {
        $queue   = [$newSuccessorId];
        $visited = [];

        while (!empty($queue)) {
            $current = array_shift($queue);
            if ($current === $predecessorId) return true;
            if (in_array($current, $visited)) continue;
            $visited[] = $current;

            $successors = WbdNodeDependency::where('predecessor_node_id', $current)
                ->pluck('successor_node_id')
                ->toArray();
            $queue = array_merge($queue, $successors);
        }
        return false;
    }

    // ─── Format response ──────────────────────────────────────────────────────

    private function format(WbdNodeDependency $dep): array
    {
        return [
            'id'                 => $dep->id,
            'predecessor'        => [
                'id'        => $dep->predecessor->id,
                'code'      => $dep->predecessor->code,
                'name'      => $dep->predecessor->name,
                'node_type' => $dep->predecessor->node_type,
            ],
            'successor_node_id'  => $dep->successor_node_id,
            'dependency_type'    => $dep->dependency_type,
        ];
    }
}
