<?php

namespace AppBundle\Service;


use AppBundle\Document\Context;
use AppBundle\Document\Statistics;
use AppBundle\Document\User;
use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class StatisticsService
{
    /**
     * @var User
     */
    private $user;

    /**
     * @var array
     */
    private $counters;

    /**
     * @var EntityManager
     */
    private $manager;

    /**
     * @var array
     */
    private $fcaParams;

    /**
     * @param $container ContainerInterface
     */
    public function __construct(ContainerInterface $container)
    {
        $this->user = null;
        $this->counters = array();
        $this->fcaParams = $container->getParameter("fca");
        $this->manager = $container->get("doctrine.odm.mongodb.document_manager");
        $token = $container->get('security.token_storage')->getToken();
        if ($token) {
            $this->user = $token->getUser();
        }
    }

    /**
     * Legend:
     *  - c  -> context
     *    - id -> id
     *    - rc -> relations count
     *    - dc -> dimensions count
     *  - d -> data
     *  - dc -> dyadic context
     *    - id -> id
     *    - rc -> relations count
     *    - dc -> dimensions count
     *
     * @param string $operation
     * @param float $duration
     * @param Context $context
     * @param array|null $data
     * @param Context|null $dyadicContext
     */
    private function logStatistics($operation, $duration, $context, $data = null, $dyadicContext = null)
    {
        $operationId = array_search($operation, $this->fcaParams["operations"]);
        $actualData = array(
            'c' => array(
                'id' => $context->getId(),
                'rc' => count($context->getRelations()),
                'dc' => array(),
            ),
        );

        if ($context->getConcepts() != null && !empty($context->getConcepts())) {
            $actualData['c']['cc'] = count($context->getConcepts());
        }

        foreach ($context->getDimensions() as $countKey => $dimension) {
            $actualData['c']['dc'][$countKey] = count($dimension);
        }

        if ($data) {
            $actualData['d'] = $data;
        }

        if ($dyadicContext) {
            $actualData['dc'] = array(
                'id' => $dyadicContext->getId(),
                'rc' => count($dyadicContext->getRelations()),
                'dc' => array(),
            );

            if ($dyadicContext->getConcepts() != null && !empty($dyadicContext->getConcepts())) {
                $actualData['dc']['cc'] = count($dyadicContext->getConcepts());
            }

            foreach ($dyadicContext->getDimensions() as $countKey => $dimension) {
                $actualData['dc']['dc'][$countKey] = count($dimension);
            }
        }

        $statistics = new Statistics();
        $statistics->setUser($this->user);
        $statistics->setOperation($operationId);
        $statistics->setDuration($duration);
        $statistics->setData($actualData);

        $this->manager->persist($statistics);
        $this->manager->flush();
    }

    /**
     * Start a counter
     */
    public function startStatisticsCounter()
    {
        $this->counters[] = microtime(true);
    }

    /**
     * Stop a counter and log the results
     *
     * @param string $operation
     * @param Context $context
     * @param array|null $data
     * @param Context|null $dyadicContext
     */
    public function stopCounterAndLogStatistics($operation, $context, $data = null, $dyadicContext = null)
    {
        $stopTime = microtime(true);
        $startTime = array_pop($this->counters);
        $diffTime = $stopTime - $startTime;

        $this->logStatistics($operation, $diffTime, $context, $data, $dyadicContext);
    }
}
