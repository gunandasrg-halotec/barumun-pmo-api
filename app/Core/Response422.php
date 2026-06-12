<?php

namespace App\Core;
use OpenApi\Annotations\Response;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Schema;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Items;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
/**
 * Create OpenApi Response for validation error
 */
class Response422 extends Response
{
    /**
     * Summary of __construct
     * @param array $fields eg ["email","password"]
     */
    public function __construct(array $fields)
    {
        $defaultPropeties = [
            new Property("message", type: "string"),
            new Property("type", type: "string", example: "ApplicationError"),
            new Property("src", type: "string", example: "https://example.com/api/v1/resource"),
        ];
        $properties = [];
        foreach ($fields as $prop) {
            $properties[] = new Property(property: $prop, type: "array", items: new Items(type: "string"));
        }
        $headers = [];
        $links = [];
        $content = new JsonContent(
            allOf: [
                new Schema(properties: $defaultPropeties),
                new Schema(
                    properties: [
                        new Property(
                            "invalid_fields",
                            type: "object",
                            anyOf: [
                                new Schema(
                                    properties: $properties
                                )
                            ]

                        )
                    ]
                )
            ],

        );

        parent::__construct([
            //'ref' => $ref ?? Generator::UNDEFINED,
            'response' => 422,
            'description' => "Occurs when request parameters doesn't meet requirement",
            'value' => $this->combine($headers, $content, $links),
        ]);
    }


}

// 'ref' => $ref ?? Generator::UNDEFINED,
// 'response' => $response ?? Generator::UNDEFINED,
// 'description' => $description ?? Generator::UNDEFINED,
// 'x' => $x ?? Generator::UNDEFINED,
// 'attachables' => $attachables ?? Generator::UNDEFINED,
// 'value' => $this->combine($headers, $content, $links),