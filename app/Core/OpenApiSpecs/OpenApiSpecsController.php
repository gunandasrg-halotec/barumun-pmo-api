<?php
namespace App\Core\OpenApiSpecs;

use Generator;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\OpenApi;
use Request;

define('API_VERSION', config('app.version'));

#[OA\Info(title: 'PMO Backend', version: API_VERSION,
    description: API_DESCRIPTION,
)]
#[OA\Server(
    url: "/api",
    description: "Local Server"
)]

#[OA\Server(
    url: "http://8.219.106.148:8021",
    description: "Staging Server"
)]




#[OA\SecurityScheme(
    type: "http",
    description: "Human Persons: Standard users accessing the dashboard, managing accounts, or viewing reports.",
    name: "Token based Based",
    in: "header",
    scheme: "bearer",
    bearerFormat: "JWT",
    securityScheme: "jwtAuth")]

#[OA\SecurityScheme(
    type: "apiKey",
    description: "Non-Person Entities: Automated systems, IOT devices, or Kiosk hardware performing background tasks.",
    name: "X-API-KEY",
    in: "header",
    securityScheme: "keyAuth")]

#[OA\Contact(
    email: "support@halotec-indonesia.com"
)]
#[OA\Tag(
    name: AUTHENTICATION_TAG,
    description: "Authentications functions"
)]

class OpenApiSpecsController
{
    public $paths = [];
    public function __construct()
    {
        $this->paths = [
            base_path() . '/app/Core/OpenApiSpecs',
            base_path() . '/app/Http/Controllers',
            // base_path() . '/app/Http/Requests',
            // base_path() . '/app/Http/Resources',
            // base_path() . '/app/Enums',
            base_path() . '/app/Models',

        ];
    }

    public function docs()
    {


        $finder = new \OpenApi\SourceFinder($this->paths, ["ActualCostController.php"]);

        $openapi = (new \OpenApi\Generator())->generate($finder);
        $currentEnv = 'DEVELOPMENT';
        $tagsToRemove = [];
        $alltags = [];
        if ($openapi->paths) {
            foreach ($openapi->paths as $path => $pathItem) {
                // Paths can have multiple operations (get, post, put, delete, etc.)
                foreach ($pathItem->operations() as $operation) {
                    $alltags = array_merge($alltags, $operation->tags);
                    // Check if our custom x-property exists and matches the current env
                    if (isset($operation->x['show-in']) && $operation->x['show-in'] === $currentEnv) {
                        $tagsToRemove = array_merge($tagsToRemove, $operation->tags);
                        unset($openapi->paths[$path]);
                    }
                }

            }

        }



        $alltags = array_count_values($alltags);
        $tagsToRemove = array_count_values($tagsToRemove);
        $result = array_filter($alltags, function ($value, $key) use ($tagsToRemove) {
            return array_key_exists($key, $tagsToRemove);
        }, ARRAY_FILTER_USE_BOTH);

        // Subtract the values of $b from our filtered result
        array_walk($result, function (&$value, $key) use ($tagsToRemove) {
            $value -= $tagsToRemove[$key];
        });
        // $result = array_keys($result, 0);
        foreach ($openapi->tags as $t) {

            if (array_key_exists($t->name, $result)) {
                $offset = array_search($t->name, array_keys($result));
                if ($offset!==false)
                    unset($openapi->tags[$offset]);
            }
        }




        // return $result;
        return response(
            $openapi,
            200,
            [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Content-Type' => ' application/json'
            ]
        );
    }

    /**
     * Return JSON represent partial schema
     * @param \Request $request
     * @param string $schema
     * @return \Illuminate\Http\Response
     */
    public function getSchema(Request $request, string $schema)
    {

        $path = __DIR__ . DIRECTORY_SEPARATOR . "schema/$schema";
        if (!file_exists($path)) {
            abort(404, "Schema not found!");
        }
        $js = file_get_contents($path);
        return response($js)->withHeaders(
            [
                // "content-type" => "application/x-yaml",
                // "Content-Security-Policy" => "default-src *; style-src * 'unsafe-inline'; script-src * 'unsafe-eval';img-src * data:;",
            ]
        );
    }


    public function documentation(Request $request, string $markdownfile)
    {

        $path = __DIR__ . DIRECTORY_SEPARATOR . "documentation/$markdownfile";
        $content = "# Not Available";
        if (file_exists($path)) {
            $md = file_get_contents($path);

            $pattern = "/";

            $content = AppMarkdown::render($md);
        } else {
            /**
             * create requested file with blank content
             */

            $content = str_replace(".md", "", $markdownfile);
            [$verb, $endpoint] = explode("-", $content, 2);
            $endpoint = str_replace("-", "/", $endpoint);
            $endpoint = "/v1/{$endpoint}";
            $verb = strtoupper($verb);

            $content = "## _{$verb}_{.green} {$endpoint}\n";
            file_put_contents($path, $content);
            $content = AppMarkdown::render($content);
        }


        return view("documentation", ["content" => $content]);

    }

    public function yaml()
    {
        $openapi = (new \OpenApi\Generator)->generate($this->paths);

        return response($openapi->toYaml())->header('Content-Type', 'application/x-yaml');
        ;
    }

}