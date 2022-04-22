<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Store;
use Spatie\DbDumper\Databases\PostgreSql;
use Spatie\DbDumper\Databases\MySql;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class BackupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db-backup {--drive=} {--host=} {--user=} {--password=} {--port=} {--database=} {--name=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backup Database';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        ini_set("memory_limit", -1);
        try {
            $dbDrive = 'pgsql';
            $dbHost = 'localhost';
            $dbPort = 5432;
            $dbUser = 'postgres';
            $dbPassword = 'postgres';
            $dbName = 'postgres';
            if ($this->option('drive')) {
                $dbDrive = $this->option('drive');
            }

            if ($dbDrive == 'pgsql') {
                $dumpCommand = PostgreSql::create();
            }
            if ($dbDrive == 'mysql') {
                $dumpCommand = MySql::create();
            }


            if ($this->option('host')) {
                $dbHost = $this->option('host');
            }

            if ($this->option('user')) {
                $dbUser = $this->option('user');
            }

            if ($this->option('password')) {
                $dbPassword = $this->option('password');
            }

            if ($this->option('port')) {
                $dbPort = $this->option('port');
            }

            if ($this->option('database')) {
                $dbName = $this->option('database');
                $name = $this->option('database');
            }

            if ($this->option('name')) {
                $name = $this->option('name');
            }

            $this->info('The backup has been started');
            $dateBefore = date("Y-m-d", time() - (3600 * 24 * 5));
            $date = date("Y-m-d");
            if (!file_exists(storage_path($name)))
                mkdir(storage_path($name));
            $backup_name = $name . "/" . $date . ".sql";
            $backup_path = storage_path($backup_name);
            $dumpCommand->setDbName($dbName)
                ->setPort($dbPort)
                ->setUserName($dbUser)
                ->setPassword($dbPassword)
                ->setHost($dbHost)
                ->setDbName($dbName);
            $dumpCommand->dumpToFile($backup_path);
            Storage::disk('s3')->put($backup_name, fopen($backup_path, 'r+'));

            if (file_exists($backup_path)) {
                unlink($backup_path);
            }

            if (Storage::disk('s3')->exists($name . "/" . $dateBefore . ".sql")) {
                Storage::disk('s3')->delete($name . "/" . $dateBefore . ".sql");
            }
            $this->info('The backup has been proceed successfully.');
        } catch (ProcessFailedException $exception) {
            logger()->error('Backup exception', compact('exception'));
            // $this->error('The backup process has been failed.');
        }
        $this->info('The command was successful!');
        return 0;
    }
}
