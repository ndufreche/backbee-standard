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
 * Quote Listener
 *
 * @author f.kroockmann <florian.kroockmann@lp-digital.fr>
 */
class QuoteListener extends Event
{
    public static function onRender(Event $event)
    {
        $renderer = $event->getEventArgs();
        $application = $event->getDispatcher()->getApplication();
        $em = $application->getEntityManager();

        $content = $renderer->getObject();

        $links = $content->getParamValue('link');
        $link = [
                    'url' => '',
                    'title' => 'Visit',
                    'target' => '_self'
                ];

        if (!empty($links)) {
            $links = reset($links);
            if (isset($links['pageUid']) && !empty($links['pageUid'])) {
                $page = $em->getRepository('BackBee\NestedNode\Page')->find($links['pageUid']);
                if ($page !== null) {
                    $link['url'] = $renderer->getUri($page->getUrl());
                }
            }

            if (empty($link['url']) && isset($links['url'])) {
                $link['url'] = $links['url'];
            }

            if (isset($links['title'])) {
                $link['title'] = $links['title'];
            }

            if (isset($links['target'])) {
                $link['target'] = $links['target'];
            }
        }

        $renderer->assign('link', $link);
    }
}