<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Pail\ValueObjects\Origin\Console;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\note;
use Illuminate\Console\Concerns\CreatesMatchingTest;
use function Laravel\Prompts\confirm;

use Symfony\Component\Yaml\Yaml;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\suggest;
use function Laravel\Prompts\text;
use function Laravel\Prompts\intro;
#[AsCommand(name: 'carve:json-schema')]
class CarveJsonSchema extends Carve
{
    use CreatesMatchingTest, SchemaTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'carve:json-schema';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected function createResource()
    {

    }
    protected function getStub()
    {
    }
    public function handle()
    {
        intro("Creating json-schema");

        $this->dbColums = $this->getTableColumns($this->getNameInput());
        $this->setClassName($this->getNameInput());
        $this->tablename = $this->getNameInput();

        $properties = [];
        foreach ($this->dbColums as $column) {
            $subProp = [];
            $type = $column["type"];
            switch ($column["type"]) {
                case "date":
                    $type = "string";
                    $subProp["format"] = $column["type"];
                    break;
                case "datetime":
                    $type = "string";
                    $subProp["format"] = "date-time";
                    break;
                case "float":
                    $type = "number";
                    break;

            }


            $subProp["type"] = $type;
            // print_r($column["name"]);

            if (!empty($column["max"])) {
                $subProp["maxLength"] = (int) $column["max"];
            }

            $properties[$column["name"]] = $subProp;
        }
        $r = [
            // "schema" => $this->getClassName(),
            // "title" => $this->getClassName(),
            // "description" => "",
            "type" => "object",
            "properties" => $properties
        ];

        $fname = Str::replace("_", "-", Str::singular($this->getNameInput()));
        $targetPath = base_path() . "/app/Core/OpenApiSpecs/schema/{$fname}.yaml";

        //file_put_contents($targetPath, json_encode($r, JSON_PRETTY_PRINT));
        $c = Yaml::dump($r, 4, 2);
        file_put_contents($targetPath, $c);
        $this->info($targetPath);
    }
}