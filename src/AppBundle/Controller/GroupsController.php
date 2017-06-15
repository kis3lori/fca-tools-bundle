<?php

namespace AppBundle\Controller;

use AppBundle\Document\Context;
use AppBundle\Document\Group;
use AppBundle\Document\User;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Security("has_role('ROLE_USER')")
 *
 * Class GroupsController
 * @package AppBundle\Controller
 */
class GroupsController extends BaseController
{

    /**
     * @Route("/groups", name="list_user_groups")
     *
     * @return Response
     */
    public function listGroupsAction()
    {
        /** @var User $user */
        $user = $this->getUser();
        $groups = $user->getGroups();

        return $this->render('@App/Groups/listGroups.html.twig', array(
            'activeMenu' => "groups",
            'groups' => $groups,
        ));
    }

    /**
     * @Route("/group/{id}", name="group")
     *
     * @param $id
     * @return Response
     */
    public function groupAction($id)
    {
        /** @var Group $group */
        $group = $this->getRepo("AppBundle:Group")->find($id);

        if (!$this->isValidGroup($group, array("not null", "can view"))) {
            return $this->renderFoundError("groups");
        }

        return $this->render('@App/Groups/group.html.twig', array(
            'activeMenu' => "group",
            'group' => $group,
        ));
    }

    /**
     * @Route("/new-group", name="create_new_group")
     * @param Request $request
     * @return Response
     */
    public function createNewGroupAction(Request $request)
    {
        $errors = array();

        if ($request->isMethod("POST")) {
            $postData = $request->request;

            $groupName = $postData->get("name");
            if (!$groupName) {
                $errors["group"] = "The name of the group cannot be empty.";
            }

            if (empty($errors)) {
                $group = new Group();
                $group->setName($groupName);
                $group->setOwner($this->getUser());
                $group->addUser($this->getUser());

                $em = $this->getManager();
                $em->persist($group);
                $em->flush();

                return $this->redirect($this->generateUrl("list_user_groups"));
            }
        }

        return $this->render('@App/Groups/listGroups.html.twig', array(
            'activeMenu' => "groups",
            'errors' => $errors,
            'groups' => $this->getUser()->getGroups()
        ));
    }

    /**
     * @Route("/share-context/{id}", name="share_context")
     *
     * @param $id
     * @param Request $request
     * @return Response
     */
    public function shareContextToGroupAction($id, Request $request)
    {
        $em = $this->getManager();
        /** @var Context $context */
        $context = $em->getRepository("AppBundle:Context")->find($id);
        $errors = array();

        if ($request->isMethod("POST")) {
            $postData = $request->request;

            if (!$this->isValidContext($context, array("not null", "is own"))) {
                return $this->renderFoundError("my_contexts");
            }

            /** @var Group $group */
            $group = $em->getRepository("AppBundle:Group")->find($postData->get("group"));
            if (!$this->isValidGroup($group, array("not null", "is member"))) {
                return $this->renderFoundError("groups");
            }

            if (empty($errors)) {
                if ($group->hasContext($context)) {
                    $group->removeContext($context);
                } else {
                    $group->addContext($context);
                }

                $em->persist($group);
                $em->flush();

                return $this->redirect($this->generateUrl("view_context", array(
                    "id" => $context->getId(),
                )));
            }
        }

        return $this->render("@App/Context/context.html.twig", array(
            'context' => $context,
            'activeMenu' => "contexts",
            'errors' => $errors,
        ));
    }

    /**
     * @Route("/add-member", name="add_member")
     * @param $request
     * @return Response
     * @internal param Request $request
     */
    public function addMemberToGroupAction(Request $request)
    {
        $errors = array();

        if ($request->isMethod("POST")) {
            $em = $this->getManager();

            $postData = $request->request;

            if (!$postData->has("username") || $postData->get("username") == "") {
                $errors["username"] = "The name of the user cannot be empty.";
            }

            if (!$postData->has("group-id") || $postData->get("group-id") == "") {
                $errors["group"] = "The id of the group cannot be empty.";
            }

            if (empty($errors)) {
                /** @var Group $group */
                $group = $em->getRepository("AppBundle:Group")->find($postData->get("group-id"));
                if (!$this->isValidGroup($group, array("not null", "is own"))) {
                    return $this->renderFoundError("groups");
                }

                $userName = $postData->get("username");
                $user = $em->getRepository("AppBundle:User")->findOneBy(array('username' => $userName));

                if ($user == null) {
                    $errors["username"] = "No user was found with the username: \"" . $userName . "\".";
                } else if ($group->hasUser($user)) {
                    $errors["group"] = "The specified user is already a member of the group.";
                }

                if (empty($errors)) {
                    $group->addUser($user);
                    $em->persist($group);
                    $em->flush();

                    return $this->redirect($this->generateUrl("list_user_groups"));
                }
            }
        }

        return $this->render('@App/Groups/listGroups.html.twig', array(
            'activeMenu' => "groups",
            'errors' => $errors,
            'groups' => $this->getUser()->getGroups(),
        ));
    }

    /**
     * @Route("/group/{id}/remove-member/{memberId}", name="remove_member")
     *
     * @param $id
     * @param $memberId
     * @param Request $request
     * @return Response
     * @internal param Request $request
     */
    public function removeMemberFromGroupAction($id, $memberId, Request $request)
    {
        /** @var Group $group */
        $group = $this->getRepo("AppBundle:Group")->find($id);
        if (!$this->isValidGroup($group, array("not null", "is own"))) {
            return $this->renderFoundErrorAsJson();
        }

        $member = $this->getRepo("AppBundle:User")->find($memberId);

        if ($member == null) {
            return $this->renderErrorAsJson("No member was found with the given id.");
        } else if (!$group->getUsers()->contains($member)) {
            return $this->renderErrorAsJson("The member does not belong to the given group.");
        } else if ($member == $this->getUser()) {
            return $this->renderErrorAsJson("You can't remove yourself from the group.");
        }

        $group->removeUser($member);

        $em = $this->getManager();
        $em->persist($group);
        $em->flush();

        return new JsonResponse(array(
            "success" => true,
        ));
    }

    /**
     * @Route("/delete-group/{id}", name="delete_group")
     *
     * @param $id
     * @return Response
     */
    public function deleteGroupAction($id)
    {
        /** @var Group $group */
        $group = $this->getRepo("AppBundle:Group")->find($id);
        if (!$this->isValidGroup($group, array("not null", "is own"))) {
            return $this->renderFoundError("groups");
        }

        $em = $this->getManager();
        $em->remove($group);
        $em->flush();

        return $this->redirect($this->generateUrl("list_user_groups"));
    }

}
