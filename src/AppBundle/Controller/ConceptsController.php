<?php

namespace AppBundle\Controller;

use AppBundle\Document\Context;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Security("has_role('ROLE_USER')")
 *
 * Class ConceptsController
 * @package AppBundle\Controller
 */
class ConceptsController extends BaseController
{

    /**
     * @Route("/generate-concepts/{id}", name="generate_concepts")
     *
     * @param $id
     * @return Response
     */
    public function generateConceptsAction($id)
    {
        $this->startStatisticsCounter();

        $em = $this->getManager();
        /** @var Context $context */
        $context = $em->getRepository("AppBundle:Context")->find($id);

        if (!$this->isValidContext($context, array("not null", "is own", "can compute concepts"))) {
            return $this->renderFoundError("my_contexts");
        }

        $generateConceptService = $this->get("app.generate_concept_service");
        $concepts = $generateConceptService->generateConcepts($context);
        $context->setConcepts($concepts);

        $em->persist($context);
        $em->flush();

        $this->stopCounterAndLogStatistics("import context", $context);

        return $this->redirect($this->generateUrl("view_context", array(
            "id" => $context->getId(),
        )));
    }

}
