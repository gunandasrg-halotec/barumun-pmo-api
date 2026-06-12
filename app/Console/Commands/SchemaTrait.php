<?php
namespace App\Console\Commands;
use Illuminate\Support\Facades\Schema;
use DB;
use function PHPUnit\Framework\isArray;
use function PHPUnit\Framework\returnArgument;
use function Psy\debug;
trait SchemaTrait
{
    public function getTableColumns(string $table)
    {

        $name = trim($table);
        if (!preg_match("/^[_a-zA-Z0-9]+$/", $name)) {
            $this->warn("Not a valid database table name!");
            return [];
        }

        if (!Schema::hasTable($table)) {
            return [];
        }
        $columns = Schema::getColumns($table);

        //dd($columns);
        $return = array_map(function ($item) {

            $facadeType = null;
            $oaType = null;
            $oaformat = null;
            $columnType = strtolower($item["type_name"]);
            $enum = null;
            $isJson = false;


            $type = strtolower($item["type"]);

            $columnLength = "";
            switch ($columnType) {

                case 'tinyint':
                case 'boolean':
                case 'bool':
                    $facadeType = 'boolean';
                    $oaType = $facadeType;
                    break;
                case 'int':
                case 'bigint':
                    $facadeType = 'integer';
                    $oaType = $facadeType;
                    $columnLength = "";
                    break;
                case 'char':
                case 'varchar':
                case 'text':
                case 'tinytext':
                case 'mediumtext':
                    $columnLength = preg_replace("/[^0-9]/", "", $type);
                    $facadeType = "string";
                    $oaType = $facadeType;
                    break;
                case 'longtext':
                    $facadeType = "string";
                    $oaType = $facadeType;
                    $isJson = true;
                case 'float':
                case 'double':
                    $facadeType = "float";
                    $oaType = "number";
                    break;
                case 'datetime':
                case 'timestamp':
                    $facadeType = "datetime";
                    $oaType = "string";
                    $oaformat = "datetime";
                    break;
                case 'date':
                    $facadeType = "date";
                    $oaType = "string";
                    break;
                case "enum":
                    $facadeType = "string";
                    $oaType = "string";
                    $enum = str_replace(["enum", "(", ")"], ["", "[", "]"], $item["type"]);

            }
            return [
                "name" => $item["name"],
                "type" => $facadeType,
                "oatype" => $oaType,
                "oaformat" => $oaformat,
                "nullable" => $item["nullable"] == 1,
                "auto_increment" => $item["auto_increment"],
                "max" => $columnLength,
                "enum" => $enum,
                "isJson" => $isJson
            ];

        }, $columns);

        return $return;

    }
    public function getFullTextField($dbTablename)
    {
        $dbSchema = DB::connection()->getDatabaseName();
        $query = "select distinct column_name as field " .
            "from information_schema.STATISTICS " .
            "where table_schema = ? " .
            "and table_name = ? " .
            "and index_type = 'FULLTEXT'";

        $result = DB::select($query, [$dbSchema, $dbTablename]);

        $rvalue = [];
        foreach ($result as $r) {
            $rvalue[] = '"' . $r->field . '"';
        }
        return implode(",", $rvalue);
    }

    protected function buildOAProperties()
    {

        $rvalue = [];
        foreach ($this->dbColums as $c) {
            $attribs = [];

            $attribs[] = sprintf("property: \"%s\"", $c["name"]);
            $attribs[] = sprintf("type: \"%s\"", $c["type"]);
            if (!empty($c["max"])) {
                $attribs[] = sprintf("maxLength: %s", $c["max"]);
            }
            if (!empty($c["nullable"])) {
                $attribs[] = "nullable: true";
            }
            if (!empty($c["oaformat"])) {
                $attribs[] = sprintf("format: \"%s\"", $c["oaformat"]);
            }
            if (!empty($c["auto_increment"])) {
                $attribs[] = "readOnly: true";
            }
            if (!empty($c["enum"])) {
                $attribs[] = "enum: " . $c["enum"];
            }
            $strAttribs = implode(", ", $attribs);
            $shouldntShow = in_array($c["name"], ["created_at", "updated_at", "deleted_at"]);
            $comment = $shouldntShow ? "//" : "";
            $rvalue[] = "$comment new OA\Property($strAttribs),";
        }
        return $rvalue;
    }

    public function getTableNames()
    {
        $tables = array_values(array_column(Schema::getTables(), "name"));
        $laravelTables = ["cache", "cache_locks", "failed_jobs", "jobs", "job_batches", "migrations", "sessions"];
        $rvalue = array_diff($tables, $laravelTables);

        return array_values($rvalue);
    }
}