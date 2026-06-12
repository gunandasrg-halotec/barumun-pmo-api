<?php

namespace App\Console\Commands;

use Illuminate\Console\Concerns\CreatesMatchingTest;


use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;



#[AsCommand(name: 'carve:resource')]
class CarveResource extends Carve
{
    use CreatesMatchingTest, SchemaTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'carve:resource';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create resource with openApi annotation';
    protected $type = 'Resource';

    protected $dbColums = [];

    protected function getStub()
    {
        return __DIR__ . DIRECTORY_SEPARATOR . '/stubs/model.resource.stub';
    }


    protected function createResource()
    {


        $classNameFromInput = $this->getClassName();

        return [
            "{{namespace}}" => "App\Http\Resources",
            "{{ModelResource_classname}}" => $classNameFromInput,
            "{{ModelResource_properties}}" => $this->buildOAProperties(),
            "{{ModelResource_return_values}}" => $this->buildReturnValues(),

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

    protected function buildReturnValues()
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
        $rvalue = array_map(function ($item) {
            return "\"$item\" => \$this->$item,";

        }, $r);
        return $rvalue;
    }


    protected function getOptions()
    {
        return [
            ['force', null, InputOption::VALUE_NONE, 'Create the class even if the resource already exists'],
        ];
    }

}