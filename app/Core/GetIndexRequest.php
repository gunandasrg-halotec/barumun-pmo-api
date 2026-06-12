<?php

namespace App\Core;
use OpenApi\Annotations\Response;
use OpenApi\Attributes\ExternalDocumentation;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Schema;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\Parameter;
use OpenApi\Annotations\Get as BaseGet;
use OpenApi\Generator;
use function GuzzleHttp\default_ca_bundle;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
/**
 * Short class for OpenApi\Attributes\Get
 * with parameters page, per-page, search, filter, sort-by, sorr-dir in query
 */
class GetIndexRequest extends BaseGet
{
    public $method = 'get';
    public function __construct(
        string $path,
        string $operationId,
        ?string $description = null,
        ?string $summary = null,
        ?array $security = null,
        ?array $tags = null,
        ?array $parameters = null,
        ?ExternalDocumentation $externalDocs = null,
        ?array $x = null,
    ) {


        if (!$parameters) {
            $parameters = ["page", "per-page", "search", "filter", "sort-by", "sort-dir"];
        }
        $parameters = array_map(function ($item) {
            $rvalue = null;
            switch ($item) {
                case "sort-dir":
                    $rvalue = new Parameter( in: "query", name: $item, schema: new Schema(type: "string", enum: ["asc", "desc"]));
                    break;
                case "page":
                case "per-page":
                    $rvalue = new Parameter( in: "query", name: $item, schema: new Schema(type: "integer"));
                    break;
                default:
                    $rvalue = new Parameter( in: "query", name: $item, schema: new Schema(type: "string"));
            }

            return $rvalue;
        }, $parameters);

        parent::__construct(array_filter([
            'path' => $path ?? Generator::UNDEFINED,
            'operationId' => $operationId ?? Generator::UNDEFINED,
            'description' => $description ?? Generator::UNDEFINED,
            'summary' => $summary ?? Generator::UNDEFINED,
            'security' => $security ?? Generator::UNDEFINED,
            'tags' => $tags ?? Generator::UNDEFINED,
            'value' => $this->combine(null, null, $parameters, null),
            'externalDocs' => $externalDocs,
            "x" => $x ?? Generator::UNDEFINED
        ]));
    }
}