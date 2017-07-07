<?php
namespace AppBundle\Service;


use AppBundle\Document\ConceptLattice;
use AppBundle\Document\Context;
use AppBundle\Helper\CommonUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Kernel;

class GenerateContextFilesService extends ContextService {
	
    /**
     * Generate the ".csv" file of a context and save it in the given path.
     *
     * @param $context Context
     * @param $path
     */
    public function generateContextCsvFile($context, $path)
    {
        $data = "";

        foreach ($context->getRelations() as $relation) {
            $elementNames = array();
            foreach ($relation as $index => $elemId) {
                $elementNames[] = $context->getElement($index, $elemId);
            }

            $data .= implode(",", $elementNames) . "\n";
        }

        file_put_contents(
            $this->kernel->getRootDir() . "/../" . $path,
            $data
        );
    }
	
	/**
     * Generate and save the ".cxt" file of a context.
     *
     * @param $context Context
     */
	public function generateContextFile($context)
    {
        $data = "";

        $type = $context->getDimCount();
        if ($type == 2) {
            $type = "B";
        }

        $data .= $type . "\n\n";

        foreach ($context->getDimensions() as $dimension) {
            $data .= count($dimension) . "\n";
        }
        if ($type > 2) {
            $data .= count($context->getRelations()) . "\n";
        }
        $data .= "\n";

        foreach ($context->getDimensions() as $dimension) {
            $data .= implode("\n", $dimension) . "\n";
        }

        if ($context->getDimCount() == 2) {
            $matrix = array();
            foreach ($context->getDimension(0) as $key => $object) {
                $matrix[$key] = array();

                foreach ($context->getDimension(1) as $key2 => $attribute) {
                    $matrix[$key][$key2] = '.';
                }
            }

            foreach ($context->getRelations() as $relation) {
                $matrix[$relation[0]][$relation[1]] = 'X';
            }

            foreach ($context->getDimension(0) as $key => $object) {
                $matrix[$key] = implode("", $matrix[$key]);
            }

            $data .= implode("\n", $matrix) . "\n";
        } else {
            foreach ($context->getRelations() as $relation) {
                $elemIds = array();
                for ($index = 0; $index < $context->getDimCount(); $index++) {
                    $elemIds[] = $relation[$index] + 1;
                }

                $data .= implode(" ", $elemIds) . "\n";
            }
        }

        file_put_contents(
            $this->kernel->getRootDir() . "/../" . $context->getBaseFilePath() . $context->getContextFileName(),
            $data
        );
    }
	
	/**
     * Generate a simplified version of the ".csv" file that is used by the data-peeler algorithm
     * and save it in the given path.
     *
     * @param $context Context
     * @param $path
     */
    public function generateContextSimplifiedCsvFile($context, $path)
    {
        $data = "";

        foreach ($context->getRelations() as $relation) {
            $data .= implode(",", $relation) . "\n";
        }

        file_put_contents(
            $this->kernel->getRootDir() . "/../" . $path,
            $data
        );
    }
 }