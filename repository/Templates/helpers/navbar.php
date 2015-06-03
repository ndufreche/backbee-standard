<?php

namespace BackBee\Renderer\Helper;

class navbar extends AbstractHelper
{

    public function __invoke($position)
    {
        $application = $this->_renderer->getApplication();
        $repository = $application->getEntityManager()->getRepository('BackBee\NestedNode\Page');

        $sections = [];
        $currentPage = $this->_renderer->getCurrentPage();
        $selected = null;

        if (null !== $currentPage) {
            $selected = $repository->getAncestor($currentPage, 1);
            $sections = $repository->getVisibleDescendants($currentPage->getRoot(), 1);
        }

        $render = $this->_renderer->partial('partials/navbar.twig', [
            'sections' => $sections,
            'selected' => $selected,
            'is_header' => $position === 'header',
            'is_root' => ($currentPage !== null) ? $currentPage->isRoot() : false
        ]);

        return $render;
    }

    private function generateCacheUid($selected = null)
    {
        return md5('navbar-' . (null === $selected ? 'none' : $selected->getUid()));
    }

}
