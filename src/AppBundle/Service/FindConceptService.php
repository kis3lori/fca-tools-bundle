<?php
namespace AppBundle\Service;


use AppBundle\Document\ConceptLattice;
use AppBundle\Document\Context;
use AppBundle\Helper\CommonUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Kernel;

class FindConceptService extends ContextService {
/**
     * Find a concept using the ASP programming language.
     *
     * @param Context $context
     * @param array $constraints
     * @return array
     */
    public function findConcept($context, $constraints)
    {
        $lastIndex = $context->getDimCount() - 1;
        $aspProgram = "";
        foreach ($context->getRelations() as $relation) {
            $aspProgram .= "rel(" . implode(",", $relation) . ").\n";
        }

        $aspProgram .= "index(0 .. " . $lastIndex . ").\n";
        for ($index = 0; $index < $context->getDimCount(); $index++) {
            $aspProgram .= "set(" . $index . ", 0 .. " . (count($context->getDimension($index)) - 1) . ").\n";
        }

        $aspProgram .= "in(I,X):- set(I,X), index(I), not out(I,X).\n";
        $aspProgram .= "out(I,X):- set(I,X), index(I), not in(I,X).\n";

        $inParts = array();
        $xParts = array();
        for ($index = 0; $index < $context->getDimCount(); $index++) {
            $inParts[] = "in(" . $index . ",X" . $index . ")";
            $xParts[] = "X" . $index;
        }

        $relPart = "rel(" . implode(",", $xParts) . ")";
        $aspProgram .= ":- " . implode(", ", $inParts) . ", not " . $relPart . ".\n";

        for ($index = 0; $index < $context->getDimCount(); $index++) {
            $otherParts = array();

            for ($index2 = 0; $index2 < $context->getDimCount(); $index2++) {
                if ($index != $index2) {
                    $otherParts[] = "in(" . $index2 . ",X" . $index2 . ")";
                }
            }
            $aspProgram .= "exc(" . $index . ",X" . $index . "):- "
                . implode(", ", $otherParts)
                . ", not " . $relPart . ", set(" . $index . ",X" . $index . ").\n";
        }

        $aspProgram .= ":- out(I,X), index(I), not exc(I,X).\n";
        $aspProgram .= ":- out(I,X), index(I), in(I,X).\n";
        $aspProgram .= ":- I=0 .." . $lastIndex . ", #count {E,in : in(I,E)} <1.\n";

        $aspProgram .= "#show in/2.\n";
        $aspProgram .= "#show out/2.\n";

        foreach ($constraints as $constraint) {
            $aspProgram .= $constraint['state'] . "(" . $constraint['dimension'] . "," . $constraint['index'] . ").\n";
        }

        $dataFileName = $this->generateTempFileName("lp");
        $resultFileName = $this->generateTempFileName("txt");

        $dataFilePath = $this->kernel->getRootDir() . "/../bin/temp/find_triadic_concept/input/" . $dataFileName;
        $resultFilePath = $this->kernel->getRootDir() . "/../bin/temp/find_triadic_concept/output/" . $resultFileName;
        file_put_contents($resultFilePath, "");

        // Execute the ASP script
        file_put_contents($dataFilePath, $aspProgram);

        $command = "clingo " . $dataFilePath . " --enum-mode cautious --quiet=0,2,2 --verbose=0 > " . $resultFilePath;

        $dimensionsCount = array();
        foreach ($context->getDimensions() as $countKey => $dimension) {
            $dimensionsCount[$countKey] = count($dimension);
        }

        $this->statisticsService->startStatisticsCounter();
        exec("cd " . $this->scriptDir . " && " . $command . " 2>&1", $errorOutput);
        $this->statisticsService->stopCounterAndLogStatistics("find concept step script", $context, array(
            "cs" => $constraints,
        ));

        $result = file_get_contents($resultFilePath);

        unlink($dataFilePath);
        unlink($resultFilePath);

        $lines = explode("\n", CommonUtils::trim($result));
        $size = count($lines);
        $lastElement = null;
        $state = $lines[$size - 1];
        if (CommonUtils::trim($state) == "UNSATISFIABLE") {
            return null;
        } else {
            $lastElement = CommonUtils::trim($lines[$size - 2]);

            if ($lastElement) {
                $additionalConstraints = explode(" ", CommonUtils::trim($lastElement));

                foreach ($additionalConstraints as $constraint) {
                    $parts = explode("(", CommonUtils::trim($constraint));
                    $state = $parts[0];
                    $data = substr($parts[1], 0, strlen($parts[1]) - 1);

                    $parts = explode(",", $data);
                    $dimKey = (int)$parts[0];
                    $elemKey = (int)$parts[1];

                    $constraints[] = array(
                        'dimension' => $dimKey,
                        'index' => $elemKey,
                        'state' => $state,
                    );
                }
            }
        }

        $constraints = array_values(array_intersect_key($constraints, array_unique(array_map('serialize', $constraints))));

        return $constraints;
    }
}