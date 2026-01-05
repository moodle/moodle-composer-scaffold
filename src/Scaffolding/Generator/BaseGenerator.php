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

use Composer\Composer;
use Composer\IO\IOInterface;
use Moodle\Composer\Scaffold\PackagePathTrait;

/**
 * Service to scaffold Moodle core files.
 */
abstract class BaseGenerator
{
    use PackagePathTrait;

    /**
     * Constructor.
     *
     * @param Composer $composer The Composer service.
     * @param IOInterface $io The Composer I/O service.
     */
    final public function __construct(
        /** @var Composer The Composer service. */
        protected Composer $composer,
        /** @var IOInterface The Composer I/O service. */
        protected IOInterface $io,
    ) {
    }

    /**
     * Generate the file.
     *
     * @return void
     */
    abstract public function generate(): void;

    /**
     * Add a path to the .gitignore.
     *
     * @param string $path
     * @param string $description
     * @param mixed $reason
     */
    protected function addGitIgnore(
        string $path,
        string $description,
        ?string $reason = null,
    ): void {
        $gitignorepath = $this->getRootPackagePath() . '/.gitignore';
        $fullentry = "# Ignore {$description}\n";
        if ($reason) {
            $fullentry .= "# {$reason}\n";
        }
        $entry = '/' . ltrim($path, '/') . "\n";
        $fullentry .= $entry;

        if (file_exists($gitignorepath)) {
            $currentcontent = file_get_contents($gitignorepath);
            if (strpos($currentcontent, $entry) !== false) {
                $this->io->debug("{$path} is already in .gitignore");
                return;
            }

            $this->io->debug("Adding {$path} to .gitignore");
            $newcontent = $currentcontent . $fullentry;
            file_put_contents($gitignorepath, $newcontent);
        } else {
            if (!file_exists(dirname($gitignorepath) . '/.git')) {
                $this->io->debug("Not creating .gitignore as project is not under git");
                return;
            }

            $this->io->debug("Creating .gitignore and adding {$path}");
            file_put_contents($gitignorepath, $fullentry);
        }
    }
}
