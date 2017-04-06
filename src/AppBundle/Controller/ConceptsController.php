<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;

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
        $context = $em->getRepository("AppBundle:Context")->find($id);

        if (!$this->isValidContext($context, array("not null", "is own", "can compute concepts"))) {
            return $this->renderFoundError("my_contexts");
        }

        $contextService = $this->get("app.context_service");
        $concepts = $contextService->generateConcepts($context);
        $context->setConcepts($concepts);

        $em->persist($context);
        $em->flush();

        $this->stopCounterAndLogStatistics("import context", $context);

        return $this->redirect($this->generateUrl("view_context", array(
            "id" => $context->getId(),
        )));
    }

}
