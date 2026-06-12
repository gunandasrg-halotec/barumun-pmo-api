<?php

namespace App\Core;
use App\OpenApiSpecs\DefaultOkResponse;
use Attribute;
use OpenApi\Annotations\Response;
use OpenApi\Attributes\JsonContent;
use OpenApi\Generator;
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
/**
 * Create OpenApi Response 
 */
class Response2xx extends Response
{
    /**
     * Summary of __construct
     * @param mixed $responseCode
     * @param mixed $description
     * @param mixed $headers
     */
    public function __construct($response = "200", string $description = "OK", ?string $ref = null, ?array $headers = null)
    {


        $content = null;
        if ($ref) {
            $content = new JsonContent(ref: $ref);
        }
        parent::__construct([
            'response' => $response,
            'description' => $description,
            'value' => $this->combine($headers, $content, null),
        ]);
    }


}
