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
 * Article Listener
 *
 * @author f.kroockmann <florian.kroockmann@lp-digital.fr>
 */
class ArticleListener extends Event
{
    public static function onRender(Event $event)
    {
        $renderer = $event->getEventArgs();
        $application = $event->getDispatcher()->getApplication();

        $content = $renderer->getObject();
        $tag = null;
        $mainNode = $content->getMainNode();

        if ($mainNode !== null) {
            $parentNode = $mainNode->getParent();
            if (null !== $parentNode) {
                $tag = (!empty($parentNode->getAltTitle())) ? $parentNode->getAltTitle() : $parentNode->getTitle();
            }
        }

        $renderer->assign('tag', $tag);
    }
}