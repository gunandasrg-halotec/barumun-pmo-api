<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActualCostTransaction;
use App\Models\ProgressEntry;
use App\Models\Project;
use App\Services\CostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActualCostController extends Controller
{
    public function __construct(private CostService $costService) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        $query = ActualCostTransaction::with([
            'progressEntry.wbdNode', 'enteredByUser.role', 'reviewedByUser',
        ])
            ->where('project_id', $project->id)
            ->orderByDesc('transaction_date');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        $costs = $query->paginate($request->get('limit', 20));

        return response()->json([
            'success' => true,
            'message' => 'Cost transactions fetched successfully',
            'data' => $costs->map(fn ($c) => $this->formatCost($c)),
            'meta' => [
                'page' => $costs->currentPage(),
                'limit' => $costs->perPage(),
                'total' => $costs->total(),
            ],
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        if (!$request->user()->canInputCost()) {
            abort(403, 'You do not have permission to input cost transactions.');
        }

        $validated = $request->validate([
            'progress_entry_id' => ['required', 'uuid', 'exists:progress_entries,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
        ]);

        $progressEntry = ProgressEntry::findOrFail($validated['progress_entry_id']);

        $cost = $this->costService->createCostTransaction(
            $project,
            $progressEntry,
            $validated,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Cost transaction created successfully',
            'data' => $this->formatCost($cost),
        ], 201);
    }

    public function show(Request $request, ActualCostTransaction $actualCostTransaction): JsonResponse
    {
        $actualCostTransaction->load([
            'project', 'progressEntry.wbdNode',
            'enteredByUser.role', 'reviewedByUser', 'rejectedByUser',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cost transaction fetched successfully',
            'data' => $this->formatCost($actualCostTransaction, true),
        ]);
    }

    public function approve(Request $request, ActualCostTransaction $actualCostTransaction): JsonResponse
    {
        $cost = $this->costService->approveCost($actualCostTransaction, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Cost transaction approved successfully',
            'data' => $this->formatCost($cost->load(['reviewedByUser'])),
        ]);
    }

    public function reject(Request $request, ActualCostTransaction $actualCostTransaction): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'min:5'],
        ]);

        $cost = $this->costService->rejectCost(
            $actualCostTransaction,
            $request->user(),
            $request->reason
        );

        return response()->json([
            'success' => true,
            'message' => 'Cost transaction rejected',
            'data' => $this->formatCost($cost->load(['rejectedByUser'])),
        ]);
    }

    private function formatCost(ActualCostTransaction $cost, bool $detail = false): array
    {
        $data = [
            'id' => $cost->id,
            'project_id' => $cost->project_id,
            'progress_entry' => $cost->progressEntry ? [
                'id' => $cost->progressEntry->id,
                'progress_date' => $cost->progressEntry->progress_date?->toDateString(),
                'wbd_node' => $cost->progressEntry->wbdNode ? [
                    'id' => $cost->progressEntry->wbdNode->id,
                    'name' => $cost->progressEntry->wbdNode->name,
                ] : null,
            ] : null,
            'amount' => $cost->amount,
            'transaction_date' => $cost->transaction_date?->toDateString(),
            'description' => $cost->description,
            'entered_by' => $cost->enteredByUser ? [
                'id' => $cost->enteredByUser->id,
                'full_name' => $cost->enteredByUser->full_name,
                'role' => $cost->enteredByUser->role?->role_name,
            ] : null,
            'status' => $cost->status,
            'reviewed_by' => $cost->reviewedByUser ? [
                'id' => $cost->reviewedByUser->id,
                'full_name' => $cost->reviewedByUser->full_name,
            ] : null,
            'reviewed_at' => $cost->reviewed_at,
            'rejected_by' => $cost->rejectedByUser ? [
                'id' => $cost->rejectedByUser->id,
                'full_name' => $cost->rejectedByUser->full_name,
            ] : null,
            'rejected_at' => $cost->rejected_at,
            'rejection_reason' => $cost->rejection_reason,
            'created_at' => $cost->created_at,
        ];

        return $data;
    }
}
