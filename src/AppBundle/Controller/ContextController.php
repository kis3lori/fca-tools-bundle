<?php

namespace AppBundle\Controller;

use AppBundle\Document\Context;
use AppBundle\Document\User;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
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
        $groups = $user->getGroups();

        $ids = $this->filterIds($groups);


        $contexts = $this->getRepo("AppBundle:Context")->findBy(array(
            'isPublic' => true
        ));

        $privates = $this->getRepo("AppBundle:Context")->findBy(array(
            'isPublic' => false
        ));

        foreach ($privates as $pr) {
            $pr_ids = $this->filterIds($pr->getGroups());
            if (sizeof(array_intersect($pr_ids, $ids)) > 0) {
                $contexts[] = $pr;
            }
        }


        return $this->render('@App/Context/index.html.twig', array(
            'activeMenu' => "contexts",
            'contexts' => $contexts,
        ));
    }

    private function filterIds($groups)
    {
        $ids = array();
        foreach ($groups as $group) {
            $ids[] = $group->getId();
        }
        return $ids;
    }

    /**
     * @Route("/my-contexts", name="list_user_contexts")
     *
     * @return Response
     */
    public function listUserContextsAction()
    {
        /** @var User $user */
        $user = $this->getUser();
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
