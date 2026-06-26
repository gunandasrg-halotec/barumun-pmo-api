<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WbdNode;
use App\Models\WbdNodeDependency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manage task dependencies between WBD ITEM nodes.
 * Only allowed when the WBD version is in DRAFT status.
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

        // Prevent circular dependency (simple: check if successor is already a predecessor of the predecessor)
        $wouldCreateCycle = WbdNodeDependency::where('predecessor_node_id', $node->id)
            ->where('successor_node_id', $predecessorId)
            ->exists();

        if ($wouldCreateCycle) {
            return response()->json(['message' => 'Relasi ini akan membuat siklus (circular dependency).'], 422);
        }

        $dep = WbdNodeDependency::updateOrCreate(
            ['predecessor_node_id' => $predecessorId, 'successor_node_id' => $node->id],
            ['dependency_type' => $request->dependency_type]
        );

        $dep->load('predecessor');

        return response()->json([
            'success' => true,
            'message' => 'Dependensi berhasil ditambahkan.',
            'data'    => $this->format($dep),
        ], 201);
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

    private function format(WbdNodeDependency $dep): array
    {
        return [
            'id'              => $dep->id,
            'predecessor'     => [
                'id'   => $dep->predecessor->id,
                'code' => $dep->predecessor->code,
                'name' => $dep->predecessor->name,
            ],
            'successor_node_id'   => $dep->successor_node_id,
            'dependency_type' => $dep->dependency_type,
        ];
    }
}
