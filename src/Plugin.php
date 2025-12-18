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
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

/**
 * Moodle Composer Scaffold Plugin.
 */
class Plugin implements
    Capable,
    EventSubscriberInterface,
    PluginInterface
{
    /**
     * The Composer service.
     *
     * @var \Composer\Composer
     */
    protected Composer $composer;

    /**
     * Composer's I/O service.
     *
     * @var \Composer\IO\IOInterface
     */
    protected IOInterface $io;

    /**
     * The scaffolder service.
     *
     * @var Scaffolder|null
     */
    protected Scaffolder|null $scaffolder = null;

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // Nothing to clean up.
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // Nothing to clean up.
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public static function getSubscribedEvents(): array {
        // Important note: We only instantiate our handler on "post" events.
        return [
            ScriptEvents::POST_UPDATE_CMD => 'postCmd',
            ScriptEvents::POST_INSTALL_CMD => 'postCmd',
        ];
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getCapabilities(): array
    {
        return [
            \Composer\Plugin\Capability\CommandProvider::class => CommandProvider::class,
        ];
    }

    /**
     * Scaffold Moodle installation.
     *
     * @param Event $event The Composer script event.
     * @return void
     */
    public function postCmd(Event $event): void
    {
        $this->scaffolder()->scaffold();
    }

    /**
     * Get the scaffolder service.
     *
     * @return Scaffolder The scaffolder service.
     */
    public function scaffolder(): Scaffolder
    {
        if ($this->scaffolder === null) {
            $this->scaffolder = new Scaffolder($this->composer, $this->io);
        }

        return $this->scaffolder;
    }
}
