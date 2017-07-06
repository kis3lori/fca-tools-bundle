<?php
namespace AppBundle\Service;


use AppBundle\Document\ConceptLattice;
use AppBundle\Document\Context;
use AppBundle\Helper\CommonUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Kernel;

class WeakAnalogicalProportionsService extends ContextService {
	
	/**
     * Find analogical complexes using the ASP programming language.
     *
     * @param Context $context
     * @return array
     */
	public function generateWeakAnalogicalProportions($context)
    {
        $aspProgram = "";
        foreach ($context->getRelations() as $relation) {
            $aspProgram .= "rel(" . implode(",", $relation) . ").\n";
        }

        $aspProgram .= <<<EOT
rawobject(O):- rel(O,_).
sameobject(O1,O2):- rawobject(O1), rawobject(O2), rel(O1,A):rel(O2,A), rel(O2,A):rel(O1,A), O1<O2.
object(O):- rawobject(O), not sameobject(X,O):rawobject(X).
attribute(A):- rel(_,A).

notrel(O,A):- object(O), attribute(A), not rel(O,A).

aptype(1..4).
optype(1..4).
foptype(0..4). %0 if attribute not selected

opp(1,4;;4,1;;2,3;;3,2;;0,5;;5,0).
to1(1,3;4;5).  to1(2,2;4;5).  to1(3,1;3;5). to1(4,1;2;5).
to0(I,K):- to1(I,J), opp(J,K).

pattern(O,A,T,U):- notrel(O,A), to0(T,U).
pattern(O,A,T,U):- rel(O,A), to1(T,U).
notpattern(O,A,T,U):- rel(O,A), to0(T,U).
notpattern(O,A,T,U):- notrel(O,A), to1(T,U).

1{ class(obj,O,X): foptype(X)}1:- object(O).

acomp(obj,O,T):- class(obj,O,T), optype(T).
acomp(obj,T):- acomp(obj,O,T).
:- optype(T), not acomp(obj,T).


minptypeobj(Min,T):-  acomp(obj,Min,T),  not acomp(obj,X,T):object(X):X<Min.

:- minptypeobj(Min1,1), minptypeobj(Min2,T), optype(T), T>1, Min2<Min1.
:- minptypeobj(Min2,2), minptypeobj(Min3,3), Min3<Min2.

same(O,X):- sameobject(O,X), acomp(obj,O,T).

1{ acomp(att,A,U): pattern(O1,A,1,U):pattern(O2,A,2,U)}:- 	minptypeobj(O1,1), minptypeobj(O2,2), optype(U).

imp_object(X,T):-  object(X), optype(T), optype(U), acomp(att,A,U), notpattern(X,A,T,U).
admissible_object(X):-  object(X), optype(T), not imp_object(X,T).

imp_attribute(Y,U):-  attribute(Y), optype(U), acomp(obj,O,T), notpattern(O,Y,T,U).
admissible_attribute(Y):-  attribute(Y), optype(T), not imp_attribute(Y,T).

:- imp_object(X,T), acomp(obj,X,T).
:- imp_attribute(Y,U), acomp(att,Y,U).

:- admissible_object(X), not acomp(obj,X,U):optype(U), object(X).
:- admissible_attribute(Y), not acomp(att,Y,T):optype(T), attribute(Y).

#hide.
#show acomp/3.
EOT;

        $dataFileName = $this->generateTempFileName("lp");
        $resultFileName = $this->generateTempFileName("txt");

        $dataFilePath = $this->kernel->getRootDir() . "/../bin/temp/find_triadic_concept/input/" . $dataFileName;
        $resultFilePath = $this->kernel->getRootDir() . "/../bin/temp/find_triadic_concept/output/" . $resultFileName;
        file_put_contents($resultFilePath, "");

        // Execute the ASP script
        file_put_contents($dataFilePath, $aspProgram);

        $command = "clingo3 " . $dataFilePath . " --verbose=0 -n 0 > " . $resultFilePath;

        exec("cd " . $this->scriptDir . " && " . $command . " 2>&1", $errorOutput);

        $result = file_get_contents($resultFilePath);

        unlink($dataFilePath);
        unlink($resultFilePath);

        $lines = explode("\n", CommonUtils::trim($result));
        unset($lines[count($lines) - 1]);

        $weakAnalogicalProportions = array();
        foreach ($lines as $line) {
            $lineParts = explode(" ", trim($line));
            $objectSets = array(array(), array(), array(), array());
            $attributeSets = array(array(), array(), array(), array());

            foreach ($lineParts as $linePart) {
                $linePart = substr($linePart, 6, strlen($linePart) - 7);
                $values = explode(",", $linePart);

                if ($values[0] == "obj") {
                    $objectSets[(int) $values[2] - 1][] = (int) $values[1];
                } else {
                    $attributeSets[(int) $values[2] - 1][] = (int) $values[1];
                }
            }

            $indicesToTake = array(array(2, 3), array(1, 3), array(0, 2), array(0, 1));
            $concepts = array();
            for ($index = 0; $index < 4; $index++) {
                $attributesFromComplex = array_unique(array_merge(
                    $attributeSets[$indicesToTake[$index][0]],
                    $attributeSets[$indicesToTake[$index][1]]
                ));

                $concepts[$index] = array();
                $concepts[$index][0] = $this->computeExtent($context, $attributesFromComplex);
                $concepts[$index][1] = $this->computeIntent($context, $concepts[$index][0]);
            }

            $conceptIds = array();
            foreach ($context->getConcepts() as $contextConceptId => $contextConcept) {
                foreach ($concepts as $index => $concept) {
                    if ($concept == $contextConcept) {
                        $conceptIds[$index] = $contextConceptId;
                    }
                }
            }

            $weakAnalogicalProportions[] = $conceptIds;
        }

        return $weakAnalogicalProportions;
    }
}