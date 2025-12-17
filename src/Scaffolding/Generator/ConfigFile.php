<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace Moodle\Composer\Plugin\Scaffold\Scaffolding\Generator;

use Composer\Util\Filesystem;

/**
 * Moodle Config File Generator.
 */
class ConfigFile extends BaseGenerator
{
    /** @var array Database configuration */
    protected array $databaseconfig = [];

    /** @var string The wwwroot URL */
    protected string $wwwroot = '';

    /** @var string The dataroot path */
    protected string $dataroot = '';

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function generate(): void
    {
        $this->io->write("- <info>Generating Moodle configuration file...</info>", false);
        file_put_contents($this->getConfigFilePath(), $this->getTemplateContent());
        $this->io->write("<info> done.</info>");
    }

    /**
     * Get the path to the Moodle configuration file.
     *
     * @return string
     */
    protected function getConfigFilePath(): string
    {
        return $this->getRootPackagePath() . '/config.php';
    }

    /**
     * Check if the config file already exists.
     *
     * @return bool
     */
    public function checkFileExists(): bool
    {
        return file_exists($this->getConfigFilePath());
    }

    /**
     * Set the database config.
     *
     * @param string $dbtype
     * @param string $dbhost
     * @param string $dbname
     * @param string $dbuser
     * @param string $dbpass
     * @param string $prefix
     * @return self
     */
    public function setDatabaseConfig(
        string $dbtype,
        string $dbhost,
        string $dbname,
        string $dbuser,
        string $dbpass,
        string $prefix = 'mdl_',
    ): static {
        $this->databaseconfig = [
            'dbtype' => $dbtype,
            'dbhost' => $dbhost,
            'dbname' => $dbname,
            'dbuser' => $dbuser,
            'dbpass' => $dbpass,
            'prefix' => $prefix,
        ];

        return $this;
    }

    /**
     * Set the site config.
     *
     * @param string $wwwroot
     * @param string $dataroot
     * @return self
     */
    public function setSiteConfig(
        string $wwwroot,
        string $dataroot,
    ): static {
        $this->wwwroot = $wwwroot;
        $this->dataroot = (new Filesystem())->normalizePath($dataroot);

        return $this;
    }

    protected function getTemplateContent(): string
    {
        return <<<TEMPLATE
        <?php

        /**
         * This is the Moodle configuration file.
         *
         * For documentation see https://docs.moodle.org/en/Configuration_file
         */

        unset(\$CFG);
        global \$CFG;
        \$CFG = new stdClass();

        \$CFG->dbtype    = '{$this->databaseconfig['dbtype']}';
        \$CFG->dblibrary = 'native';
        \$CFG->dbhost    = '{$this->databaseconfig['dbhost']}';
        \$CFG->dbname    = '{$this->databaseconfig['dbname']}';
        \$CFG->dbuser    = '{$this->databaseconfig['dbuser']}';
        \$CFG->dbpass    = '{$this->databaseconfig['dbpass']}';
        \$CFG->prefix    = '{$this->databaseconfig['prefix']}';

        \$CFG->dboptions = array (
          'dbpersist' => 0,
          'dbport' => '',
          'dbsocket' => '',
        );

        \$CFG->wwwroot   = '{$this->wwwroot}';
        \$CFG->dataroot  = '{$this->dataroot}';

        // Note: Do *not* include setup.php here.
        // For Composer-based installations, it is included by the shim config.php file.

        TEMPLATE;
    }

    /**
     * Generate the Moodle configuration file.
     *
     * @return void
     */
    public function generateConfigurationFile(): void
    {
        $this->io->write('Generating Moodle configuration file...');

        if (!$this->io->isInteractive()) {
            $this->io->write('<error>Non-interactive mode detected. Skipping configuration file generation to avoid incomplete setup.</error>');
            return;
        }

        if ($this->checkFileExists()) {
            $this->io->write('<warning>Configuration file already exists. Aborting to prevent overwriting existing configuration.</warning>');
            $overwrite = $this->io->askConfirmation(
                '<question>Do you want to overwrite the existing configuration file? (y/N) </question>',
                 false
            );

            if ($overwrite) {
                $this->io->write('<warning>Overwriting existing configuration file as per user request.</warning>');
            } else {
                $this->io->write('<error>Aborting configuration file generation.</error>');
                return;
            }
        }

        // Ask for site details (site name, admin user, password, etc).
        $dbdriver = $this->getDatabaseDriver();

        $dbuser = $this->getDatabaseUsername();
        $dbpass = $this->getDatabasePassword();
        $dbname = $this->getDatabaseName();
        $dbhost = $this->getDatabaseHost();
        $dbprefix = $this->getDatabasePrefix();

        $wwwroot = $this->getWwwRoot();
        $dataroot = $this->io->ask('Enter the Moodle data directory path (default: moodledata): ', 'moodledata');

        $this->setDatabaseConfig(
            dbtype: $dbdriver,
            dbuser: $dbuser,
            dbpass: $dbpass,
            dbname: $dbname,
            dbhost: $dbhost,
            prefix: $dbprefix,
        );

        $this->setSiteConfig(
            wwwroot: $wwwroot,
            dataroot: realpath($dataroot),
        );

        if (!file_exists($dataroot)) {
            mkdir($dataroot, 0770, true);
            $this->io->write("Created dataroot directory at: {$dataroot}");
        }

        $this->generate();
        $this->io->write('Moodle configuration file generated successfully.');
    }

    protected function getDatabaseDriver(): string
    {
        if ($_ENV['MOODLE_DB_DRIVER'] ?? '' !== '') {
            return $_ENV['MOODLE_DB_DRIVER'];
        }

        // Ask for site details (site name, admin user, password, etc).
        return $this->io->select(
            'What database driver are you using?',
            [
                'mariadb' => 'MariaDB (mariadb)',
                'mysqli' => 'MySQL Improved (mysqli)',
                'pgsql' => 'PostgreSQL (pgsql)',
                'sqlsrv' => 'Microsoft SQL Server (sqlsrv)',
                'auroramysql' => 'Amazon Aurora MySQL (auroramysql)',
            ],
            'pgsql',
        );
    }

    protected function getDatabaseUsername(): string
    {
        if ($_ENV['MOODLE_DB_USERNAME'] ?? '' !== '') {
            return $_ENV['MOODLE_DB_USERNAME'];
        }

        return $this->io->askAndHideAnswer('Enter the database username: ') ?? '';
    }

    protected function getDatabasePassword(): string
    {
        if ($_ENV['MOODLE_DB_PASSWORD'] ?? '' !== '') {
            return $_ENV['MOODLE_DB_PASSWORD'];
        }

        return $this->io->askAndHideAnswer('Enter the database password: ') ?? '';
    }

    protected function getDatabaseName(): string
    {
        $default = $this->getBaseDirName();

        return $this->io->askAndValidate(
            "Enter the database name (default {$default}): ",
            function ($answer)  {
                if (empty($answer)) {
                    throw new \RuntimeException('Database name cannot be empty.');
                }
                return $answer;
            },
            null,
            $default,
        );
    }

    protected function getDatabaseHost(): string
    {
        if ($_ENV['MOODLE_DB_HOST'] ?? '' !== '') {
            return $_ENV['MOODLE_DB_HOST'];
        }

        return $this->io->ask('Enter the database host (default: localhost): ', 'localhost');
    }

    protected function getDatabasePrefix(): string
    {
        if ($_ENV['MOODLE_DB_PREFIX'] ?? '' !== '') {
            return $_ENV['MOODLE_DB_PREFIX'];
        }

        return $this->io->ask('Enter the database table prefix (default: mdl_): ', 'mdl_');
    }

    protected function getWwwRoot(): string
    {
        if ($_ENV['MOODLE_WWWROOT'] ?? '' !== '') {
            $wwwroot = rtrim($_ENV['MOODLE_WWWROOT'], '/');

            $wwwroot = str_replace('[NAME]', $this->getBaseDirName(), $wwwroot);

            return $wwwroot;
        }

        return $this->io->askAndValidate(
            'Enter the web root URL (for example, https://moodle.example.com): ',
            function ($answer) {
                if (empty($answer) || !filter_var($answer, FILTER_VALIDATE_URL)) {
                    throw new \RuntimeException('Please enter a valid URL for the web root.');
                }
                return rtrim($answer, '/');
            },
        );
    }
}
