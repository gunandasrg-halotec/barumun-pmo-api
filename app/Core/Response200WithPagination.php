<?php

namespace App\Core;
use OpenApi\Annotations\Response;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Schema;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Items;
use App\OpenApiSpecs\MetaPagination;
use OpenApi\Generator;
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
/**
 * Create OpenApi Response for validation error
 */
class Response200WithPagination extends Response
{
    /**
     * Summary of __construct
     * @param string $modelClass model class name to render as array
     * @param mixed $description
     * @param mixed $headers
     */
    public function __construct(string $ref, ?string $description = null, ?array $headers = null)
    {

        $content = new JsonContent(
            properties: [
                new Property(
                    property: "data",
                    type: "array",
                    items: new Items(ref: $ref)
                ),
                new Property(
                    property: "meta",
                    type: "object",
                    properties: [
                        new Property(property: "page", type: "integer", minimum: 1, example: 1, description: "current page number"),
                        new Property(property: "limit", type: "integer", minimum: 1, example: 1, description: "data start offset"),
                        // new Property(property: "to", type: "integer", minimum: 1, example: 1, description: "data end  offset"),
                        new Property(property: "total", type: "integer", minimum: 1, example: 1, description: "total number of data"),
                        // new Property(property: "last_page", type: "integer", minimum: 1, example: 1),
                        // new Property(property: "path", type: "string", example: "http://example.com/api/v1/resource", description: "resource path"),
                        // new Property(property: "per_page", type: "integer", minimum: 1, example: 20, description: "number of data on each page"),

                    ]
                )
            ],
        );

        parent::__construct([
            //'ref' => $ref ?? Generator::UNDEFINED,
            'response' => 200,
            'description' => "Http OK  $description",
            'value' => $this->combine($headers, $content, null),
        ]);
    }


}
