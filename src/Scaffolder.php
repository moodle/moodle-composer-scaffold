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

namespace Moodle\Composer\Scaffold;

use Composer\Composer;
use Composer\IO\IOInterface;
use Moodle\Composer\Scaffold\Scaffolding\Generator;
use Symfony\Component\Dotenv\Dotenv;

/**
 * Service to scaffold Moodle core files.
 */
class Scaffolder
{
    use PackagePathTrait;

    /**
     * Event name dispatched before scaffolding starts.
     */
    public const PRE_MOODLE_SCAFFOLD = 'moodle-pre-scaffold';

    /**
     * Event name dispatched after scaffolding ends.
     */
    public const POST_MOODLE_SCAFFOLD = 'moodle-post-scaffold';

    /** @var bool Whether the env file has been loaded. */
    protected bool $envLoaded = false;

    /**
     * Constructor.
     *
     * @param Composer $composer The Composer service.
     * @param IOInterface $io The Composer I/O service.
     */
    public function __construct(
        /** @var Composer The Composer service. */
        protected Composer $composer,

        /** @var IOInterface The Composer I/O service. */
        protected IOInterface $io,
    ) {
    }

    /**
     * Perform scaffold tasks for Moodle.
     *
     * @return void
     */
    public function scaffold(): void
    {
        $dispatcher = $this->composer->getEventDispatcher();

        $dispatcher->dispatchScript(self::PRE_MOODLE_SCAFFOLD);

        $this->io->write($this->asciiHeader());
        $this->io->write('<info>Scaffolding Moodle core files...</info>');

        // Generate the Moodle Configuration Shim file.
        (new Generator\ShimConfigFile($this->composer, $this->io))->generate();

        // Attempt to generate the Moodle configuration file.
        $configFile = new Generator\ConfigFile($this->composer, $this->io);
        if ($configFile->checkFileExists()) {
            $this->io->write('- <comment>Configuration file already exists. Skipping generation.</comment>');
        } else {
            $this->loadEnvFile();

            if (empty($_ENV['MOODLE_CREATE_CONFIG'])) {
                $configRequested = $this->io->askConfirmation(
                    'A Moodle configuration file does not exist. Do you want to generate a new one now? (Y/n) ',
                    true,
                );
            } else {
                $configRequested = filter_var($_ENV['MOODLE_CREATE_CONFIG'], FILTER_VALIDATE_BOOLEAN);
            }

            if ($configRequested) {
                $this->generateConfigurationFile();
                $this->installMoodle();
            }
        }

        $this->io->write('');
        $this->io->write('<info>Moodle core files scaffolded successfully.</info>');
        $dispatcher->dispatchScript(self::POST_MOODLE_SCAFFOLD);
    }

    /**
     * Run the Moodle installer.
     */
    public function installMoodle(): void
    {
        $installer = new MoodleInstaller($this->composer, $this->io);

        $installer->installMoodle();
    }

    /**
     * Generate the Moodle configuration file.
     */
    public function generateConfigurationFile(): void
    {
        $this->loadEnvFile();
        (new Generator\ConfigFile($this->composer, $this->io))->generateConfigurationFile();
    }

    /**
     * Get the ASCII Moodle Logo.
     *
     * @return string
     */
    protected function asciiHeader(): string
    {
        return <<<HEADER
         __  __                 _ _
        |  \/  | ___   ___   __| | | ___
        | |\/| |/ _ \ / _ \ / _` | |/ _ \
        | |  | | (_) | (_) | (_| | |  __/
        |_|  |_|\___/ \___/ \__,_|_|\___|

        HEADER;
    }

    /**
     * Load settings from env.
     */
    protected function loadEnvFile(): void
    {
        if ($this->envLoaded) {
            return;
        }
        $this->envLoaded = true;

        $dotenv = new Dotenv();

        $cwd = getcwd();
        if ($cwd === false) {
            // Unable to get current working directory to load env files.
            return;
        }

        // Load from the current working directory.
        $files = [
            "{$cwd}/.env",
            "{$cwd}/.env.local",
            dirname($cwd) . '/.env',
            dirname($cwd) . '/.env.local',
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                $dotenv->load($file);
            }
        }
    }
}
