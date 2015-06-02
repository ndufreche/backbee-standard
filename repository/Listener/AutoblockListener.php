<?php
namespace BackBee\Event\Listener;

use BackBee\Event\Event;

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class AutoblockListener extends Event
{
    public static function onRender(Event $event)
    {
        $dispatcher = $event->getDispatcher();
        $renderer = $event->getEventArgs();
        $content = $renderer->getObject();
        $application = $renderer->getApplication();

        $renderer->assign('contents', []);
        if (NULL != $selector = $content->getParam('selector')) {
            $selector = reset($selector);

            if (!array_key_exists('parentnode', $selector) || 0 == count($selector['parentnode'])) {
                if (NULL !== $renderer->getCurrentPage()) {
                    $selector['parentnode'] = [$renderer->getCurrentPage()->getUid()];
                } else {
                    $selector['parentnode'] = null;
                }
            }

            $limit = array_key_exists('limit', $selector) ? $selector['limit'] : 10;

            if (array_key_exists('classcontent', $selector)) {
                $classcontents = (array)$selector['classcontent'];

                $contentsList = $application->getEntityManager()
                                            ->getRepository('BackBee\ClassContent\AbstractClassContent')
                                            ->getSelection(
                                                $selector,
                                                false,
                                                false,
                                                0,
                                                $limit,
                                                true,
                                                false,
                                                $classcontents
                                            );
            }
        }

        $renderer->assign('contents', $contentsList);
        $renderer->assign('nbContent', count($contentsList));
    }
}