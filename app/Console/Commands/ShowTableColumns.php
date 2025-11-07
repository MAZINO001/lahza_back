<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ShowTableColumns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
protected $signature = 'db:columns';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lahza: Show all database tables and their columns';

    /**
     * Execute the console command.
     */
    public function handle()
    {
         $tables = DB::select('SHOW TABLES');
        $key = array_key_first((array)$tables[0]);

        foreach ($tables as $table) {
            $tableName = $table->$key;
            $this->info("📦 $tableName");
            $columns = Schema::getColumnListing($tableName);
            foreach ($columns as $column) {
                $this->line("   - $column");
            }
            $this->newLine();
        }

        return 0;
    }
}
