<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends BaseController
{
    /**
     * @Route("/", name="homepage")
     *
     * @return Response
     */
    public function indexAction()
    {
        $this->clearBreadcrumb();

        return $this->render('@App/Default/index.html.twig', array(
            'activeMenu' => "home",
        ));
    }

}
