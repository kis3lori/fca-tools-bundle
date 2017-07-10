<?php

namespace AppBundle\Service;


use AppBundle\Document\ConceptLattice;
use AppBundle\Document\Context;

class GenerateLatticeService
{

    /**
     * @param $context Context
     * @return ConceptLattice
     */
    public function generateConceptLattice($context)
    {
        $conceptLattice = null;

        if ($context->getDimCount() == 2) {
            $conceptLattice = $this->generateDyadicConceptLattice($context);
        }

        return $conceptLattice;
    }

    /**
     * @param $context Context
     * @return ConceptLattice
     */
    public function generateDyadicConceptLattice($context)
    {
        $conceptLattice = new ConceptLattice();

        $conceptsByNrAttr = array();
        $allConcepts = $context->getConcepts();

        foreach ($allConcepts as $conceptId => $concept) {
            $nrItems = count($concept[1]);

            if (!isset($conceptsByNrAttr[$nrItems])) {
                $conceptsByNrAttr[$nrItems] = array($conceptId);
            } else {
                $conceptsByNrAttr[$nrItems][] = $conceptId;
            }
        }

        ksort($conceptsByNrAttr);

        $levels = array();
        $links = array();
        $linkedConcepts = array();
        $gatheredConcepts = array();
        $level = 1;

        foreach ($conceptsByNrAttr as $nrAttributes => $concepts) {
            foreach ($concepts as $conceptId) {
                $levels[$conceptId] = $level;
                $linkedConcepts[$conceptId] = array($conceptId);
            }

            if ($level > 1) {
                foreach ($concepts as $conceptId) {
                    $conceptsToParse = $gatheredConcepts;
                    while (!empty($conceptsToParse)) {
                        $conceptAboveId = array_pop($conceptsToParse);
                        $diff1 = array_diff($allConcepts[$conceptId][0], $allConcepts[$conceptAboveId][0]);
                        $diff2 = array_diff($allConcepts[$conceptAboveId][1], $allConcepts[$conceptId][1]);
                        $areConceptsLinked = empty($diff1) && empty($diff2);

                        if ($conceptAboveId != $conceptId && $areConceptsLinked) {
                            $visitedNodes[] = $conceptAboveId;
                            $links[] = array($conceptAboveId, $conceptId);
                            $linkedConcepts[$conceptId] = array_merge($linkedConcepts[$conceptId], $linkedConcepts[$conceptAboveId]);
                            $conceptsToParse = array_diff($conceptsToParse, $linkedConcepts[$conceptAboveId]);
                        }
                    }
                }
            }

            $gatheredConcepts = array_merge($gatheredConcepts, $concepts);

            $level++;
        }

        $conceptLattice->setLinks($links);
        $conceptLattice->setLevels($levels);
        $conceptLattice->setMinLevel(1);
        $conceptLattice->setMaxLevel($level - 1);
        $conceptLattice->setMaxLevelConceptId(end($conceptsByNrAttr)[0]);

        return $conceptLattice;
    }

    /**
     * Generate the parsed concept lattice of a context.
     * This representation of the concept lattice is used by the javascript to display it.
     *
     * @param $context Context
     * @return array
     */
	 public function generateParsedConceptLattice($context)
    {
        $parsedConceptLattice = array(
            'nodes' => array(),
            'links' => array(),
            'maxLevel' => $context->getConceptLattice()->getMaxLevel(),
            'lastNode' => $context->getConceptLattice()->getMaxLevelConceptId(),
        );

        foreach ($context->getConcepts() as $key => $concept) {
            $objects = array();
            $attributes = array();

            foreach ($concept[0] as $elemId) {
                $objects[] = $context->getElement(0, $elemId);
            }

            foreach ($concept[1] as $elemId) {
                $attributes[] = $context->getElement(1, $elemId);
            }

            sort($objects);
            sort($attributes);

            $nodeToAdd = array(
                'objects' => $objects,
                'attributes' => $attributes,
                'level' => $context->getConceptLattice()->getLevel($key),
            );

            if (isset($concept['o']) && $concept['o'] !== null) {
                $nodeToAdd['triadicConceptId'] = $concept['o'];
            } else if (isset($concept['c']) && $concept['c'] !== null && !empty($concept['c'])) {
                $nodeToAdd['triadicConcept'] = $concept['c'];
            }

            $parsedConceptLattice['nodes'][] = $nodeToAdd;
        }

        foreach ($context->getConceptLattice()->getLinks() as $link) {
            $parsedConceptLattice['links'][] = array(
                'source' => $link[0],
                'target' => $link[1],
            );
        }

        return $parsedConceptLattice;
    }

}