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

namespace BackBee\Event\Listener;

use BackBee\Event\Event;

/**
 * Social Listener
 *
 * @author f.kroockmann <florian.kroockmann@lp-digital.fr>
 */
class SocialListener extends Event
{
    private static $application;

    public static function onPreRenderFacebook(Event $event)
    {
        $renderer = $event->getEventArgs();
        self::$application = $event->getDispatcher()->getApplication();

        $config = self::getSocialConfig('facebook');

        $content = $renderer->getObject();

        $link = $content->getParamValue('link');
        if (empty($link)) {
            if (null !== $config && isset($config['link'])) {
                $link = $config['link'];
            }
        }

        $showPost = $content->getParamValue('show_post');
        $hideCover = $content->getParamValue('hide_cover');

        $renderer->assign('link', $link)
                 ->assign('show_post', in_array('show_post', $showPost))
                 ->assign('hide_cover', in_array('hide_cover', $hideCover))
                 ->assign('height', $content->getParamValue('height'));
    }

    public static function onPreRenderTwitter(Event $event)
    {
        $renderer = $event->getEventArgs();
        self::$application = $event->getDispatcher()->getApplication();

        $config = self::getSocialConfig('twitter');

        $content = $renderer->getObject();

        $widgetId = $content->getParamValue('widget_id');

        if (empty($widgetId)) {
            if (null !== $config && isset($config['widget_id'])) {
                $widgetId = $config['widget_id'];
            }
        }

        $renderer->assign('widget_id', $widgetId);
    }

    private static function getSocialConfig($key)
    {
        $socialsNetworks = self::$application->getConfig()->getSection('social_network');
        $config = null;

        if ($socialsNetworks !== null && isset($socialsNetworks[$key])) {
            $config = $socialsNetworks[$key];
        }

        return $config;
    }
}