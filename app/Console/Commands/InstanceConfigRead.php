<?php

namespace App\Console\Commands;


use App\Models\InstanceSetting;
use Illuminate\Console\Command;

class InstanceConfigRead extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'instance:read-config';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'read instance configuration';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $config = InstanceSetting::select("name", "value")->get();
        $this->table(["name", "value"], $config);
    }
}