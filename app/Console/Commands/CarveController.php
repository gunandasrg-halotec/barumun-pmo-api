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


#[AsCommand(name: 'carve:controller')]
class CarveController extends Carve
{
    use CreatesMatchingTest, SchemaTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'carve:controller';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected $type = "Controller";

    private $classname;
    private $tag;
    private $modelName;
    private $modelClassname;


    /**
     * Execute the console command.
     */
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
        return __DIR__ . DIRECTORY_SEPARATOR . '/stubs/controller.stub';
    }

    protected function getIndexStub()
    {
        return __DIR__ . DIRECTORY_SEPARATOR . '/stubs/controller.index.stub';
    }
    protected function getShowStub()
    {
        return __DIR__ . DIRECTORY_SEPARATOR . '/stubs/controller.show.stub';
    }
    protected function getStoreStub()
    {
        return __DIR__ . DIRECTORY_SEPARATOR . '/stubs/controller.store.stub';
    }
    protected function getUpdateStub()
    {
        return __DIR__ . DIRECTORY_SEPARATOR . '/stubs/controller.update.stub';
    }
    protected function getDeleteStub()
    {
        return __DIR__ . DIRECTORY_SEPARATOR . '/stubs/controller.destroy.stub';
    }

    protected function readStub()
    {
        $content = explode(PHP_EOL, file_get_contents($this->getStub()));
        return $content;
    }



    protected function createResource()
    {
        $this->classname = $this->getClassName();
        $this->tag = Str::title($this->getNameInput());
        $this->modelName = Str::singular($this->getNameInput());
        $this->modelClassname = $this->createClassName($this->modelName);
        $uses = [
            "use \\App\\Models\\$this->modelClassname;",
            "use \\App\\Http\\Resources\\{$this->modelClassname}Resource;",
            "use \\App\\Http\\Requests\\{$this->modelClassname}Request;",
        ];
        return [
            "{{namespace}}" => "App\Http\Controllers",
            "{{uses}}" => $uses,
            "{{classname}}" => $this->classname,
            "{{index}}" => $this->makeIndexSection(),
            "{{show}}" => $this->makeShowSection(),
            "{{store}}" => $this->makeStoreSection(),
            "{{update}}" => $this->makeUpdateSection(),
            "{{destroy}}" => $this->makeDestroySection(),
        ];
    }

    protected function makeIndexSection()
    {
        $content = explode(PHP_EOL, file_get_contents($this->getIndexStub()));
        $result = $this->blend($content, [
            "{{classname}}" => $this->classname,
            "{{tag}}" => $this->tag,
            "{{model}}" => Str::replace("_","-", $this->modelName),
            "{{Modelclassname}}" => $this->modelClassname,
        ]);
        //        print_r($result);exit;
        return $result;
    }

    protected function makeShowSection()
    {
        $content = explode(PHP_EOL, file_get_contents($this->getShowStub()));
        $result = $this->blend($content, [
            "{{classname}}" => $this->classname,
            "{{tag}}" => $this->tag,
            "{{model}}" => $this->modelName,
            "{{Modelclassname}}" => $this->modelClassname,

        ]);
        //        print_r($result);exit;
        return $result;
    }
    protected function makeStoreSection()
    {
        $content = explode(PHP_EOL, file_get_contents($this->getStoreStub()));
        $result = $this->blend($content, [
            "{{classname}}" => $this->classname,
            "{{tag}}" => $this->tag,
            "{{model}}" => $this->modelName,
            "{{Modelclassname}}" => $this->modelClassname,

        ]);
        //        print_r($result);exit;
        return $result;
    }
    protected function makeUpdateSection()
    {
        $content = explode(PHP_EOL, file_get_contents($this->getUpdateStub()));
        $result = $this->blend($content, [
            "{{classname}}" => $this->classname,
            "{{tag}}" => $this->tag,
            "{{model}}" => $this->modelName,
            "{{Modelclassname}}" => $this->modelClassname,

        ]);
        //        print_r($result);exit;
        return $result;
    }

    protected function makeDestroySection()
    {
        $content = explode(PHP_EOL, file_get_contents($this->getDeleteStub()));
        $result = $this->blend($content, [
            "{{classname}}" => $this->classname,
            "{{tag}}" => $this->tag,
            "{{model}}" => $this->modelName,
            "{{Modelclassname}}" => $this->modelClassname,

        ]);
        //        print_r($result);exit;
        return $result;

    }

}
