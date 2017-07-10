<?php

namespace AppBundle\Controller;

class TemporalContextController extends BaseController
{

    /*
    /**
     * @Route("/temporal-context/{id}", name="temporal_context")
     *
     * @param $id
     * @return Response
     * /
    public function indexAction($id)
    {
        exit;

        $context = $this->getRepo("AppBundle:Context")->find($id);

        if (!$this->isValidContext($context, array("not null", "can view"))) {
            return $this->renderFoundError("contexts");
        }

        return $this->render('@App/TemporalContext/index.html.twig', array(
            'activeMenu' => "contexts",
            'context' => $context,
        ));
    }

    /**
     * @Route("/generate-temporal-evolution/{id}", name="generate_temporal_evolution")
     *
     * @param $id
     * @param $request
     * @return Response
     * /
    public function generateTemporalAction($id, Request $request)
    {
        exit;

        /** @var Context $context * /
        $context = $this->getRepo("AppBundle:Context")->find($id);

        if (!$this->isValidContext($context, array("not null", "can view"))) {
            return $this->renderFoundError("contexts");
        }

        $dimKey = $request->get("dimension");
        $contextService = $this->get("app.context_service");
		$generateLatticeService = $this->get("app.generate_lattice_service");

        foreach ($context->getDimension($dimKey) as $dimension) {
            $childContext = $contextService->generateChildContext($context, $dimKey, $dimension);
            $parsedConceptLattice = $generateLatticeService->generateParsedConceptLattice($childContext);

            var_dump($childContext);
            var_dump($parsedConceptLattice);
        }

        exit;

        return $this->render('@App/TemporalContext/index.html.twig', array(
            'activeMenu' => "contexts",
            'context' => $context,
        ));
    }
    */
}
