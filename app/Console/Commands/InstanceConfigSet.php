<?php

namespace App\Console\Commands;


use App\Models\ApiKey;
use App\Models\InstanceSetting;
use Illuminate\Console\Command;

class InstanceConfigSet extends Command
{
    public const string MAIN_SERVER_NAME = "main";
    public const string REMOTE_SERVER_NAME = "backup";
    public const string INSTANCE_NAME = "instance_name";
    public const string REMOTE_API_URL = 'backup_api_url';
    public const string REMOTE_DB_HOST = "backup_database_host";
    public const string REMOTE_DB_PORT = "backup_database_port";

    public const string REMOTE_DB_DATABASE = "backup_database_name";
    public const string REMOTE_DB_USERNAME = "backup_database_username";
    public const string REMOTE_DB_PASSWORD = "backup_database_password";

    public const string CCTV_SERVER_URL = "cctv_server_url";




    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'instance:set-config';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'set instance configuration';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $inputValue = $this->confirm("Set this instance As Main server?", true);
        if ($inputValue) {
            InstanceSetting::updateOrCreate(
                ["name" => self::INSTANCE_NAME],
                ["name" => self::INSTANCE_NAME, "value" => self::MAIN_SERVER_NAME]
            );

            $default = InstanceSetting::where("name", "=", self::REMOTE_API_URL)->first();

            $inputValue = $this->ask("Backup Api Url", $default ? $default->value : "");
            InstanceSetting::updateOrCreate(
                ["name" => self::REMOTE_API_URL],
                ["name" => self::REMOTE_API_URL, "value" => $inputValue]
            );

            $default = InstanceSetting::where("name", "=", self::REMOTE_DB_HOST)->first();
            $inputValue = $this->ask("Backup Database host", $default ? $default->value : "host.docker.internal");

            InstanceSetting::updateOrCreate(
                ["name" => self::REMOTE_DB_HOST],
                ["name" => self::REMOTE_DB_HOST, "value" => $inputValue]
            );
            $default = InstanceSetting::where("name", "=", self::REMOTE_DB_PORT)->first();
            $inputValue = $this->ask("Backup Database port", $default ? $default->value : "3306");
            InstanceSetting::updateOrCreate(
                ["name" => self::REMOTE_DB_PORT],
                ["name" => self::REMOTE_DB_PORT, "value" => $inputValue]
            );

            $default = InstanceSetting::where("name", "=", self::REMOTE_DB_USERNAME)->first();
            $inputValue = $this->ask("Backup Database username", $default ? $default->value : "root");
            InstanceSetting::updateOrCreate(
                ["name" => self::REMOTE_DB_USERNAME],
                ["name" => self::REMOTE_DB_USERNAME, "value" => $inputValue]
            );
            $default = InstanceSetting::where("name", "=", self::REMOTE_DB_PASSWORD)->first();
            $inputValue = $this->ask("Backup Database password", $default ? $default->value : "halotec123");
            InstanceSetting::updateOrCreate(
                ["name" => self::REMOTE_DB_PASSWORD],
                ["name" => self::REMOTE_DB_PASSWORD, "value" => $inputValue]
            );
            $default = InstanceSetting::where("name", "=", self::REMOTE_DB_DATABASE)->first();
            $inputValue = $this->ask("Backup Database name", $default ? $default->value : "autogate_grobogan");
            InstanceSetting::updateOrCreate(
                ["name" => self::REMOTE_DB_DATABASE],
                ["name" => self::REMOTE_DB_DATABASE, "value" => $inputValue]
            );
            

            $inputValue = $this->ask("Please enter the api-key", "b6d17bf8-42f8-47cc-ae89-ed008bf3a2a2");
            ApiKey::updateOrCreate([
                "id" => $inputValue
            ], [
                "description" => "Api Key",
                "id" => $inputValue
            ]);


        } else {
            InstanceSetting::updateOrCreate(
                ["name" => self::INSTANCE_NAME],
                ["name" => self::INSTANCE_NAME, "value" => self::REMOTE_SERVER_NAME]
            );
            foreach ([
                self::REMOTE_API_URL,
                self::REMOTE_DB_DATABASE,
                self::REMOTE_DB_HOST,
                self::REMOTE_DB_PASSWORD,
                self::REMOTE_DB_PORT
            ] as $key) {
                $setting = InstanceSetting::where("name", "=", $key)->first();
                if ($setting) {
                    $setting->delete();
                }
            }


        }

        $default = InstanceSetting::where("name", "=", self::CCTV_SERVER_URL)->first();
        $inputValue = $this->ask("CCTV Server Url", $default ? $default->value : "http://192.168.100.29:8063");
        InstanceSetting::updateOrCreate(
            ["name" => self::CCTV_SERVER_URL],
            ["name" => self::CCTV_SERVER_URL, "value" => $inputValue]
        );


    }
}