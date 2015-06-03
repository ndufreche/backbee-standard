<?php

namespace BackBee\Renderer\Helper;

class navbar extends AbstractHelper
{

    public function __invoke($position, $mode = null)
    {
        $application = $this->_renderer->getApplication();
        $repository = $application->getEntityManager()->getRepository('BackBee\NestedNode\Page');

        $sections = [];
        $articles = [];
        $selected = null;

        if (null !== $current = $this->_renderer->getCurrentPage()) {
            $selected = $repository->getAncestor($current, 1);
            $sections = $repository->getVisibleDescendants($current->getRoot(), 2);

            foreach ($sections as $page) {
                if (1 === $page->getLevel()) {
                    $selector = [
                        'parentnode' => [$page->getUid()],
                        'orderby'    => ['modified', 'desc'],
                        'limit'      => 6,
                    ];

                    $articles[$page->getUid()] = $application->getEntityManager()
                        ->getRepository('BackBee\ClassContent\AbstractClassContent')
                        ->getSelection($selector, false, true, 0, 3, true, false, ['BackBee\ClassContent\Article'])
                    ;
                }
            }
        }

        $is_header = false;
        if ($position == 'header') {
            $is_header = true;
        }

        $render = $this->_renderer->partial('partials/navbar.' . (null !== $mode ? $mode . '.' : '') . 'twig', [
            'sections' => $sections,
            'articles' => $articles,
            'selected' => $selected,
            'is_header' => $is_header,
        ]);

        return $render;
    }

    private function generateCacheUid($selected = null)
    {
        return md5('navbar-' . (null === $selected ? 'none' : $selected->getUid()));
    }

}
