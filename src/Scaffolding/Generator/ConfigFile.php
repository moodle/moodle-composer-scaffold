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

namespace Moodle\Composer\Scaffold\Scaffolding\Generator;

use Composer\Util\Filesystem;

/**
 * Moodle Config File Generator.
 */
class ConfigFile extends BaseGenerator
{
    /** @var string The database type */
    protected string $dbtype = '';

    /** @var string The database host */
    protected string $dbhost = '';

    /** @var string The database name */
    protected string $dbname = '';

    /** @var string The database user */
    protected string $dbuser = '';

    /** @var string The database password */
    protected string $dbpass = '';

    /** @var string The database table prefix */
    protected string $prefix = 'mdl_';

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

        \$CFG->dbtype    = '{$this->dbtype}';
        \$CFG->dblibrary = 'native';
        \$CFG->dbhost    = '{$this->dbhost}';
        \$CFG->dbname    = '{$this->dbname}';
        \$CFG->dbuser    = '{$this->dbuser}';
        \$CFG->dbpass    = '{$this->dbpass}';
        \$CFG->prefix    = '{$this->prefix}';

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

        // Database credentials.
        $this->dbtype = $this->getDatabaseDriver();
        $this->dbuser = $this->getDatabaseUsername();
        $this->dbpass = $this->getDatabasePassword();
        $this->dbname = $this->getDatabaseName();
        $this->dbhost = $this->getDatabaseHost();
        $this->prefix = $this->getDatabasePrefix();

        // Site configuration.
        $this->wwwroot = $this->getWwwRoot();
        $this->dataroot = $this->getDataRoot();

        // Generate the config file.
        $this->generate();
        $this->io->write('Moodle configuration file generated successfully.');
    }

    protected function getDatabaseDriver(): string
    {
        $driver = $this->getEnv('MOODLE_DB_DRIVER');
        if ($driver === null) {
            /** @var string */
            $driver = $this->io->select(
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

        return $driver;
    }

    protected function getDatabaseUsername(): string
    {
        $username = $this->getEnv('MOODLE_DB_USERNAME');
        if ($username === null) {
            /** @var string */
            $username = $this->io->ask('Enter the database username: ') ?? '';
        }

        return $username;
    }

    protected function getDatabasePassword(): string
    {
        $password = $this->getEnv('MOODLE_DB_PASSWORD');
        if ($password === null) {
            /** @var string */
            $password = $this->io->askAndHideAnswer('Enter the database password: ') ?? '';
        }

        return $password;
    }

    protected function getDatabaseName(): string
    {
        $default = $this->getBaseDirName();

        $dbname = $this->getEnv('MOODLE_DB_NAME');
        if ($dbname === null) {
            /** @var string */
            $dbname = $this->io->askAndValidate(
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

        return $dbname;
    }

    protected function getDatabaseHost(): string
    {
        $host = $this->getEnv('MOODLE_DB_HOST');
        if ($host === null) {
            /** @var string */
            $host = $this->io->ask('Enter the database host (default: localhost): ', 'localhost');
        }

        return $host;
    }

    protected function getDatabasePrefix(): string
    {
        $prefix = $this->getEnv('MOODLE_DB_PREFIX');
        if ($prefix === null) {
            /** @var string */
            $prefix = $this->io->ask('Enter the database table prefix (default: mdl_): ', 'mdl_');
        }

        return $prefix;
    }

    protected function getWwwRoot(): string
    {
        $wwwroot = $this->getEnv('MOODLE_WWWROOT');
        if ($wwwroot === null) {
            /** @var string */
            $wwwroot = $this->io->askAndValidate(
                'Enter the web root URL (for example, https://moodle.example.com): ',
                function ($answer) {
                    if (empty($answer) || !filter_var($answer, FILTER_VALIDATE_URL)) {
                        throw new \RuntimeException('Please enter a valid URL for the web root.');
                    }
                    return rtrim($answer, '/');
                },
            );
        }

        $wwwroot = rtrim($wwwroot, '/');
        $wwwroot = str_replace('[NAME]', $this->getBaseDirName(), $wwwroot);

        return $wwwroot;
    }

    protected function getDataRoot(): string
    {
        $dataroot = $this->getEnv('MOODLE_DATAROOT');
        if ($dataroot === null) {
            /** @var string */
            $dataroot = $this->io->ask(
                'Enter the Moodle data directory path (default: data): ',
                'data'
            );
        }
        $dataroot = rtrim($dataroot, '/');
        $dataroot = str_replace('[NAME]', $this->getBaseDirName(), $dataroot);


        $filesystem = new Filesystem();
        if ($filesystem->isAbsolutePath($dataroot) === false) {
            $dataroot = $this->getRootPackagePath() . '/' . $dataroot;
        }
        $dataroot = $filesystem->normalizePath($dataroot);

        $filesystem->ensureDirectoryExists($dataroot);
        /** @var int */
        $permissions = octdec('2777');
        chmod($dataroot, $permissions);

        return $dataroot;
    }

    /**
     * Fetch environment var as a string.
     */
    protected function getEnv(string $name): ?string
    {
        if (array_key_exists($name, $_ENV) === false) {
            return null;
        }

        if (!is_string($_ENV[$name])) {
            return null;
        }

        if ($_ENV[$name] !== '') {
            return null;
        }

        return $_ENV[$name];
    }
}
