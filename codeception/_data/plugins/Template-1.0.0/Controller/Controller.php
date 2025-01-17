<?php

namespace Plugin\Template\Controller;

use Eccube\Controller\AbstractController;
use Eccube\Controller\Annotation\Template;
use Symfony\Component\HttpFoundation\Request;
use Eccube\Routing\Annotation\Route;

class Controller extends AbstractController
{
    /**
     * @Route("/template", name="template")
     * @Template("@Template/index.twig")
     */
    public function front(Request $request)
    {
        return [];
    }

    /**
     * @Route("/%eccube_admin_route%/template", name="template_admin")
     * @Template("@Template/admin/index.twig")
     */
    public function admin(Request $request)
    {
        return [];
    }

}
