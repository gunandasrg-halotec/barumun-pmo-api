<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProgressEntry;
use App\Models\Project;
use App\Models\WbdNode;
use App\Services\ProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    public function __construct(private ProgressService $progressService) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        $query = ProgressEntry::with([
            'wbdNode', 'enteredByUser.role', 'approvedByUser', 'rejectedByUser',
            'actualCostTransactions',
        ])
            ->where('project_id', $project->id)
            ->orderByDesc('progress_date');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('progress_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('progress_date', '<=', $request->date_to);
        }

        if ($request->filled('wbd_node_id')) {
            $query->where('wbd_node_id', $request->wbd_node_id);
        }

        $entries = $query->paginate($request->get('limit', 20));

        return response()->json([
            'success' => true,
            'message' => 'Progress entries fetched successfully',
            'data' => $entries->map(fn ($e) => $this->formatProgress($e)),
            'meta' => [
                'page' => $entries->currentPage(),
                'limit' => $entries->perPage(),
                'total' => $entries->total(),
            ],
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        if (!$request->user()->canInputProgress()) {
            abort(403, 'You do not have permission to create progress entries.');
        }

        $validated = $request->validate([
            'wbd_node_id' => ['required', 'uuid', 'exists:wbd_nodes,id'],
            'progress_date' => ['required', 'date'],
            'progress_volume' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string'],
        ]);

        $node = WbdNode::findOrFail($validated['wbd_node_id']);

        $entry = $this->progressService->createProgress(
            $project,
            $node,
            $validated,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Progress entry created successfully',
            'data' => $this->formatProgress($entry),
        ], 201);
    }

    public function show(Request $request, ProgressEntry $progressEntry): JsonResponse
    {
        $progressEntry->load([
            'project', 'wbdNode', 'enteredByUser.role',
            'approvedByUser', 'rejectedByUser', 'actualCostTransactions.enteredByUser',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Progress entry fetched successfully',
            'data' => $this->formatProgress($progressEntry, true),
        ]);
    }

    public function approve(Request $request, ProgressEntry $progressEntry): JsonResponse
    {
        $entry = $this->progressService->approveProgress($progressEntry, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Progress approved successfully',
            'data' => $this->formatProgress($entry->load(['approvedByUser'])),
        ]);
    }

    public function reject(Request $request, ProgressEntry $progressEntry): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'min:5'],
        ]);

        $entry = $this->progressService->rejectProgress(
            $progressEntry,
            $request->user(),
            $request->reason
        );

        return response()->json([
            'success' => true,
            'message' => 'Progress rejected',
            'data' => $this->formatProgress($entry->load(['rejectedByUser'])),
        ]);
    }

    private function formatProgress(ProgressEntry $entry, bool $detail = false): array
    {
        $data = [
            'id' => $entry->id,
            'project_id' => $entry->project_id,
            'wbd_node' => $entry->wbdNode ? [
                'id' => $entry->wbdNode->id,
                'code' => $entry->wbdNode->code,
                'name' => $entry->wbdNode->name,
                'unit' => $entry->wbdNode->unit,
            ] : null,
            'progress_date' => $entry->progress_date?->toDateString(),
            'progress_volume' => $entry->progress_volume,
            'note' => $entry->note,
            'entered_by' => $entry->enteredByUser ? [
                'id' => $entry->enteredByUser->id,
                'full_name' => $entry->enteredByUser->full_name,
                'role' => $entry->enteredByUser->role?->role_name,
            ] : null,
            'status' => $entry->status,
            'is_official' => $entry->isOfficial(),
            'approved_by' => $entry->approvedByUser ? [
                'id' => $entry->approvedByUser->id,
                'full_name' => $entry->approvedByUser->full_name,
            ] : null,
            'approved_at' => $entry->approved_at,
            'rejected_by' => $entry->rejectedByUser ? [
                'id' => $entry->rejectedByUser->id,
                'full_name' => $entry->rejectedByUser->full_name,
            ] : null,
            'rejected_at' => $entry->rejected_at,
            'rejection_reason' => $entry->rejection_reason,
            'created_at' => $entry->created_at,
        ];

        if ($detail && $entry->relationLoaded('actualCostTransactions')) {
            $data['actual_costs'] = $entry->actualCostTransactions->map(fn ($c) => [
                'id' => $c->id,
                'amount' => $c->amount,
                'status' => $c->status,
                'transaction_date' => $c->transaction_date?->toDateString(),
            ])->toArray();
        }

        return $data;
    }
}
