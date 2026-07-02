<?php

namespace App\Http\Controllers\Api;

use App\Core\GetIndexRequest;
use App\Core\Response200WithPagination;
use App\Core\Response2xx;
use App\Core\ResponseDefault;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Project;
use App\Models\ReportRecord;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\Schema;

class ReportController extends Controller
{
    // ─── GET /v1/projects/{project}/reports ──────────────────────────────────

    #[GetIndexRequest(
        tags: [REPORT_TAG],
        path: "/v1/projects/{project}/reports",
        operationId: "ReportController@index",
        summary: "List generated reports for a project. All authenticated users.",
        security: [Auth_JWT],
        filters: [
            new OA\Parameter(
                in: "query", name: "filter[report_type]",
                description: "Filter by report type",
                schema: new Schema(type: "string",
                    enum: ["WEEKLY", "MONTHLY", "PROGRESS", "COST", "SUMMARY"])
            ),
        ]
    )]
    #[Response200WithPagination(ref: "schemas/report_resource.yaml", description: "Report list")]
    #[ResponseDefault()]
    public function index(ReportRequest $request, Project $project): JsonResponse
    {
        $query = ReportRecord::with(['generatedByUser'])
            ->where('project_id', $project->id)
            ->where('status', 'FINAL')
            ->orderByDesc('generated_at');

        $filter = $request->input('filter', []);
        if (!empty($filter['report_type'])) {
            $query->where('report_type', $filter['report_type']);
        }

        $reports = $query->paginate($request->get('per-page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Reports fetched successfully',
            'data'    => ReportResource::collection($reports),
            'meta'    => [
                'page'  => $reports->currentPage(),
                'limit' => $reports->perPage(),
                'total' => $reports->total(),
            ],
        ]);
    }

    // ─── POST /v1/projects/{project}/reports/generate ────────────────────────

    #[OA\Post(
        tags: [REPORT_TAG],
        path: "/v1/projects/{project}/reports/generate",
        operationId: "ReportController@generate",
        summary: "Generate a project report for a given period and type. Allowed: PM, Admin Proyek.",
        parameters: [
            new OA\Parameter(in: "path", name: "project", required: true,
                schema: new Schema(type: "string", format: "uuid")),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "schemas/generate_report_request.yaml")
        ),
        security: [Auth_JWT]
    )]
    #[Response2xx(response: "201", description: "Report generated and record created")]
    #[ResponseDefault()]
    public function generate(ReportRequest $request, Project $project): JsonResponse
    {
        $data = $request->validated();

        // Build a deterministic file path — actual PDF generation is a Phase 9+ enhancement.
        // The record acts as the report manifest; file_path stores where the file will live.
        $fileName = implode('_', [
            $project->project_code,
            $data['report_type'],
            str_replace('-', '', $data['period_start']),
            str_replace('-', '', $data['period_end']),
            now()->format('His'),
        ]) . '.pdf';

        $filePath = 'reports/' . $project->id . '/' . $fileName;

        $report = ReportRecord::create([
            'project_id'   => $project->id,
            'report_type'  => $data['report_type'],
            'period_start' => $data['period_start'],
            'period_end'   => $data['period_end'],
            'file_path'    => $filePath,
            'generated_by' => $request->user()->id,
            'generated_at' => now(),
            'status'       => 'FINAL',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Report generated successfully',
            'data'    => new ReportResource($report->load(['generatedByUser', 'project'])),
        ], 201);
    }

    // ─── GET /v1/reports/{reportRecord} ──────────────────────────────────────

    #[OA\Get(
        tags: [REPORT_TAG],
        path: "/v1/reports/{reportRecord}",
        operationId: "ReportController@show",
        summary: "Get a single report record. All authenticated users.",
        parameters: [
            new OA\Parameter(in: "path", name: "reportRecord", required: true,
                schema: new Schema(type: "string", format: "uuid")),
        ],
        security: [Auth_JWT]
    )]
    #[Response2xx(description: "Report record detail")]
    #[ResponseDefault()]
    public function show(ReportRequest $request, ReportRecord $reportRecord): JsonResponse
    {
        $reportRecord->load(['project', 'generatedByUser']);

        return response()->json([
            'success' => true,
            'message' => 'Report record fetched successfully',
            'data'    => new ReportResource($reportRecord),
        ]);
    }
}
