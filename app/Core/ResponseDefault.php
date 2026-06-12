<?php

namespace App\Core;

use Attribute;
use OpenApi\Annotations\Response;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;

use OpenApi\Generator;
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
/**
 * Create OpenApi Response 
 */
class ResponseDefault extends Response
{
    /**
     * Summary of __construct
     * @param mixed $responseCode
     * @param mixed $description
     * @param mixed $headers
     */
    public function __construct($responseCode = "default", ?string $description = null, ?array $headers = null)
    {

        $properties = [
            new Property("message", type: "string"),
            new Property("type", type: "string", enum: ["SystemError", "ApplicationError"]),
            new Property("src", type: "string", example: "https://example.com/api/v1/resource"),
        ];

        $content = new JsonContent(properties: $properties);
        parent::__construct([
            'response' => $responseCode,
            'description' => $description ?? "All except http status 2xx and 422",
            'value' => $this->combine($headers, $content, null),
        ]);
    }


}
