<?php

namespace BackBee\Renderer\Helper;

class socialNetwork extends AbstractHelper
{

    public function __invoke($showCurrentItem = true)
    {
        $application = $this->_renderer->getApplication();

        $socialsNetworks = $application->getConfig()->getSection('social_network');

        $render = $this->_renderer->partial('partials/socialNetwork.twig', [
            'social_networks' => $socialsNetworks
        ]);

        return $render;
    }
}
