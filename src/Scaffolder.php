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

namespace Moodle\Composer\Plugin\Scaffold;

use Composer\Composer;
use Composer\IO\IOInterface;
use Moodle\Composer\Plugin\Scaffold\Scaffolding\Generator;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Dotenv\Exception\PathException;
use Symfony\Component\Process\Process;

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

    public function installMoodle(): void
    {
        $installRequested = $this->io->askConfirmation(
            'Do you want to run the Moodle installer now? (Y/n) ',
            true,
        );

        if ($installRequested === false) {
            $this->io->write('- <comment>Skipping Moodle installer. You can run it later by running:</comment>');
            $this->io->write('  php admin/cli/install_database.php');
            return;
        }

        $this->io->write('');
        $this->io->write('<info>Launching Moodle installer...</info>');

        if (empty($_ENV['MOODLE_AGREE_LICENSE'])) {
            // Display the license agreement first.
            $licenseAgreed = $this->io->askConfirmation(
                'Do you agree to the GNU General Public License terms? (y/N) ',
                false,
            );

            if ($licenseAgreed === false) {
                $this->io->write('<error>You must agree to the license terms to proceed with the installation.</error>');
                return;
            }
        }

        if (!empty($_ENV['MOODLE_ADMIN_PASSWORD'])) {
            $adminPassword = $_ENV['MOODLE_ADMIN_PASSWORD'];
        } else {
            do {
                $adminPassword = $this->io->askAndHideAnswer('Enter the password for the admin user: ');
                $passwordValid = $adminPassword !== null && strlen($adminPassword) >= 6;
                if ($passwordValid === false) {
                    $this->io->write('<error>Password must be at least 6 characters long. Please try again.</error>');
                }
            } while ($passwordValid === false);
        }

        if (!empty($_ENV['MOODLE_ADMIN_EMAIL'])) {
            $adminEmail = $_ENV['MOODLE_ADMIN_EMAIL'];
        } else {
            $adminEmail = $this->io->askAndValidate(
                'Enter the email address for the admin user: ',
                function ($value): string {
                    if (empty($value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        throw new \RuntimeException('Invalid email address.');
                    }

                    return $value;
                },
            );
        }

        $defaultShortName = $this->getBaseDirName();
        $shortName = $this->io->askAndValidate(
            "Enter the site short name: (default {$defaultShortName}) ",
            function ($value): string {
                if (empty($value)) {
                    throw new \RuntimeException('Site short name cannot be empty.');
                }

                return $value;
            },
            null,
            $defaultShortName,
        );

        $installCommand = new Process([
            PHP_BINARY,
            'admin/cli/install_database.php',
            '--agree-license',
            '--adminpass=' . $adminPassword,
            '--adminemail=' . $adminEmail,
            '--shortname=' . $shortName,
        ], $this->getMoodlePath(), null, null, null);

        $this->io->write('');
        $this->io->write('<info>Launching Moodle installer...</info>');

        $installCommand->run(function ($type, $buffer) {
            $this->io->write($buffer, false);
        });
    }

    /**
     * Generate the Moodle configuration file.
     *
     * @return void
     */
    public function generateConfigurationFile(): void
    {
        $this->loadEnvFile();
        (new Generator\ConfigFile($this->composer, $this->io))->generateConfigurationFile();
    }

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

    protected function loadEnvFile(): void
    {
        $dotenv = new Dotenv();

        // Load from the current working directory.
        $files = [
            getcwd() . '/.env',
            getcwd() . '/.env.local',
            dirname(getcwd()) . '/.env',
            dirname(getcwd()) . '/.env.local',
        ];

        foreach ($files as $file) {
            if (file_exists($file) === false) {
                continue;
            }
            $dotenv->load($file);
        }
    }
}
