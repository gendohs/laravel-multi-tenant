<?php

namespace EJLab\Laravel\MultiTenant\Commands\Migrate;

use App\Tenant;
use EJLab\Laravel\MultiTenant\DatabaseManager;
use DB;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\Console\Migrations\ResetCommand;
use Symfony\Component\Console\Input\InputOption;

class MigrateResetCommand extends ResetCommand
{
    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        if (! $this->confirmToProceed()) {
            return;
        }

        if ($this->input->getOption('tenant')) {

            $domain = $this->input->getOption('domain') ?: 'all';

            $manager = new DatabaseManager();
            DB::setDefaultConnection($manager->systemConnectionName);
            
            if ($domain == 'all') $tenants = Tenant::all();
            else $tenants = Tenant::where('domain', $domain)->get();

            $drawBar = (count($tenants) > 1);

            if ($drawBar) $bar = $this->output->createProgressBar(count($tenants));

            foreach ($tenants as $tenant) {
                
                $manager->setConnection($tenant);
                $this->migrator->setConnection($manager->tenantConnectionName);

                $this->info('');
                $this->info("Resetting migrations for '{$tenant->name}'...");

                if (! $this->migrator->repositoryExists()) {
                    if ($drawBar) $bar->advance();
                    $this->error(($drawBar?'  ':'')."No migrations found for '{$tenant->name}'.");
                    continue;
                }

                $this->migrator->reset(array_map(function ($path) {
                    return $path .= DIRECTORY_SEPARATOR.'tenant';
                }, $this->getMigrationPaths()), $this->option('pretend'));

                // Once the migrator has run we will grab the note output and send it out to
                // the console screen, since the migrator itself functions without having
                // any instances of the OutputInterface contract passed into the class.
                foreach ($this->migrator->getNotes() as $note) {
                    $this->output->writeln($note);
                }

                if ($drawBar) $bar->advance();
                $this->info(($drawBar?'  ':'')."'{$tenant->name}' reseted.");
            }
            if ($drawBar) $bar->finish();
        }
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array_merge(parent::getOptions(), [
            ['tenant', 'T', InputOption::VALUE_NONE, "Rollback all database migrations for tenant database. '--database' option will be ignored. use '--domain' instead."],
            ['domain', NULL, InputOption::VALUE_OPTIONAL, "The domain for tenant. 'all' or null value for all tenants."]
        ]);
    }
}
