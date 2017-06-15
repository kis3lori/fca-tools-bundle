<?php

namespace AppBundle\Controller;

use AppBundle\Document\Context;
use AppBundle\Document\Group;
use AppBundle\Document\User;
use AppBundle\Helper\CommonUtils;
use AppBundle\Repository\ContextRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Response;

class ContextController extends BaseController
{

    /**
     * @Route("/contexts", name="context_homepage")
     *
     * @return Response
     */
    public function indexAction()
    {
        $user = $this->getUser();
        if (!$user) return $this->redirect($this->generateUrl("public_contexts"));

        /** @var ContextRepository $contextRepo */
        $contextRepo = $this->getRepo("AppBundle:Context");
        $contexts = $contextRepo->getAllViewableByUser($user);

        return $this->render('@App/Context/index.html.twig', array(
            'activeMenu' => "contexts",
            'contexts' => $contexts,
        ));
    }

    /**
     * @Route("/public-contexts", name="public_contexts")
     *
     * @return Response
     */
    public function publicContextsAction()
    {
        $contexts = $this->getRepo("AppBundle:Context")->findBy(array(
            'isPublic' => true
        ));

        return $this->render('@App/Context/publicContexts.html.twig', array(
            'activeMenu' => "public_contexts",
            'contexts' => $contexts,
        ));
    }

    /**
     * @Security("has_role('ROLE_USER')")
     * @Route("/my-contexts", name="list_user_contexts")
     *
     * @return Response
     */
    public function listUserContextsAction()
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) return $this->redirect($this->generateUrl("public_contexts"));

        $contexts = $user->getContexts();

        return $this->render('@App/Context/listUserContexts.html.twig', array(
            'activeMenu' => "my_contexts",
            'contexts' => $contexts,
        ));
    }

    /**
     * @Route("/view-context/{id}", name="view_context")
     *
     * @param $id
     * @return Response
     */
    public function viewContextAction($id)
    {
        /** @var Context $context */
        $context = $this->getRepo("AppBundle:Context")->find($id);

        if (!$this->isValidContext($context, array("not null", "can view"))) {
            return $this->renderFoundError("contexts");
        }

        return $this->render("@App/Context/context.html.twig", array(
            'context' => $context,
            'activeMenu' => "contexts",
        ));
    }

    /**
     * @Security("has_role('ROLE_USER')")
     * @Route("/delete-context/{id}", name="delete_context")
     *
     * @param $id
     * @return Response
     */
    public function deleteContextAction($id)
    {
        $em = $this->getManager();
        $context = $em->getRepository("AppBundle:Context")->find($id);

        if (!$this->isValidContext($context, array("not null", "is own"))) {
            return $this->renderFoundError("contexts");
        }

        $em->remove($context);
        $em->flush();

        return $this->redirect($this->generateUrl("list_user_contexts"));
    }

}
