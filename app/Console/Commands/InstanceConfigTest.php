<?php

namespace App\Console\Commands;


use App\Models\InstanceSetting;
use DB;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class InstanceConfigTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'instance:test-config';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'test the configuration';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info("Testing Connection to Backup server");

        try {
            DB::connection(BACKUP_SERVER)->getPdo();
            $this->info("   Testing Backup database: OK");
        } catch (\Throwable $th) {
            $this->error("   Testing Backup database: Fail, " . $th->getMessage());
        }
        $this->info("Testing Backup Server Api Connection");
        try {
            //code...
            $response = Http::get(config("app.remote_api_url"));
            $msg = "   Response status: " . $response->status();

            if ($response->status() != 200) {
                $this->error($msg);
            } else {
                $this->info($msg);
            }
        } catch (\Throwable $th) {
            $this->error("   " . $th->getMessage());
        }




    }
}