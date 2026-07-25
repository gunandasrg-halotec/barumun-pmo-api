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
    /**
     * Summary of __construct
     * @param string $path
     * @param string $operationId
     * @param mixed $description
     * @param mixed $summary
     * @param mixed $security
     * @param mixed $tags
     * @param string[]|null $parameters
     * @param Parameter[]|null $filters
     * @param mixed $externalDocs
     * @param mixed $x
     */
    public function __construct(
        string $path,
        string $operationId,
        ?string $description = null,
        ?string $summary = null,
        ?array $security = null,
        ?array $tags = null,
        ?array $parameters = null,
        ?array $filters = null,
        ?ExternalDocumentation $externalDocs = null,
        ?array $x = null,
    ) {


        if (!$parameters) {
            $parameters = ["page", "per-page", "search", "sort-by", "sort-dir"];
        }
        $parameters = array_map(function ($item) {
            $rvalue = null;
            switch ($item) {
                case "sort-dir":
                    $rvalue = new Parameter(in: "query", name: $item, schema: new Schema(type: "string", enum: ["asc", "desc"]));
                    break;
                case "page":
                case "per-page":
                    $rvalue = new Parameter(in: "query", name: $item, schema: new Schema(type: "integer"));
                    break;
                default:
                    $rvalue = new Parameter(in: "query", name: $item, schema: new Schema(type: "string"));
            }

            return $rvalue;
        }, $parameters);
        $filters ??=[];
        $parameters = array_merge($parameters, $filters);

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