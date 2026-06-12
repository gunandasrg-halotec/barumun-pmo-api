<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ReportRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        $reports = ReportRecord::with(['generatedByUser'])
            ->where('project_id', $project->id)
            ->where('status', 'FINAL')
            ->orderByDesc('generated_at')
            ->paginate($request->get('limit', 20));

        return response()->json([
            'success' => true,
            'message' => 'Report records fetched',
            'data' => $reports->map(fn ($r) => $this->formatReport($r)),
            'meta' => [
                'page' => $reports->currentPage(),
                'limit' => $reports->perPage(),
                'total' => $reports->total(),
            ],
        ]);
    }

    public function generate(Request $request, Project $project): JsonResponse
    {
        if (!$request->user()->canGenerateReport()) {
            abort(403, 'You do not have permission to generate reports.');
        }

        $validated = $request->validate([
            'report_type' => ['required', 'string', Rule::in(['WEEKLY', 'MONTHLY', 'COST', 'PROGRESS', 'SUMMARY'])],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'gte:period_start'],
        ]);

        // In a real implementation, this would generate a PDF or Excel file
        // For MVP, we create a record with a placeholder file path
        $filePath = 'reports/' . $project->id . '/' . $validated['report_type'] . '_' . now()->format('YmdHis') . '.pdf';

        $report = ReportRecord::create([
            'project_id' => $project->id,
            'report_type' => $validated['report_type'],
            'period_start' => $validated['period_start'],
            'period_end' => $validated['period_end'],
            'file_path' => $filePath,
            'generated_by' => $request->user()->id,
            'generated_at' => now(),
            'status' => 'FINAL',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Report generated successfully',
            'data' => $this->formatReport($report->load(['generatedByUser'])),
        ], 201);
    }

    public function show(Request $request, ReportRecord $reportRecord): JsonResponse
    {
        $reportRecord->load(['project', 'generatedByUser']);

        return response()->json([
            'success' => true,
            'message' => 'Report record fetched',
            'data' => $this->formatReport($reportRecord, true),
        ]);
    }

    private function formatReport(ReportRecord $report, bool $detail = false): array
    {
        return [
            'id' => $report->id,
            'project_id' => $report->project_id,
            'report_type' => $report->report_type,
            'period_start' => $report->period_start?->toDateString(),
            'period_end' => $report->period_end?->toDateString(),
            'file_path' => $report->file_path,
            'generated_by' => $report->generatedByUser ? [
                'id' => $report->generatedByUser->id,
                'full_name' => $report->generatedByUser->full_name,
            ] : null,
            'generated_at' => $report->generated_at,
            'status' => $report->status,
        ];
    }
}
