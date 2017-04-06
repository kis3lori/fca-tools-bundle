<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ConceptLatticeController extends BaseController
{

    /**
     * @Route("/generate-concept-lattice/{id}", name="generate_concept_lattice")
     *
     * @param $id
     * @return Response
     */
    public function generateConceptLatticeAction($id)
    {
        $this->startStatisticsCounter();

        $em = $this->getManager();
        $context = $em->getRepository("AppBundle:Context")->find($id);

        if (!$this->isValidContext($context, array("not null", "is own", "is dyadic", "has concepts", "can compute concept lattice"))) {
            return $this->renderFoundError("my_contexts");
        }

        $contextService = $this->get("app.context_service");
        $conceptLattice = $contextService->generateConceptLattice($context);
        $context->setConceptLattice($conceptLattice);

        $em->persist($context);
        $em->flush();

        $this->stopCounterAndLogStatistics("generate concept lattice", $context);

        return $this->redirect($this->generateUrl("view_context", array(
            "id" => $context->getId(),
        )));
    }

    /**
     * @Route("/view-concept-lattice/{id}", name="view_concept_lattice")
     *
     * @param $id
     * @return Response
     */
    public function viewConceptLatticeAction($id)
    {
        $context = $this->getRepo("AppBundle:Context")->find($id);

        if (!$this->isValidContext($context, array("not null", "can view", "has concept lattice"))) {
            return $this->renderFoundError("contexts");
        }

        return $this->render("@App/ConceptLattice/conceptLattice.html.twig", array(
            'context' => $context,
            'activeMenu' => "context",
        ));
    }

    /**
     * @Route("/get-concept-lattice-data/{id}", name="get_concept_lattice_data")
     *
     * @param $id
     * @return Response
     */
    public function getConceptLatticeDataAction($id)
    {
        $context = $this->getRepo("AppBundle:Context")->find($id);

        if (!$this->isValidContext($context, array("not null", "can view", "has concept lattice"))) {
            return $this->renderFoundError("contexts");
        }

        $contextService = $this->get("app.context_service");
        $parsedConceptLattice = $contextService->generateParsedConceptLattice($context);

        return new JsonResponse($parsedConceptLattice);
    }

    /**
     * @Route("/view-locked-concept-lattice/{id}/type/{lockType}", name="view_locked_concept_lattice")
     *
     * @param string $id
     * @param string $lockType
     * @param Request $request
     * @return Response
     */
    public function viewLockedConceptLatticeAction($id, $lockType, Request $request)
    {
        $context = $this->getRepo("AppBundle:Context")->find($id);

        if (!$this->isValidContext($context, array("not null", "can view", "has concepts", "is triadic"))) {
            return $this->renderFoundError("contexts");
        }

        $contextService = $this->get("app.context_service");
        $lockedElements = $request->query->get("lock");
        $lockableElements = array_values($contextService->computeLockableElements($context));

        return $this->render("@App/ConceptLattice/lockedConceptLattice.html.twig", array(
            'context' => $context,
            'lockType' => $lockType,
            'lockedElements' => $lockedElements,
            'activeMenu' => "contexts",
            'lockableElements' => $lockableElements,
            'viewMode' => "1",
        ));
    }

    /**
     * @Route("/get-locked-concept-lattice-data/{id}/type/{lockType}", name="get_locked_concept_lattice_data")
     *
     * @param string $id
     * @param string $lockType
     * @param Request $request
     * @return Response
     */
    public function getLockedConceptLatticeDataAction($id, $lockType, Request $request)
    {
        $this->startStatisticsCounter();

        $lockedElements = $request->query->get("lock");
        $context = $this->getRepo("AppBundle:Context")->find($id);

        if (!$this->isValidContext($context, array("not null", "can view", "has concepts", "is triadic"))) {
            return $this->renderFoundError("contexts");
        }

        $contextService = $this->get("app.context_service");

        $dyadicContext = $contextService->generateLockedContext($context, $lockType, $lockedElements);

        if (!$dyadicContext) {
            return new JsonResponse(array(
                "success" => false,
                "error" => "Unable to generate the locked dyadic context, most likely because it is too large.",
            ));
        }

        $dyadicContext = $contextService->computeAssociatedConcepts($dyadicContext, $context, $lockType);
        $parsedConceptLattice = $contextService->generateParsedConceptLattice($dyadicContext);
        $parsedConceptLattice = $contextService->attachTriConcepts($parsedConceptLattice, $context);
        $parsedConceptLattice["lock"] = $lockedElements;

        $this->stopCounterAndLogStatistics("generate locked concept lattice", $context, null, $dyadicContext);

        return new JsonResponse($parsedConceptLattice);
    }

    /**
     * @Route("/large-context/view-concept-lattice/{id}/type/{lockType}", name="view_large_context_concept_lattice")
     *
     * @param string $id
     * @param string $lockType
     * @param Request $request
     * @return Response
     */
    public function viewLargeContextConceptLatticeAction($id, $lockType, Request $request)
    {
        $context = $this->getRepo("AppBundle:Context")->find($id);

        if (!$this->isValidContext($context, array("not null", "can view", "not has concepts", "is triadic"))) {
            return $this->renderFoundError("contexts");
        }

        $contextService = $this->get("app.context_service");
        $lockedElements = $request->query->get("lock");

        // TODO: Remove this after validation
        $dyadicContext = $contextService->generateLockedContext($context, $lockType, $lockedElements);
        if ($dyadicContext == null) {
            return $this->renderError(
                "Unable to generate the locked dyadic context, most likely because it is too large.",
                "contexts"
            );
        }

        return $this->render("@App/ConceptLattice/lockedConceptLattice.html.twig", array(
            'context' => $context,
            'lockType' => $lockType,
            'lockedElements' => $lockedElements,
            'activeMenu' => "contexts",
            'lockableElements' => array(),
            'viewMode' => "2",
        ));
    }

    /**
     * @Route("/large-context/get-concept-lattice-data/{id}/type/{lockType}", name="get_large_context_concept_lattice_data")
     *
     * @param string $id
     * @param string $lockType
     * @param Request $request
     * @return Response
     */
    public function getLargeContextConceptLatticeDataAction($id, $lockType, Request $request)
    {
        $this->startStatisticsCounter();

        $lockedElements = $request->query->get("lock");
        $context = $this->getRepo("AppBundle:Context")->find($id);

        if (!$this->isValidContext($context, array("not null", "can view", "not has concepts", "is triadic"))) {
            return $this->renderFoundError("contexts");
        }

        $contextService = $this->get("app.context_service");
        $dyadicContext = $contextService->generateLockedContext($context, $lockType, $lockedElements);

        if ($dyadicContext == null) {
            return new JsonResponse(array(
                "success" => false,
                "error" => "Unable to generate the locked dyadic context, most likely because it is too large.",
            ));
        }

        $dyadicContext = $contextService->generateAssociatedConcepts($dyadicContext, $context, $lockType);

        if (!$this->isValidContext($dyadicContext, array("not null", "has concept lattice", "is dyadic"))) {
            return $this->renderFoundError("contexts");
        }

        $parsedConceptLattice = $contextService->generateParsedConceptLattice($dyadicContext);
        $parsedConceptLattice["lock"] = $lockedElements;

        $this->stopCounterAndLogStatistics("generate locked concept lattice of large context",
            $context, null, $dyadicContext);

        return new JsonResponse($parsedConceptLattice);
    }

}
