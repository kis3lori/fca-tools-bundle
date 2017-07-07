<?php

namespace AppBundle\Controller;

use AppBundle\Document\Context;
use AppBundle\Document\DatabaseConnection;
use AppBundle\Document\Scale;
use AppBundle\Document\User;
use AppBundle\Helper\CommonUtils;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ParameterBag;
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

        $tab = "select-database";
        $errors = array();

        if ($request->isMethod("POST")) {
            $postData = $request->request;

            $databaseConnectionId = $postData->get('databaseConnectionId');
            $tableName = $postData->get("tableName");
            $scaleName = $postData->get("scaleName");
            $scaleType = $postData->get("scaleType");

            /** @var DatabaseConnection $databaseConnection */
            $databaseConnection = $this->getRepo("AppBundle:DatabaseConnection")->find($databaseConnectionId);

            $errors = $this->validateDatabaseConnection($errors, $databaseConnection);
            if (empty($errors)) {
                $tab = "describe-scale";
                $errors = $this->validateGenericScale($errors, $tableName, $scaleName, $scaleType);
            }

            if (empty($errors)) {
                $tab = "define-scale";
                $errors = $this->validateScaleType($errors, $scaleType, $postData);
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
                $scale->setContext($context);

                $errors = $this->updateContextByScaleType($context, $scale, $postData, $errors);

                if (empty($errors)) {
                    $fileName = CommonUtils::generateFileName("cxt");
                    $contextService = $this->get("app.context_service");
                    $errors = $contextService->computeConceptsAndConceptLattice($context, $fileName, $errors);

                    if (empty($errors)) {
                        $em = $this->getManager();
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
        }

        return $this->render('@App/Scale/createNewScale.html.twig', array(
            'activeMenu' => "my_scales",
            'errors' => $errors,
            'tab' => $tab,
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

        $databaseConnectionService = $this->get("app.database_connection_service");
        $tableData = $databaseConnectionService
            ->getTableData($scale->getDatabaseConnection(), $scale->getTable());

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
     * @param $column string
     * @return JsonResponse
     */
    public function getScaleConceptLatticeDataAction($id, $column)
    {
        /** @var Scale $scale */
        $scale = $this->getRepo("AppBundle:Scale")->find($id);

        if (!$this->isValidScale($scale, array("not null", "can view"))) {
            return $this->renderFoundErrorAsJson();
        }

        $context = $this->generateContextFromScale($scale, $column);
        $fileName = CommonUtils::generateFileName("cxt");
        $errors = array();

        $contextService = $this->container->get("app.context_service");
        $errors = $contextService->computeConceptsAndConceptLattice($context, $fileName, $errors);

        if (!empty($errors)) {
            return $this->renderErrorAsJson($errors[0]);
        }

        $generateLatticeService = $this->container->get("app.generate_lattice_service");
        $parsedConceptLattice = $generateLatticeService->generateParsedConceptLattice($context);
        $wapService = $this->container->get("app.weak_analogical_proportions_service");
        $parsedConceptLattice["analogicalComplexes"] = $wapService->generateWeakAnalogicalProportions($context);

        return new JsonResponse($parsedConceptLattice);
    }

    /**
     * @Route("/get-tables", name="get_tables")
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getTablesAction(Request $request)
    {
        $databaseConnectionId = $request->query->get("databaseConnectionId");
        /** @var DatabaseConnection $databaseConnection */
        $databaseConnection = $this->getRepo("AppBundle:DatabaseConnection")->find($databaseConnectionId);

        $databaseConnectionService = $this->get("app.database_connection_service");
        $tables = $databaseConnectionService->getTables($databaseConnection);

        return new JsonResponse(array(
            "success" => true,
            "data" => array(
                "tables" => $tables
            )
        ));
    }

    /**
     * @Route("/get-table-data", name="get_table_data")
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getTableDataAction(Request $request)
    {
        $databaseConnectionId = $request->query->get("databaseConnectionId");
        /** @var DatabaseConnection $databaseConnection */
        $databaseConnection = $this->getRepo("AppBundle:DatabaseConnection")->find($databaseConnectionId);

        $tableName = $request->query->get("table");
        $databaseConnectionService = $this->get("app.database_connection_service");
        $tableData = $databaseConnectionService->getTableData($databaseConnection, $tableName);

        return new JsonResponse(array(
            "success" => true,
            "data" => array(
                "tableData" => $tableData
            )
        ));
    }

    /**
     * @param $errors array
     * @param $databaseConnection DatabaseConnection
     * @return mixed
     */
    private function validateDatabaseConnection($errors, $databaseConnection)
    {
        if (!$databaseConnection) {
            $errors["databaseConnectionId"] = "You have to select a database connection.";
        }

        return $errors;
    }

    /**
     * @param $errors array
     * @param $tableName string
     * @param $scaleName string
     * @param $scaleType string
     * @return mixed
     */
    private function validateGenericScale($errors, $tableName, $scaleName, $scaleType)
    {
        if (!$tableName) {
            $errors["tableName"] = "You must select a table on which the scale to operate.";
        }

        if (!$scaleName) {
            $errors["scaleName"] = "The scale must have a name.";
        }

        if (!$scaleType) {
            $errors["scaleType"] = "You have to select a scale type.";
        } else if (!in_array($scaleType, array("nominal", "custom"))) {
            $errors["scaleType"] = "The scale type is not valid.";
        }

        return $errors;
    }

    /**
     * @param $errors array
     * @param $scaleType string
     * @param $postData ParameterBag
     * @return mixed
     */
    private function validateScaleType($errors, $scaleType, $postData)
    {
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

        return $errors;
    }

    /**
     * Generate a context from a scale having the given column as main objects.
     *
     * @param $scale Scale
     * @param $column string
     * @return Context
     */
    private function generateContextFromScale($scale, $column)
    {
        $databaseConnectionService = $this->get("app.database_connection_service");
        $tableData = $databaseConnectionService->getTableData($scale->getDatabaseConnection(), $scale->getTable());
        $objects = array();
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

        $newRelations = array();
        foreach ($scale->getContext()->getRelations() as $relation) {
            foreach ($assocArray[$relation[0]] as $object) {
                $newRelations[] = array($objectToIndex[$object], $relation[1]);
            }
        }

        $newContext = new Context(true);
        $newContext->setDimCount(2);
        $newContext->setDimension(0, $objects);
        $newContext->setDimension(1, $scale->getContext()->getDimension(1));
        $newContext->setRelations($newRelations);

        return $newContext;
    }

    /**
     * @param $context Context
     * @param $scale Scale
     * @param $postData ParameterBag
     * @param $errors array
     * @return array
     */
    private function updateContextByScaleType($context, $scale, $postData, $errors)
    {
        $params = $this->getParameter('fca');

        switch ($scale->getType()) {
            case "nominal":
                $column = $postData->get("column");
                $subType = $postData->get("subType");

                $data = array(
                    'column' => $column,
                );

                if ($subType == "simple") {
                    $databaseConnectionService = $this->get("app.database_connection_service");
                    $tableData = $databaseConnectionService
                        ->getTableData($scale->getDatabaseConnection(), $scale->getTable());

                    $values = array();
                    foreach ($tableData['data'] as $row) {
                        $values[] = $row[$column];
                    }

                    $values = array_unique($values);
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

        return $errors;
    }
}
