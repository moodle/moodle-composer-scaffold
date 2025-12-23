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
use Symfony\Component\Process\Process;

class MoodleInstaller
{
    use PackagePathTrait;

    public function __construct(
        /** @var Composer The Composer service. */
        protected Composer $composer,

        /** @var IOInterface The Composer I/O service. */
        protected IOInterface $io,
    ) {
    }

    /**
     * Run the Moodle installer.
     */
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

        if ($this->getLicenseConfirmation() === false) {
            $this->io->write('<error>You must agree to the license terms to proceed with the installation.</error>');
            return;
        }

        $adminEmail = $this->getAdminEmail();
        $adminPassword = $this->getAdminPassword();
        $shortName = $this->getShortName();

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

    protected function getLicenseConfirmation(): bool
    {
        if (empty($_ENV['MOODLE_AGREE_LICENSE']) === false) {
            return true;
        }

        $this->io->write('================================ ');
        $this->io->write('<comment>Moodle GNU General Public License Agreement</comment>');
        $this->io->write('================================ ');
        $this->io->write('');
        $this->io->write($this->getLicenseText());
        $this->io->write('');

        // Display the license agreement first.
        return $this->io->askConfirmation(
            'Do you agree to the GNU General Public License terms? (y/N) ',
            false,
        );
    }

    protected function getAdminEmail(): string
    {
        if (!empty($_ENV['MOODLE_ADMIN_EMAIL'])) {
            /** @var string */
            return $_ENV['MOODLE_ADMIN_EMAIL'];
        }

        /** @var string */
        return $this->io->askAndValidate(
            'Enter the email address for the admin user: ',
            function ($value): string {
                if (empty($value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException('Invalid email address.');
                }

                return $value;
            },
        );
    }

    protected function getAdminPassword(): string
    {
        if (!empty($_ENV['MOODLE_ADMIN_PASSWORD'])) {
            /** @var string */
            return $_ENV['MOODLE_ADMIN_PASSWORD'];
        }

        do {
            $adminPassword = $this->io->askAndHideAnswer('Enter the password for the admin user: ');
            $passwordValid = $adminPassword !== null && strlen($adminPassword) >= 6;
            if ($passwordValid === false) {
                $this->io->write('<error>Password must be at least 6 characters long. Please try again.</error>');
            }
        } while ($passwordValid === false);

        /** @var string */
        return $adminPassword;
    }

    protected function getShortName(): string
    {
        $defaultShortName = $this->getBaseDirName();
        /** @var string */
        return $this->io->askAndValidate(
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
    }

    protected function getLicenseText(): string
    {
        // Attempt to load the license text from the Moodle root directory.
        $licenseFilePaths = [
            $this->getMoodlePath() . '/public/lang/en/moodle.php',
            $this->getMoodlePath() . '/lang/en/moodle.php',
        ];

        foreach ($licenseFilePaths as $licenseFilePath) {
            if (is_readable($licenseFilePath)) {
                $string = [];
                include $licenseFilePath;
                if (isset($string['gpl3'])) {
                    return $string['gpl3'];
                }
            }
        }

        // Fallback: load the license text from a static value.
        // This value copied from Moodle 5.2.
        return <<<EOT
        Copyright (C) 1999 onwards Martin Dougiamas (https://moodle.com)

        This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

        This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

        See the Moodle License information page for full details: https://moodledev.io/general/license
        EOT;
    }
}
