<?php

namespace App\Commands;

use App\Services\Bitbucket\Bitbucket;
use App\Services\Bitbucket\Exceptions\InvalidVersion;
use Chumper\Zipper\Zipper;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use LaravelZero\Framework\Commands\Command;

class Create extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'create 
        {name : The name of the project}
        {--v= : The version of Mixer you want to use}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Create a new project build on Mixer';

    /**
     * @var Bitbucket
     */
    private $bitbucket;

    /**
     * Create constructor.
     * @param Bitbucket $bitbucket
     */
    public function __construct(Bitbucket $bitbucket)
    {
        parent::__construct();

        $this->bitbucket = $bitbucket;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $version = $this->determineProjectVersion();

        $this->line("Using version $version");

        if(! $this->createDirectory()) {
            $this->error("Directory " . $this->argument('name') . " already exists");
            return;
        }

        $this->line('Created project directory');

        if(!$this->bitbucket->downloadRepo($version)) {
            $this->error('Something went wrong downloading your repository.');
            return;
        }

        $this->line('Downloaded project');

        // Unzip zip in directory
        $zipper = new Zipper();
        $zipper->make(storage_path(config('bitbucket.repo').'.zip'))->extractTo(storage_path('app/'.$this->argument('name')));

        $this->line('Extracted project');

        $dir = File::directories(storage_path('app/'.$this->argument('name')))[0];
        File::copyDirectory($dir, storage_path('app/'.$this->argument('name')));
        File::deleteDirectory($dir);

        // Git init, add, commit
        exec('cd storage/app/' . $this->argument('name') . ' && git init && git add . && git commit -m="Add mixer"');

        // composer install
        exec('cd storage/app/'.$this->argument('name').' && composer install');

        // cat .env.example > .env
        File::copy(storage_path('app/'.$this->argument('name').'/.env.example'), storage_path('app/'.$this->argument('name').'/.env'));

        // pa key:generate
        exec('cd storage/app/'.$this->argument('name').' && php artisan key:generate');

        exec('mv storage/app/'.$this->argument('name') . ' ' . $this->argument('name'));
    }

    private function createDirectory()
    {
        if (File::exists(storage_path('app/'.$this->argument('name')))) {
            return false;
        }

        return File::makeDirectory(storage_path('app/'.$this->argument('name')));
    }

    private function determineProjectVersion()
    {
        $version = $this->option('v');

        if(is_null($version)) {
            return $this->bitbucket->getLatestVersionName();
        }

        if(! $this->bitbucket->isValidVersion($version)) {
            throw new InvalidVersion('The specified version doesn\t exist');
        }

        return $version;
    }
}
