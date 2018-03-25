<?php

namespace App\Commands;

use App\Services\Bitbucket\Bitbucket;
use App\Services\Bitbucket\Exceptions\InvalidVersion;
use Chumper\Zipper\Zipper;
use Illuminate\Support\Facades\File;
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

    private $version;

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
     * @throws InvalidVersion
     */
    public function handle(): void
    {
        $this->task('Determining project Version', function () {
            $this->version = $this->determineProjectVersion();
        });

        $createdDirectory = $this->task('Creating project directory', function () {
            return $this->createDirectory();
        });

        if (!$createdDirectory) {
            $this->error('Directory ' . $this->argument('name') . ' already exists');
            return;
        }

        $downloadedRepo = $this->task('Downloading ' . config('app.name'), function () {
            return $this->bitbucket->downloadRepo($this->version);
        });

        if (!$downloadedRepo) {
            $this->rollbackCreatedDirectory();
            $this->error('Something went wrong downloading your repository.');
            return;
        }

        $this->task('Extracting project files', function() {
            $this->extractProject();
        });

        $this->task('Moving project files', function() {
            $this->moveProjectFilesToTheRootDirectory();
        });

        $this->task('Initialise git', function() {
            $this->gitInit();
        });

        $this->task('Installing dependencies', function() {
            $this->composerInstall();
        });

        $this->task('Create environment file', function() {
            $this->createEnvFile();
        });

        $this->task('Generate application key', function() {
            $this->generateAppKey();
        });
    }

    private function createDirectory()
    {
        if (File::exists(install_path($this->argument('name')))) {
            return false;
        }

        return File::makeDirectory(install_path($this->argument('name')));
    }

    private function determineProjectVersion()
    {
        $version = $this->option('v');

        if (is_null($version)) {
            return $this->bitbucket->getLatestVersionName();
        }

        if (!$this->bitbucket->isValidVersion($version)) {
            throw new InvalidVersion('The specified version doesn\'t exist');
        }

        return $version;
    }

    private function extractProject()
    {
        $zipper = new Zipper();
        $zipper->make(install_path(config('bitbucket.repo') . '.zip'))->extractTo(install_path($this->argument('name')));
    }

    private function moveProjectFilesToTheRootDirectory()
    {
        $dir = File::directories(install_path($this->argument('name')))[0];
        File::copyDirectory($dir, install_path($this->argument('name')));
        File::deleteDirectory($dir);
    }

    private function gitInit()
    {
        exec('cd ' . $this->argument('name') . ' && git init && git add . && git commit -m="Add mixer"');
    }

    private function composerInstall()
    {
        exec('cd ' . $this->argument('name') . ' && composer install 2> /dev/null', $output, $return);
    }

    private function createEnvFile()
    {
        File::copy(install_path($this->argument('name') . '/.env.example'), install_path($this->argument('name') . '/.env'));
    }

    private function generateAppKey()
    {
        exec('cd ' . $this->argument('name') . ' && php artisan key:generate');
    }

    private function rollbackCreatedDirectory()
    {
        File::deleteDirectory(install_path($this->argument('name')));
    }
}
