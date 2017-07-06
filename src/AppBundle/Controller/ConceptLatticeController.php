<?php

namespace AppBundle\Controller;

use AppBundle\Document\Context;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ConceptLatticeController extends BaseController
{

    /**
     * @Security("has_role('ROLE_USER')")
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

        $generateLatticeService = $this->get("app.generate_lattice_service");
        $conceptLattice = $generateLatticeService->generateConceptLattice($context);
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
        /** @var Context $context */
        $context = $this->getRepo("AppBundle:Context")->find($id);

        if (!$this->isValidContext($context, array("not null", "can view", "has concept lattice"))) {
            return $this->renderFoundError("contexts");
        }

        $generateLatticeService = $this->get("app.generate_lattice_service");
		$weakAnalogicalProportionsService = $this->get("app.weak_analogical_proportions_service");
		
        $parsedConceptLattice = $generateLatticeService->generateParsedConceptLattice($context);
        $parsedConceptLattice["analogicalComplexes"] = $weakAnalogicalProportionsService->generateWeakAnalogicalProportions($context);

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
        /** @var Context $context */
        $context = $this->getRepo("AppBundle:Context")->find($id);

        if (!$this->isValidContext($context, array("not null", "can view", "has concepts", "is triadic"))) {
            return $this->renderFoundError("contexts");
        }

        $triadicContextNavigationService = $this->get("app.triadic_context_navigation_service");
        $lockedElements = $request->query->get("lock");
        $lockableElements = array_values($triadicContextNavigationService->computeLockableElements($context));

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
        /** @var Context $context */
        $context = $this->getRepo("AppBundle:Context")->find($id);

        if (!$this->isValidContext($context, array("not null", "can view", "has concepts", "is triadic"))) {
            return $this->renderFoundError("contexts");
        }

        $triadicContextNavigationService = $this->get("app.triadic_context_navigation_service");
		$generateLatticeService = $this->get("app.generate_lattice_service");
        $dyadicContext = $triadicContextNavigationService->generateLockedContext($context, $lockType, $lockedElements);

        if (!$dyadicContext) {
            return new JsonResponse(array(
                "success" => false,
                "error" => "Unable to generate the locked dyadic context, most likely because it is too large.",
            ));
        }

        $dyadicContext = $triadicContextNavigationService->computeAssociatedConcepts($dyadicContext, $context, $lockType);
        $parsedConceptLattice = $generateLatticeService->generateParsedConceptLattice($dyadicContext);
        $parsedConceptLattice = $triadicContextNavigationService->attachTriConcepts($parsedConceptLattice, $context);
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

        $triadicContextNavigationService = $this->get("app.triadic_context_navigation_service");
        $lockedElements = $request->query->get("lock");

        // TODO: Remove this after validation
        $dyadicContext = $triadicContextNavigationService->generateLockedContext($context, $lockType, $lockedElements);
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
        /** @var Context $context */
        $context = $this->getRepo("AppBundle:Context")->find($id);

        if (!$this->isValidContext($context, array("not null", "can view", "not has concepts", "is triadic"))) {
            return $this->renderFoundError("contexts");
        }

        $triadicContextNavigationService = $this->get("app.triadic_context_navigation_service");
		$generateLatticeService = $this->get("app.generate_lattice_service");
        $dyadicContext = $triadicContextNavigationService->generateLockedContext($context, $lockType, $lockedElements);

        if ($dyadicContext == null) {
            return new JsonResponse(array(
                "success" => false,
                "error" => "Unable to generate the locked dyadic context, most likely because it is too large.",
            ));
        }

        $dyadicContext = $triadicContextNavigationService->generateAssociatedConcepts($dyadicContext, $context, $lockType);

        if (!$this->isValidContext($dyadicContext, array("not null", "has concept lattice", "is dyadic"))) {
            return $this->renderFoundError("contexts");
        }

        $parsedConceptLattice = $generateLatticeService->generateParsedConceptLattice($dyadicContext);
        $parsedConceptLattice["lock"] = $lockedElements;

        $this->stopCounterAndLogStatistics("generate locked concept lattice of large context",
            $context, null, $dyadicContext);

        return new JsonResponse($parsedConceptLattice);
    }

}
