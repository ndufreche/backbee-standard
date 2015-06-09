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

use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * Autoblock Listener
 *
 * @author      f.kroockmann <florian.kroockmann@lp-digital.fr>
 */
class AutoblockListener extends Event
{
    /**
     * @var BackBee\BBApplication
     */
    private static $application;

    /**
     * @var Doctrine\ORM\EntityManager
     */
    private static $em;

    /**
     * @var BackBee\Renderer\Renderer
     */
    private static $renderer;

    public static function onRender(Event $event)
    {
        self::$application = self::$renderer->getApplication();
        self::$em = self::$application->getEntityManager();
        self::$renderer = $event->getEventArgs();

        $content = self::$renderer->getObject();

        $selector = ['parentnode' => [self::getParentNodeUid($content->getParamValue('parent_node'))]];

        $contents = self::$em->getRepository('BackBee\ClassContent\AbstractClassContent')
                             ->getSelection(
                                 $selector,
                                 in_array('multipage', $content->getParamValue('multipage')),
                                 in_array('recursive', $content->getParamValue('recursive')),
                                 (int) $content->getParamValue('start'),
                                 (int) $content->getParamValue('limit'),
                                 self::$application->getBBUserToken() === null,
                                 false,
                                 (array) $content->getParamValue('content_to_show'),
                                 (int) $content->getParamValue('delta')
                             );

        $count = $contents instanceof Paginator ? $contents->count() : count($contents);

        self::$renderer->assign('contents', $contents);
        self::$renderer->assign('nbContents', $count);
    }

    private static function getParentNodeUid($parentNodeParam)
    {
        $parentNode = null;

        if (!empty($parentNodeParam)) {
            if (isset($parentNodeParam['pageUid'])) {
                $parentNode = self::$em->getRepository('BackBee\NestedNode\Page')->find($parentNodeParam['pageUid']);
            }
        } else {
            $parentNode = self::$renderer->getCurrentPage();
        }

        return ($parentNode !== null) ? $parentNode->getUid() : null;
    }
}