<?php


use App\Http\Controllers\ChatSimulatorController;
use Illuminate\Support\Facades\Route;
use App\Core\OpenApiSpecs\OpenApiSpecsController;

/** App OpenApi controller */
Route::get("/v1/docs", [OpenApiSpecsController::class, "docs"]);
Route::get("/v1/openapi.yaml", [OpenApiSpecsController::class, "yaml"]);
Route::get("/v1/documentation/{markdownfile}", [OpenApiSpecsController::class, "documentation"]);
Route::get("/v1/schemas/{schema}", [OpenApiSpecsController::class, "getSchema"]);



/** application controllers */
foreach (glob(__DIR__ . "/api/*.php") as $filename) {
    include $filename;
}
