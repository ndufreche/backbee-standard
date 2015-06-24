<?php

/*
 * Copyright (c) 2011-2015 Lp digital system
 *
 * This file is part of BackBee Standard Edition.
 *
 * BackBee is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * BackBee Standard Edition is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee Standard Edition. If not, see <http://www.gnu.org/licenses/>.
 */

namespace BackBee\Standard;

use BackBee\BBApplication;
use BackBee\Event\Event;

/**
 * @author e.chau <eric.chau@lp-digital.fr>
 * @author Mickaël Andrieu <mickael.andrieu@lp-digital.fr>
 */
class Application extends BBApplication
{
    /**
     * {@inheritdoc}
     */
    public function getBaseDir()
    {
        return __DIR__;
    }

    /**
     * {@inheritdoc}
     */
    public static function getConfigurationDir()
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'repository' . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR;
    }

    /**
     * Check if the CMS is installed
     */
    public static function isInstalled()
    {
        $configDirectory = __DIR__.DIRECTORY_SEPARATOR.'repository'.DIRECTORY_SEPARATOR.'Config';
        return is_file($configDirectory.DIRECTORY_SEPARATOR.'sites.yml')
            && is_file($configDirectory.DIRECTORY_SEPARATOR.'doctrine.yml')
            && is_file($configDirectory.DIRECTORY_SEPARATOR.'bootstrap.yml')
        ;
    }

    /**
     * Stop the current BBApplication instance.
     */
    public function stop()
    {
        parent::stop();
        exit();
    }
}

