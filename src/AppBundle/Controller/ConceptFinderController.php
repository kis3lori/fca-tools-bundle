<?php

namespace AppBundle\Controller;

use AppBundle\Document\ConceptFinderBookmark;
use AppBundle\Document\Context;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ConceptFinderController extends BaseController
{

    /**
     * @Route("/context/{id}/find-concept", name="concept_finder")
     *
     * @param string $id
     * @param Request $request
     * @return Response
     */
    public function conceptFinderAction($id, Request $request)
    {
        $this->startStatisticsCounter();
        $context = $this->getRepo("AppBundle:Context")->find($id);

        if (!$this->isValidContext($context, array("not null", "can view"))) {
            return $this->renderFoundError("contexts");
        }

        $searchContext = $this->getLatestSearchContext($request, $context);
        $currentState = $this->getActiveSate($context, $searchContext, $request);

        $mappedConstraints = array();
        for ($index = 0; $index < $context->getDimCount(); $index++) {
            $mappedConstraints[$index] = array();
        }
        foreach ($currentState['constraints'] as $constraint) {
            $mappedConstraints[$constraint['dimension']][$constraint['index']] = $constraint['state'];
        }

        $lockedConceptLatticeRouteName = null;
        if ($context->getDimCount() == 3) {
            if (!empty($context->getConcepts())) {
                $lockedConceptLatticeRouteName = "view_locked_concept_lattice";
            } else {
                $lockedConceptLatticeRouteName = "view_large_context_concept_lattice";
            }
        }

        $request->getSession()->set("searchContext", $searchContext);

        $this->stopCounterAndLogStatistics("find concept step", $context, array(
            "constraints" => $currentState["constraints"],
            "history" => $searchContext['history']
        ));

        return $this->render("@App/ConceptFinder/conceptFinder.html.twig", array(
            'context' => $context,
            'activeMenu' => "contexts",
            'lockedConceptLatticeRouteName' => $lockedConceptLatticeRouteName,
            'searchContext' => $searchContext,
            'state' => $currentState,
            'mappedConstraints' => $mappedConstraints,
        ));
    }

    /**
     * @Route("/context/{id}/find-concept/update", name="concept_finder_update")
     *
     * @param string $id
     * @param Request $request
     * @return Response
     */
    public function conceptFinderUpdateAction($id, Request $request)
    {
        $this->startStatisticsCounter();
        $context = $this->getRepo("AppBundle:Context")->find($id);

        if (!$this->isValidContext($context, array("not null", "can view"))) {
            return $this->renderFoundError("contexts");
        }

        $searchContext = $this->getLatestSearchContext($request, $context);
        $currentState = $this->getActiveSate($context, $searchContext, $request);

        $rawConstraint = $request->request->get("constraint");

        if (count($rawConstraint) == 3 && in_array($rawConstraint['state'], array("in", "out"))) {
            $constraint = array(
                "dimension" => (int)$rawConstraint['dimension'],
                "index" => (int)$rawConstraint['index'],
                "state" => $rawConstraint['state'],
            );

            if (!in_array($constraint, $currentState['constraints'])) {
                $currentState['constraints'][] = $constraint;

                $findConceptService = $this->get("app.find_concept_service");
                $result = $findConceptService->findConcept($context, $currentState['constraints']);

                if ($result == null) {
                    $currentState['status'] = "unsatisfiable";
                } else {
                    $currentState['status'] = "intermediary";
                    $currentState['constraints'] = $result;
                    $currentState['foundConcept'] = $this
                        ->getConceptBasedOnConstraints($context, $currentState['constraints']);

                    if ($currentState['foundConcept'] != null) {
                        $currentState['status'] = "done";
                    }
                }

                $searchContext['history'][] = $constraint;
                $searchContext['states'][] = $currentState;
            }
        }

        $request->getSession()->set("searchContext", $searchContext);

        $this->stopCounterAndLogStatistics("find concept step", $context, array(
            "constraints" => $currentState["constraints"],
            "history" => $searchContext['history']
        ));

        return new JsonResponse(array(
            "status" => "success",
            "data" => $currentState
        ));
    }

    /**
     * @param Context $context
     * @param array $constraints
     * @return null|array
     */
    private function getConceptBasedOnConstraints($context, $constraints)
    {
        $nrConstraints = count($constraints);
        $nrElements = 0;
        foreach ($context->getDimensions() as $dimension) {
            $nrElements += count($dimension);
        }

        if ($nrConstraints == $nrElements) {
            $foundConcept = array();
            for ($index = 0; $index < $context->getDimCount(); $index++) {
                $foundConcept[$index] = array();
            }

            foreach ($constraints as $constraint) {
                if ($constraint['state'] == "in") {
                    $foundConcept[$constraint['dimension']][] = $constraint['index'];
                }
            }

            return $foundConcept;
        }

        return null;
    }

    /**
     * @Route("/context/{id}/find-concept/bookmark", name="concept_finder_bookmark")
     *
     * @param string $id
     * @param Request $request
     * @return Response
     */
    public function conceptFinderBookmarkAction($id, Request $request)
    {
        if (!$this->getUser()) {
            return new JsonResponse(array(
                "status" => "success",
                "error" => "You have to be logged in to manage bookmarks.",
            ));
        }

        $em = $this->getManager();
        $context = $em->getRepository("AppBundle:Context")->find($id);

        if (!$this->isValidContext($context, array("not null", "can view"))) {
            return $this->renderFoundError("contexts");
        }

        $searchContext = $this->getLatestSearchContext($request, $context);


        unset($searchContext['bookmarks']);
        $name = $request->query->get("name", "");

        $conceptFinderBookmark = new ConceptFinderBookmark();
        $conceptFinderBookmark->setUser($this->getUser());
        $conceptFinderBookmark->setContext($context);
        $conceptFinderBookmark->setName($name);
        $conceptFinderBookmark->setSearchContext($searchContext);

        $em->persist($conceptFinderBookmark);
        $em->flush();

        $searchContext["bookmarks"] = $this->getUserBookmarks($context);
        $request->getSession()->set("searchContext", $searchContext);

        return new JsonResponse(array(
            "status" => "success",
            "data" => $searchContext["bookmarks"],
        ));
    }

    /**
     * @Route("/context/{id}/find-concept/delete-bookmark", name="concept_finder_delete_bookmark")
     *
     * @param string $id
     * @param Request $request
     * @return Response
     */
    public function conceptFinderDeleteBookmarkAction($id, Request $request)
    {
        if (!$this->getUser()) {
            return new JsonResponse(array(
                "status" => "success",
                "error" => "You have to be logged in to manage bookmarks.",
            ));
        }

        $em = $this->getManager();
        $context = $em->getRepository("AppBundle:Context")->find($id);

        if (!$this->isValidContext($context, array("not null", "can view"))) {
            return $this->renderFoundError("contexts");
        }

        $searchContext = $this->getLatestSearchContext($request, $context);

        if (!$request->query->has("id")) {
            return new JsonResponse(array(
                "success" => false,
                "error" => "Missing bookmark id.",
            ));
        }

        $id = $request->query->get("id");
        $conceptFinderBookmark = $em->getRepository("AppBundle:ConceptFinderBookmark")
            ->find($id);
        $em->remove($conceptFinderBookmark);
        $em->flush();

        $searchContext["bookmarks"] = $this->getUserBookmarks($context);
        $request->getSession()->set("searchContext", $searchContext);

        return new JsonResponse(array(
            "status" => "success",
            "data" => $searchContext["bookmarks"],
        ));
    }

    /**
     * @Route("/context/{id}/find-concept/reset", name="concept_finder_reset")
     *
     * @param string $id
     * @param Request $request
     * @return Response
     */
    public function conceptFinderResetAction($id, Request $request)
    {
        $context = $this->getRepo("AppBundle:Context")->find($id);

        if (!$this->isValidContext($context, array("not null", "can view"))) {
            return $this->renderFoundError("contexts");
        }

        $searchContext = $this->resetContext($request, $context);
        $currentState = $this->getActiveSate($context, $searchContext, $request);

        return new JsonResponse(array(
            "status" => "success",
            "data" => $currentState,
        ));
    }

    /**
     * Reset the search context
     *
     * @param Request $request
     * @param Context $context
     * @return array
     */
    private function resetContext($request, $context)
    {
        $searchContext = $this->getDefaultSearchContext($context);

        $request->getSession()->set("searchContext", $searchContext);

        return $searchContext;
    }

    /**
     * @param Context $context
     * @return array
     */
    private function getDefaultSearchContext($context)
    {

        return array(
            'id' => $context->getId(),
            'history' => array(),
            'states' => array(),
            'bookmarks' => $this->getUserBookmarks($context),
        );
    }

    /**
     * @param Context $context
     * @return array
     */
    private function getUserBookmarks($context)
    {
        if (!$this->getUser()) return array();

        $bookmarks = $this->getRepo("AppBundle:ConceptFinderBookmark")
            ->findBy(array(
                "user.id" => $this->getUser()->getId(),
                "context.id" => $context->getId(),
            ));

        $bookmarkArray = array();
        foreach ($bookmarks as $entity) {
            $bookmarkArray[] = array(
                "id" => $entity->getId(),
                "name" => $entity->getName(),
                "searchContext" => $entity->getSearchContext(),
            );
        }

        return $bookmarkArray;
    }

    /**
     * @param Request $request
     * @param Context $context
     * @return array|null
     */
    private function getLatestSearchContext($request, $context)
    {
        $searchContext = null;

        if ($request->getSession()->has("searchContext")) {
            $searchContext = $request->getSession()->get("searchContext");
        }

        if (!isset($searchContext['id']) || $searchContext['id'] != $context->getId()) {
            $searchContext = $this->resetContext($request, $context);
        }

        $bookmarkId = $request->request->get("bookmarkId", false);
        if ($bookmarkId) {
            $bookmarks = $searchContext["bookmarks"];

            $mainBookmark = null;
            foreach ($bookmarks as $bookmark) {
                if ($bookmark["id"] == $bookmarkId) {
                    $mainBookmark = $bookmark;
                    break;
                }
            }

            $searchContext = $mainBookmark['searchContext'];
            $searchContext["bookmarks"] = $bookmarks;
        }

        if ($request->request->get("reset", false)) {
            $activeStateIndex = $request->request->get("activeState");
            $searchContext['history'] = array_slice($searchContext['history'], 0, $activeStateIndex + 1);
            $searchContext['states'] = array_slice($searchContext['states'], 0, $activeStateIndex + 1);
        }

        if ($searchContext == null) {
            $this->getDefaultSearchContext($context);
        }

        return $searchContext;
    }

    /**
     * @param Context $context
     * @param array $searchContext
     * @param Request $request
     * @return array|null
     */
    private function getActiveSate($context, $searchContext, $request)
    {
        $currentState = null;

        if (count($searchContext['states']) > 0) {
            $currentState = end($searchContext['states']);
        }

        if ($currentState == null) {
            $findConceptService = $this->get("app.find_concept_service");

            $firstState = null;
            if ($request->getSession()->has("firstState")) {
                $firstStateFromSession = $request->getSession()->get("firstState");
                if ($firstStateFromSession["id"] == $context->getId()) {
                    $firstState = $firstStateFromSession;
                    unset($firstState["id"]);
                }
            }

            if ($firstState == null) {
                $firstState = array(
                    "id" => $context->getId(),
                    "status" => "start",
                    "constraints" => $findConceptService->findConcept($context, array()),
                    "foundConcept" => null,
                );
                $request->getSession()->set("firstState", $firstState);
            }

            $currentState = $firstState;
        }

        return $currentState;
    }
}
