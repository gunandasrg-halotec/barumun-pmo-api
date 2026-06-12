<?php

namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Symfony\Component\Console\Input\InputArgument;

use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\suggest;
use function Laravel\Prompts\text;
use function Laravel\Prompts\intro;
abstract class Carve extends Command implements PromptsForMissingInput
{

    public function __construct()
    {
        parent::__construct();

    }
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description;
    /**
     * Summary of type
     * @var  string
     */
    protected $type;
    /**
     * Summary of tablename
     * @var string
     */
    protected $tablename;
    private $className;

    private $warning = [];
    /**
     * Execute the console command.
     */
    protected $dbColums;
    public function handle()
    {
        intro("Creating $this->type");

        $this->dbColums = $this->getTableColumns($this->getNameInput());
        $this->setClassName($this->getNameInput());
        $this->tablename = $this->getNameInput();

        if (empty($this->dbColums)) {
            $this->warn("Table was not found on database!");
            $action = select("Please select your action", [
                "select" => "Select table name from database",
                "create" => "Create Empty  $this->type",
                "exit" => "Exit $this->type",

            ], "2", );

            switch ($action) {
                case "select":
                    $tableNames = $this->getTableNames();
                    $this->tablename = select("Please select table", $tableNames);
                    $this->dbColums = $this->getTableColumns($this->tablename);
                    break;
                case "create":
                    $this->dbColums = [];
                    $this->tablename = "";
                    break;
                case "exit";
                default:
                    $this->info("Exit...");
                    return static::INVALID;
            }
            $className = $this->getClassName();
            $className = text("What is the class name?", $className, $className, true);
            $this->setClassName($className);
        }

        $path = $this->getTargetPath();
        $className = $this->getClassName();
        $targetFile = $path . DIRECTORY_SEPARATOR . "$className.php";
        $contents = $this->readStub();

        if (file_exists($targetFile)) {
            if ($this->hasOption("force") && $this->option("force")) {
                $contents = $this->readExistingFile($targetFile);
                //continue;
            } else {
                $this->output->writeln([
                    "Target File already exists!",
                    "If you want to update the file, make sure to at least one of these tokens on target file ",
                    "---------------------------------",
                    "Target file: ",
                    "   $targetFile",
                    "tokens:"

                ]);
                $tokens = $this->getTemplateToken($contents);
                $this->showTokens($tokens);
                $this->output->writeln("---------------------------------", );
                $action = select("would you like to overwrite it?", ["yes" => "Yes", "no" => "No"], "yes");
                if ($action == "no") {
                    return false;
                }

                $contents = $this->readExistingFile($targetFile);
                //check if any token exists;
                $tokens = $this->getTemplateToken($contents);
                if (count($tokens) < 1) {
                    $this->warn("There is no token on the file! File was not changed.");
                    return static::INVALID;
                }

            }
        }


        $rtemplate = $this->createResource();

        $mixed = $this->blend($contents, $rtemplate);
        $str = implode(PHP_EOL, $mixed);


        //echo $str;

        file_put_contents($targetFile, $str);

        if (count($this->warning) > 0) {
            $this->warn("Warning !");
            foreach ($this->warning as $w) {
                $this->warn($w);
            }
        }
        $this->info(implode(" ", [$this->type, $this->getClassName(), "created", $targetFile]));
        outro("done");
        return static::SUCCESS;
    }
    /**
     * build file content 
     * @param array $contents
     * @param array $resources
     * @return array
     */
    protected function blend(array $contents, array $resources)
    {
        $result = [];
        $keys = array_keys($resources);
        foreach ($contents as $line) {
            $founds = [];
            foreach ($keys as $key) {
                $pos = strpos($line, $key);
                if ($pos !== false) {
                    $founds[$key] = $pos;
                }
            }
            if (count($founds) > 1) {
                foreach ($founds as $key => $pos) {
                    $value = $resources[$key];
                    if (is_array($value)) {
                        $this->warning[] = "tag $key was not replaced";
                        continue;
                    }
                    $line = str_replace($key, $value, $line);
                }
                $result[] = $line;
            } else if (count($founds) > 0) {
                $key = array_key_first($founds);
                $value = $resources[$key];
                if (is_array($value)) {
                    if (!empty($value)) {
                        foreach ($value as $v) {
                            if (strlen(ltrim($line)) > 0) {
                                $result[] = str_replace($key, $v, $line);
                            } else {
                                $result[] = str_repeat(" ", $pos) . $v;
                            }
                        }
                    }

                } else {
                    if (!empty($value)) {
                        $result[] = str_replace($key, $value, $line);
                    }

                }
            } else {
                $result[] = $line;
            }
        }

        return $result;
    }

    abstract protected function getStub();

    protected function readStub()
    {
        $content = explode(PHP_EOL, file_get_contents($this->getStub()));
        return $content;
    }
    protected function readExistingFile($targetFile)
    {
        $content = explode(PHP_EOL, file_get_contents($targetFile));
        return $content;
    }


    abstract protected function createResource();

    protected function getNameInput()
    {
        $name = trim($this->argument('name'));
        return $name;
    }
    protected function getClassName()
    {
        return $this->className;

    }
    protected function setClassName($name)
    {
        if (Str::endsWith($name, '.php')) {
            $name = Str::substr($name, 0, -4);
        }
        $name = $this->createQualifyClassName($name);
        $this->className = $name;
    }

    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'table name'],
        ];
    }
    protected function promptForMissingArgumentsUsing()
    {
        return [
            'name' => [
                'Enter Table Name',
                ''
            ]
        ];
    }


    protected function getTargetPath()
    {
        $path = "";
        switch ($this->type) {
            case "Model":
                $path .= "app" . DIRECTORY_SEPARATOR . "Models";
                break;
            case "Controller":
                $path .= "app" . DIRECTORY_SEPARATOR . "Http" . DIRECTORY_SEPARATOR . "Controllers";
                break;
            case "FormRequest":
                $path .= "app" . DIRECTORY_SEPARATOR . "Http" . DIRECTORY_SEPARATOR . "Requests";
                break;
            case "Resource":
                $path .= "app" . DIRECTORY_SEPARATOR . "Http" . DIRECTORY_SEPARATOR . "Resources";
                break;
        }

        return base_path($path);

    }

    protected function getTemplateToken($lines)
    {

        $lines = implode("", $lines);
        $pattern = "/\{\{\w+\}\}/";

        preg_match_all($pattern, $lines, $matches);

        return array_values(array_unique($matches[0]));



    }
    protected function showTokens($tokens)
    {

        foreach ($tokens as $token) {
            $this->line(str_repeat(" ", 4) . "- " . $token);
        }

    }

    protected function createQualifyClassName($name)
    {
        $name = $this->createClassName($name);
        switch ($this->type) {
            case "Model":
                break;
            case "Controller":
            case "Route":
                if (!Str::endsWith($name, "Controller")) {
                    $name .= "Controller";
                }
                break;
            case "Resource":
                if (!Str::endsWith($name, "Resource")) {
                    $name .= "Resource";
                }
                break;
        }
        return $name;
    }

    protected function createClassName($name)
    {
        return Str::studly(Str::singular($name));
    }

}
