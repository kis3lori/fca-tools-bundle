<?php

namespace AppBundle\Controller;

use AppBundle\Document\Context;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;

class ContextSettingsController extends BaseController
{

    /**
     * @Route("/make-context-public/{id}", name="mark_context_public")
     *
     * @param $id
     * @return Response
     * @internal param Request $request
     */
    public function markContextPublicAction($id)
    {
        $em = $this->getManager();
        $context = $em->getRepository("AppBundle:Context")->find($id);

        if (!$this->isValidContext($context, array("not null", "is own"))) {
            return $this->renderFoundError("my_contexts");
        }

        $context->setIsPublic(true);
        $em->persist($context);
        $em->flush();

        return $this->redirect($this->generateUrl("view_context", array(
            "id" => $context->getId(),
        )));
    }

    /**
     * @Route("/make-context-private/{id}", name="mark_context_private")
     *
     * @param $id
     * @return Response
     * @internal param Request $request
     */
    public function markContextPrivateAction($id)
    {
        $em = $this->getManager();
        $context = $em->getRepository("AppBundle:Context")->find($id);
        
        if (!$this->isValidContext($context, array("not null", "is own"))) {
            return $this->renderFoundError("my_contexts");
        }

        $context->setIsPublic(false);
        $em->persist($context);
        $em->flush();

        return $this->redirect($this->generateUrl("view_context", array(
            "id" => $context->getId(),
        )));
    }

}
