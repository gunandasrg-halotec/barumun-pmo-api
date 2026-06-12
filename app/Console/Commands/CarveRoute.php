<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;
use OpenApi\Generator as OpenApiGenerator;
use Symfony\Component\Finder\Finder;
use function Laravel\Prompts\confirm;

class CarveRoute extends Carve
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'carve:route';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create route base on swagger comments';

    protected $type = 'Route';

    protected function getStub()
    {

    }



    /**
     * Execute the console command.
     *
     * @return int
     */


    public function handle()
    {
        $ccName = $this->getNameInput();
        $cc = new CarveController();
        $ccPath = $cc->getTargetPath();
        $spec = base_path('/app/Core/OpenApiSpecs');
        $sourceFile = $ccPath . DIRECTORY_SEPARATOR . $ccName . ".php";
        // $sourceFile = base_path($ccName);

        $filename = basename($sourceFile);
        $this->info($sourceFile);
        if (!file_exists($sourceFile)) {
            $this->error("File not found");
            return self::FAILURE;
        }




        $targetFile = base_path("routes/api/$filename");

        if (file_exists($targetFile)) {
            $this->warn("file already exists; ");
            $this->comment("path: $targetFile");
            $answer = confirm("Overrides existing file?");
            if (!$answer) {
                return 0;
            }
        }
        $this->info($filename);

        $className = str_replace("/", "\\", $ccName);
        $controllerClass = "use App\Http\Controllers\\$className;";
        $routeClass = "use Illuminate\\Support\\Facades\\Route;";

        $filecontent = "<?php" . PHP_EOL;
        ;
        $filecontent .= $controllerClass . PHP_EOL;
        $filecontent .= $routeClass . PHP_EOL . PHP_EOL;


        $finder = Finder::create()
            ->files()
            ->append([$spec, $sourceFile]);

        $openApi = (new OpenApiGenerator())->generate($finder);

        foreach ($openApi->paths as $pathItem) {
            $operations = $pathItem->operations();
            foreach ($operations as $operation) {
                $this->info("path: $operation->path");
                $this->info("method: $operation->method");
                $this->info("operationId:$operation->operationId");
                $this->info("\n");
                [$className, $function] = array_pad(explode("@", $operation->operationId), 3, null);
                if (empty($function)) {
                    $this->error("function name was not defined in operation_id");
                    return 1;
                }
                if (empty($operation->path)) {
                    $this->error("Path was not defined");
                    return 1;
                }

                $filecontent .= "Route::$operation->method(\"$operation->path\", [$className::class,\"$function\"])" . PHP_EOL;
                $name = Str::snake(str_replace("@", ".", $operation->operationId));
                $filecontent .= "    ->name(\"$name\")";
                $middleware = [];
                if (
                    is_array($operation->security) &&
                    count($operation->security) > 0
                ) {
                    foreach ($operation->security as $key => $value) {
                        $keys = array_keys($value);
                        $middleware[] = "'{$keys[0]}'";
                    }
                }
                if (count($middleware) > 0) {
                    $filecontent .= PHP_EOL . "    ->middleware(" . implode(",", $middleware) . ");";
                } else {
                    $filecontent .= ";";
                }
                $filecontent .= PHP_EOL;
            }
        }


        $fhandle = fopen($targetFile, "w");
        fwrite($fhandle, $filecontent);
        fclose($fhandle);

        return 0;
    }
    public function handlex()
    {
        $ccName = $this->getNameInput();
        $cc = new CarveController();
        $ccPath = $cc->getTargetPath();

        $sourceFile = $ccPath . DIRECTORY_SEPARATOR . $ccName . ".php";

        $this->info($sourceFile);
        if (!file_exists($sourceFile)) {
            $this->error("File not found");
            return self::FAILURE;
        }


        if (Str::startsWith($ccName, "Api/")) {
            $ccName = Str::replace("Api/", "", $ccName);
        }

        $targetFile = base_path("routes/api/$ccName.php");
        if (file_exists($targetFile)) {
            $this->warn("file already exists; ");
            $this->comment("path: $targetFile");
            $answer = confirm("Overrides existing file?");
            if (!$answer) {
                return 0;
            }
        }
        $this->info($ccName);

        $fhandle = fopen($targetFile, "w");
        fwrite($fhandle, "<?php\n\n");
        $controllerClass = "App\Http\Controllers\\$ccName";
        $routeClass = "use Illuminate\\Support\\Facades\\Route;";
        $obj = new $controllerClass;
        $reflection = new \ReflectionObject($obj);

        fwrite($fhandle, "$routeClass\n");
        fwrite($fhandle, "use $controllerClass;\n");

        foreach ($reflection->getMethods() as $method) {
            $attributes = $method->getAttributes();
            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $this->writeToFile($fhandle, $instance, $ccName);

            }
        }
        fclose($fhandle);
        return 0;
    }

    public function createResource()
    {

    }



    private function writeToFile($fhandle, $d, $controllerClass)
    {
        $path = property_exists($d, "path") ? $d->path : "";
        $method = property_exists($d, "method") ? $d->method : "";
        $operationId = property_exists($d, "operationId") ? $d->operationId : "";
        $security = property_exists($d, "security") ? $d->security : "";
        $x = property_exists($d, "x") ? $d->x : "";

        if (empty($path) || empty($method)) {
            return;
        }
        $pathname = null;
        if (is_array($x)) {

            if (array_key_exists("pathname", $x)) {
                $pathname = $x["pathname"];
            }
        }

        $this->info("path : " . $path);
        $this->info("method : " . $method);
        $this->info("operationId : " . $operationId);
        $this->info("\n");

        if (Str::startsWith($path, "/api")) {
            $path = Str::replaceFirst("/api", "", $path);
        }



        $operationId = Str::replaceFirst("$controllerClass@", "", $operationId);
        $route = "Route::$method('$path', [$controllerClass::class, \"$operationId\"])";
        if (!empty($pathname)) {
            $route .= "->name(\"$pathname\")";
        }
        fwrite($fhandle, $route);
        //Route::get('/v1/contacts', [ContactController::class, "index"]);//->middleware(["auth"]);
        $middleware = [];



        if (is_array($security)) {
            foreach ($security as $key => $value) {
                $keys = array_keys($value);
                $middleware[] = "'{$keys[0]}'";

            }
        }
        if (property_exists($d, "x")) {
            if ($d->x != OpenApiGenerator::UNDEFINED) {
                if (array_key_exists("allows", $d->x)) {
                    $roles = [];
                    foreach ($d->x["allows"] as $value) {
                        $roles[] = $value->name;
                    }
                    $middleware[] = '"' . "role:" . implode(",", $roles) . '"';
                }
            }
        }



        if (count($middleware) > 0) {
            fwrite($fhandle, "\n");
            fwrite($fhandle, '   ->middleware (' . implode(",", $middleware) . ')');
        }
        fwrite($fhandle, ";\n");



    }
}