<?php

namespace Moodle\Composer\Plugin\Scaffold;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;

trait PackagePathTrait
{
    /**
     * Get the root package name.
     *
     * @return string The root package name.
     */
    protected function rootPackageName(): string
    {
        return $this->composer->getPackage()->getName();
    }

    protected function getRootPackagePath(): string
    {
        return (new Filesystem())->normalizePath(
            dirname($this->getVendorPath())
        );
    }

    /**
     * Get the Moodle installation path.
     *
     * @return string The Moodle installation path.
     */
    public function getMoodlePath(): string
    {
        return dirname($this->getVendorPath()) . '/' . $this->getInstallDirectory();
    }

    /**
     * Get the vendor directory path.
     *
     * @return string The vendor directory path.
     */
    protected function getVendorPath(): string
    {
        $vendor = $this->composer->getConfig()->get('vendor-dir');

        return (new Filesystem())->normalizePath(realpath($vendor));
    }

    /**
     * Get the name of the installation directory.
     *
     * @return string The installation directory.
     */
    protected function getInstallDirectory(): string
    {
        $extra = $this->composer->getPackage()->getExtra();
        return $extra['install-path'] ?? 'moodle/';
    }

    /**
     * Calculate the relative path between two points.
     *
     * @param string $from
     * @param string $to
     * @return string
     */
    protected function calculateRelativePath(
        string $from,
        string $to,
        bool $directories = false,
    ): string {
        $fs = new Filesystem();
        return $fs->findShortestPath($from, $to, $directories);
    }

    protected function getBaseDirName(): string
    {
        return basename(dirname($this->getVendorPath()));
    }
}
