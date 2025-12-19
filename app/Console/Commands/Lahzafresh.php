<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class Lahzafresh extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:lahzafresh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'refresh and seed ';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Refreshing and seeding the database...');
        
        $this->call('migrate:fresh');
        $this->info('Database refreshed successfully!');
        
        $this->info('Seeding the database...');
        $this->call('db:seed');
        $this->info('Database seeded successfully!');
        
        $this->info('Database refreshed and seeded successfully!');
    }
}
