<?php

namespace AppBundle\Controller;

use AppBundle\Document\Context;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Security("has_role('ROLE_USER')")
 *
 * Class ContextSettingsController
 * @package AppBundle\Controller
 */
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
        /** @var Context $context */
        $context = $this->getRepo("AppBundle:Context")->find($id);

        if (!$this->isValidContext($context, array("not null", "is own"))) {
            return $this->renderFoundError("my_contexts");
        }

        $context->setIsPublic(true);
        $em = $this->getManager();
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
        /** @var Context $context */
        $context = $this->getRepo("AppBundle:Context")->find($id);

        if (!$this->isValidContext($context, array("not null", "is own"))) {
            return $this->renderFoundError("my_contexts");
        }

        $context->setIsPublic(false);
        $em = $this->getManager();
        $em->persist($context);
        $em->flush();

        return $this->redirect($this->generateUrl("view_context", array(
            "id" => $context->getId(),
        )));
    }

}
