<?php

namespace App\Console\Commands;

use Illuminate\Console\Concerns\CreatesMatchingTest;

use Illuminate\Foundation\Console\MailMakeCommand;
use Illuminate\Foundation\Console\ModelMakeCommand;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;

#[AsCommand(name: 'carve:model')]
class CarveModel extends Carve
{
    use CreatesMatchingTest, SchemaTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'carve:model';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create model with openApi annotation';
    protected $type = 'Model';

    protected $dbColums = [];


    public function handle()
    {
        $return = parent::handle();
        if ($return == static::SUCCESS) {
            $rClass = new CarveResource();
            $rClassname = $rClass->createQualifyClassName($this->getNameInput());
            $path = $rClass->getTargetPath();
            $targetFile = $path . DIRECTORY_SEPARATOR . "$rClassname.php";
            if (!file_exists($targetFile)) {
                $this->info("Resource for this model doesn't exists yet.");
                $prompt = confirm("Would you like to create Resource for this model?");
                if ($prompt) {
                    $this->call('carve:resource', [
                        'name' => $this->getNameInput()
                    ]);
                }

            }

        }
    }

    protected function getStub()
    {
        return __DIR__ . DIRECTORY_SEPARATOR . '/stubs/model.stub';
    }


    protected function createResource()
    {

        $filable = $this->createFillable();
        $scopeSearch = $this->createFullTextScopeSearch();
        if (empty($scopeSearch)) {
            $scopeSearch = $this->createPlainScopeSearch();
        }

        $classNameFromTable = $this->createQualifyClassName($this->tablename);
        $classNameFromInput = $this->getClassName();
        $fromTable = "";
        if ($classNameFromTable != $classNameFromInput) {
            $fromTable = "protected \$table = '$this->tablename';";
        }

        return [
            "{{namespace}}" => "App\Models",
            "{{classname}}" => $classNameFromInput,
            "{{oa_schema_name}}" => $classNameFromInput,
            "{{oa_schema_properties}}" => $this->buildOAProperties(),
            "{{general_rules}}" => $this->createValidationRule(),
            "{{softDelete}}" => $this->useSoftDeletes(),
            "{{tableNameDef}}" => $fromTable,
            "{{fillable}}" => $filable,
            "{{scopeSearch}}" => $scopeSearch
        ];
    }



    protected function afterPromptingForMissingArguments(InputInterface $input, OutputInterface $output)
    {
        parent::afterPromptingForMissingArguments($input, $output);
    }

    protected function promptForMissingArgumentsUsing()
    {
        return [
            'name' => [
                'Enter table name',
                ''
            ]
        ];
    }

    protected function useSoftDeletes()
    {
        $rvalue = "use \Illuminate\Database\Eloquent\SoftDeletes;";

        $columns = array_column($this->dbColums, "name");
        if (!in_array("deleted_at", $columns)) {
            $rvalue = "";
        }
        return $rvalue;

    }




    protected function createValidationRule()
    {
        $allRules = [];

        foreach ($this->dbColums as $column) {
            if (in_array($column["name"], ["created_at", "updated_at", "deleted_at"])) {
                continue;
            }

            if (!empty($column["auto_increment"])) {
                continue;
            }
            $rules = [];
            if (!empty($column["nullable"])) {
                $rules[] = '"nullable"';
            } else {
                $rules[] = '"required"';

            }
            if (!empty($column["max"]) && $column["type"] == "string") {
                $rules[] = "\"max:" . $column["max"] . "\"";
            }

            if (!empty($column["enum"])) {

            }


            $allRules[] = "\"" . $column["name"] . "\" => [" . implode(",", $rules) . "],";
        }

        return $allRules;
    }

    protected function createFillable()
    {
        $r = array_filter($this->dbColums, function ($item, $key) {

            if (!empty($item["auto_increment"])) {
                return false;
            }
            if (in_array($item["name"], ["created_at", "updated_at", "deleted_at"])) {
                return false;
            }
            return true;
        }, ARRAY_FILTER_USE_BOTH);
        $r = array_column($r, "name");
        $r = array_map(function ($item) {
            return "\"$item\",";
        }, $r);

        return $r;
        //return '"' . implode('",' . PHP_EOL . '"', array_column($r, "name")) . '"';
    }
    protected function getOptions()
    {
        return [
            ['force', null, InputOption::VALUE_NONE, 'Create the class even if the model already exists'],
        ];
    }

    protected function createCasts()
    {
        $r = array_filter($this->dbColums, function ($item, $key) {
            return in_array($item["type"], [
                "date",
                "datetime",
                ""
            ]);
        }, ARRAY_FILTER_USE_BOTH);
    }
    protected function getFullTextFields()
    {
        return $this->getFullTextField($this->getNameInput());
    }

    protected function createFullTextScopeSearch()
    {
        $fulltextFields = $this->getFullTextFields();

        if (empty($fulltextFields)) {
            return null;
        }
        $content = "\$query->whereFullText([$fulltextFields], \$search);";
        $lines = explode(PHP_EOL, file_get_contents(__DIR__ . DIRECTORY_SEPARATOR .
            '/stubs/model.scopeSearch.stub'));

        $r = ["{{filterQuery}}" => $content];

        $r = $this->blend($lines, $r);

        return $r;

    }
    protected function createPlainScopeSearch()
    {
        $r = array_filter($this->dbColums, function ($item, $key) {

            if (!empty($item["auto_increment"])) {
                return false;
            }
            if (in_array($item["name"], ["created_at", "updated_at", "deleted_at"])) {
                return false;
            }
            return true;
        }, ARRAY_FILTER_USE_BOTH);

        $r = array_column($r, "name");
        $firstLine = array_shift($r);
        $first2 = array_slice($r, 2);
        $lines = array_map(function ($item) {
            return "        ->orWhere('$item','like',\"'%\$search%'\")";
        }, $first2);



        $commentedLines = array_map(function ($item) {
            return "        //->orWhere('$item','like',\"'%\$search%'\")";
        }, $r);

        $lines = array_merge($lines, $commentedLines, ["        ;"]);
        array_unshift($lines, "    \$query->where('$firstLine','like',\"'%\$search%'\")");
        array_unshift($lines, "\$query->where(function (\$query) use (\$search) {");
        array_push($lines, "});");

        $contents = explode(PHP_EOL, file_get_contents(__DIR__ . DIRECTORY_SEPARATOR .
            '/stubs/model.scopeSearch.stub'));

        $resource = ["{{filterQuery}}" => $lines];
        $mixed = $this->blend($contents, $resource);


        return $mixed;
    }

}