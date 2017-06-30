<?php

namespace AppBundle\Controller;

use AppBundle\Document\Context;
use AppBundle\Document\DatabaseConnection;
use AppBundle\Document\Scale;
use AppBundle\Document\User;
use AppBundle\Helper\CommonUtils;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Security("has_role('ROLE_USER')")
 */
class ScaleController extends BaseController
{
    /**
     * @Route("/create-new-scale", name="create_new_scale")
     *
     * @param Request $request
     * @return Response
     */
    public function createNewScaleAction(Request $request)
    {
        $this->startStatisticsCounter();

        $errors = array();

        if ($request->isMethod("POST")) {
            $em = $this->getManager();
            $params = $this->getParameter('fca');

            $postData = $request->request;
            $subType = "";
            $column = "";

            /** @var DatabaseConnection $databaseConnection */
            $databaseConnection = $this->getRepo("AppBundle:DatabaseConnection")
                ->find($postData->get('databaseConnection'));
            if (!$databaseConnection) {
                $errors["databaseConnection"] = "You have to select a database connection.";
            }

            $tableName = $postData->get("tableName");
            if (!$tableName) {
                $errors["tableName"] = "You must select a table on which the scale to operate.";
            }

            $scaleName = $postData->get("scaleName");
            if (!$scaleName) {
                $errors["scaleName"] = "The scale must have a name.";
            }

            $scaleType = $postData->get("scaleType");
            if (!$scaleType) {
                $errors["scaleType"] = "You have to select a scale type.";
            } else if (!in_array($scaleType, array("nominal", "custom"))) {
                $errors["scaleType"] = "The scale type is not valid.";
            }

            switch ($scaleType) {
                case "nominal":
                    $column = $postData->get("column");
                    if (!$column) {
                        $errors["subType"] = "The nominal scale must have a column defined.";
                    }
                    $subType = $postData->get("subType");
                    if (!$subType) {
                        $errors["subType"] = "The nominal scale must be simple or custom.";
                    } else if ($subType == "custom") {
                        if (!$postData->has("nominalScaleValues")) {
                            $errors["nominalScaleValues"] = "The custom nominal scale must have nominal scale values.";
                        }
                    }
                    break;
                case "custom":
                default:
                    if (!$postData->has("objects")) {
                        $errors["objects"] = "The context must have at least one object.";
                    }
                    if (!$postData->has("attributes")) {
                        $errors["attributes"] = "The context must have at least one attribute.";
                    }
            }

            if (empty($errors)) {
                $scale = new Scale();
                $scale->setUser($this->getUser());
                $scale->setDatabaseConnection($databaseConnection);
                $scale->setTable($tableName);
                $scale->setName($scaleName);
                $scale->setType($scaleType);

                $context = new Context();
                $context->setName($scaleName);
                $context->setDimCount(2);
                $context->setIsPublic(false);
                $context->setScale($scale);

                switch ($scaleType) {
                    case "nominal":
                        $data = array(
                            'column' => $column,
                        );

                        if ($subType == "simple") {
                            $databaseConnection = $this->getRepo("AppBundle:DatabaseConnection")
                                ->find($postData->get("databaseConnectionId"));
                            $tableData = $this->getTableData($databaseConnection, $tableName);
                            $values = $tableData[$column];
                        } else {
                            $values = $postData->get("nominalScaleValues");
                            $data['nominalScaleValues'] = $values;
                        }

                        $scale->setData($data);

                        $objects = array();
                        $relationPairs = array();
                        foreach ($values as $index => $value) {
                            $objects[] = $column . " == '" . $value . "'";
                            $relationPairs[] = array($index, $index);
                        }

                        $context->setDimension(0, $objects);
                        $context->setDimension(1, $objects);
                        $context->setRelations($relationPairs);

                        break;
                    case "custom":
                    default:
                        $dimensions = array_slice($params['dimensionsPlural'], 0, 2);

                        for ($index = 0; $index < $context->getDimCount(); $index++) {
                            $paramName = $dimensions[$index];
                            $elements = $postData->get($paramName, array());
                            foreach ($elements as $elem) {
                                $context->addElement($index, CommonUtils::trim($elem));
                            }
                        }

                        foreach ($postData->get('relation_tuples', array()) as $tuple) {
                            $parts = explode("###", $tuple);
                            $relation = array();

                            for ($index = 0; $index < 2; $index++) {
                                $elemName = CommonUtils::trim($parts[$index]);
                                $elemId = array_search($elemName, $context->getDimension($index));
                                $relation[] = $elemId;
                            }

                            $context->addRelation($relation);
                        }
                }

                $context->setContextFile(null);
                $fileName = uniqid() . ".cxt";
                $context->setContextFileName($fileName);

                $contextService = $this->get("app.context_service");
                $contextService->generateContextFile($context);

                $context->setScale($scale);
                $scale->setContext($context);

                if (!$contextService->canComputeConcepts($context)) {
                    $errors["context"] = "The context is too big to compute its concepts.";
                }

                $concepts = $contextService->generateConcepts($context);
                $context->setConcepts($concepts);

                if (!$contextService->canComputeConceptLattice($context)) {
                    $errors["context"] = "The context is too big to compute its concept lattice.";
                }

                $conceptLattice = $contextService->generateConceptLattice($context);
                $context->setConceptLattice($conceptLattice);

                if (empty($errors)) {
                    $em->persist($context);
                    $em->persist($scale);
                    $em->flush();

                    $this->stopCounterAndLogStatistics("create scale", $context);

                    return $this->redirect($this->generateUrl("view_scale", array(
                        "id" => $scale->getId(),
                    )));
                }
            }
        }

        return $this->render('@App/Scale/createNewScale.html.twig', array(
            'activeMenu' => "my_scales",
            'errors' => $errors,
        ));
    }

    /**
     * @Route("/my-scales", name="list_user_scales")
     *
     * @return Response
     */
    public function listUserScalesAction()
    {
        /** @var User $user */
        $user = $this->getUser();
        $scales = $user->getScales();

        return $this->render('@App/Scale/listUserScales.html.twig', array(
            'activeMenu' => "my_scales",
            'scales' => $scales,
        ));
    }

    /**
     * @Route("/view-scale/{id}", name="view_scale")
     *
     * @param $id
     * @return Response
     */
    public function viewScaleAction($id)
    {
        /** @var Scale $scale */
        $scale = $this->getRepo("AppBundle:Scale")->find($id);

        if (!$this->isValidScale($scale, array("not null", "can view"))) {
            return $this->renderFoundError("my_scales");
        }

        $tableData = $this->getTableData($scale->getDatabaseConnection(), $scale->getTable());

        return $this->render("@App/Scale/scale.html.twig", array(
            'scale' => $scale,
            'tableData' => $tableData,
            'activeMenu' => "my_scales",
        ));
    }

    /**
     * @Route("/apply-scale/{id}", name="apply_scale")
     *
     * @param $id
     * @param Request $request
     * @return Response
     */
    public function applyScaleAction($id, Request $request)
    {
        /** @var Scale $scale */
        $scale = $this->getRepo("AppBundle:Scale")->find($id);

        if (!$this->isValidScale($scale, array("not null", "can view"))) {
            return $this->renderFoundError("my_scales");
        }

        $column = $request->query->get("objectColumn");

        return $this->render("@App/Scale/scaleContextConceptLattice.html.twig", array(
            'activeMenu' => "my_scales",
            'scale' => $scale,
            'column' => $column,
        ));
    }

    /**
     * @Route("/get-scale-concept-lattice-data/{id}/column/{column}", name="get_scale_concept_lattice_data")
     *
     * @param $id
     * @param $column
     * @return JsonResponse
     */
    public function getScaleConceptLatticeDataAction($id, $column)
    {
        $contextService = $this->container->get("app.context_service");
        /** @var Scale $scale */
        $scale = $this->getRepo("AppBundle:Scale")->find($id);

        if (!$this->isValidScale($scale, array("not null", "can view"))) {
            return $this->renderFoundErrorAsJson();
        }

        $tableData = $this->getTableData($scale->getDatabaseConnection(), $scale->getTable());
        $objects = array();
        $attributes = $scale->getContext()->getDimension(1);
        $newRelations = array();
        $assocArray = array();
        foreach ($scale->getContext()->getDimension(0) as $index => $object) {
            $assocArray[$index] = array();
        }

        $language = new ExpressionLanguage();
        foreach ($tableData['data'] as $row) {
            $objects[] = $row[$column];

            foreach ($scale->getContext()->getDimension(0) as $index => $expression) {
                $result = $language->evaluate($expression, $row);
                if ($result) {
                    $assocArray[$index][] = $row[$column];
                    break;
                }
            }
        }
        sort($objects);
        $objectToIndex = array_flip($objects);

        foreach ($scale->getContext()->getRelations() as $relation) {
            foreach ($assocArray[$relation[0]] as $object) {
                $newRelations[] = array($objectToIndex[$object], $relation[1]);
            }
        }

        $newContext = new Context(true);
        $newContext->setDimCount(2);
        $newContext->setDimension(0, $objects);
        $newContext->setDimension(1, $attributes);
        $newContext->setRelations($newRelations);

        $fileName = $contextService->generateTempFileName("cxt");
        $newContext->setContextFileName($fileName);
        $contextService->generateContextFile($newContext);
        $concepts = $contextService->generateConcepts($newContext);
        $newContext->setConcepts($concepts);
        $conceptLattice = $contextService->generateConceptLattice($newContext);
        $newContext->setConceptLattice($conceptLattice);

        $parsedConceptLattice = $contextService->generateParsedConceptLattice($newContext);
        $parsedConceptLattice["analogicalComplexes"] = $contextService->generateWeakAnalogicalProportions($newContext);

        return new JsonResponse($parsedConceptLattice);
    }

    /**
     * @Route("/get-table-data", name="get_table_data")
     */
    public function getTableDataAction(Request $request)
    {
        $databaseConnectionId = $request->query->get("databaseConnectionId");
        /** @var DatabaseConnection $databaseConnection */
        $databaseConnection = $this->getRepo("AppBundle:DatabaseConnection")->find($databaseConnectionId);

        $tableName = $request->query->get("table");

        return new JsonResponse(array(
            "success" => true,
            "data" => array(
                "tableData" => $this->getTableData($databaseConnection, $tableName)
            )
        ));
    }

    /**
     * @param DatabaseConnection $databaseConnection
     * @param $tableName
     * @return array
     */
    private function getTableData(DatabaseConnection $databaseConnection, $tableName)
    {
        $config = new Configuration();
        $connectionParams = array(
            'dbname' => $databaseConnection->getName(),
            'user' => $databaseConnection->getUsername(),
            'password' => $databaseConnection->getPassword(),
            'host' => $databaseConnection->getHost() . ':' . $databaseConnection->getPort(),
            'driver' => ($databaseConnection->getType() == "mysql" ? 'pdo_mysql' : ''),
        );

        $conn = DriverManager::getConnection($connectionParams, $config);
        $sql = "SELECT * FROM " . $tableName;
        $stmt = $conn->query($sql);

        $tableData = array(
            "columns" => array(),
        );
        if ($stmt->rowCount() != 0) {
            $tableData["data"] = $stmt->fetchAll();
            $tableData["columns"] = array_keys($tableData["data"][0]);
        }

        return $tableData;
    }
}
