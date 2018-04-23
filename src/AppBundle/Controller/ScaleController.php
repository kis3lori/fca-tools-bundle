<?php

namespace AppBundle\Controller;

use AppBundle\Document\Context;
use AppBundle\Document\DatabaseConnection;
use AppBundle\Document\Scale;
use AppBundle\Document\User;
use AppBundle\Helper\CommonUtils;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Security("has_role('ROLE_USER')")
 */
class ScaleController extends BaseController
{

    private $tempCsvFilePath = "../bin/temp/scale/files/";

    /**
     * @Route("/create-new-scale", name="create_new_scale")
     *
     * @param Request $request
     * @return Response
     */
    public function createNewScaleAction(Request $request)
    {
        $this->startStatisticsCounter();

        $databaseConnectionService = $this->get("app.database_connection_service");
        $csvTableService = $this->get("app.csv_table_service");
        $tab = "select-source";
        $errors = array();
        $fillData = array();

        if ($request->getSession()->has("errors")) {
            $errors = $request->getSession()->remove("errors");
        }

        if ($request->getSession()->has("csvFileName")) {
            $csvFileName = $request->getSession()->get("csvFileName");
            $fileContent = file_get_contents($this->tempCsvFilePath . $csvFileName);

            try {
                $fillData['tableData'] = $csvTableService->getTableDataFromFileContents($fileContent);
            } catch (\Exception $exception) {
                $request->getSession()->remove("csvFileName");
                $errors["csvFileName"] = "Unable to parse the csv file. Please check the correct format and try again.";
            }

            if (empty($errors)) {
                $tab = "describe-scale";

                $request->request->set("sourceType", "csv");
                $request->request->set("csvFileName", $csvFileName);

                $request->getSession()->remove("csvFileName");
            }
        }

        if (empty($errors) && $request->isMethod("POST")) {
            $scaleService = $this->get("app.scale_service");
            $postData = $request->request;

            $sourceType = $postData->get("sourceType");
            $csvFileName = $postData->get("csvFileName");
            $databaseConnectionId = $postData->get('databaseConnectionId');
            $tableName = $postData->get("tableName");
            $scaleName = $postData->get("scaleName");
            $scaleType = $postData->get("scaleType");

            $errors = $scaleService->validateSourceType($errors, $sourceType, $databaseConnectionId, $csvFileName);

            $databaseConnection = null;
            if (empty($errors)) {
                if ($sourceType == "database") {
                    /** @var DatabaseConnection $databaseConnection */
                    $databaseConnection = $this->getRepo("AppBundle:DatabaseConnection")->find($databaseConnectionId);
                    $errors = $scaleService->validateDatabaseConnection($errors, $databaseConnection);
                }
            }

            if (empty($errors)) {
                $tab = "describe-scale";

                if ($sourceType == "database") {
                    $fillData['tables'] = $databaseConnectionService->getTables($databaseConnection);
                } else {
                    $fillData['tables'] = array($tableName);
                }

                $errors = $scaleService->validateGenericScale($errors, $tableName, $scaleName, $scaleType, $fillData['tables']);
            }

            if (empty($errors)) {
                $tab = "define-scale";

                if ($sourceType == "database") {
                    $fillData['tableData'] = $databaseConnectionService->getTableData($databaseConnection, $tableName);
                } else {
                    $fileContent = file_get_contents($this->tempCsvFilePath . $csvFileName);
                    try {
                        $fillData['tableData'] = $csvTableService->getTableDataFromFileContents($fileContent);
                    } catch (\Exception $exception) {
                        $request->getSession()->remove("csvFileName");
                        $errors["csvFileName"] = "Unable to parse the csv file. Please check the correct format and try again.";
                    }
                }

                $errors = $scaleService->validateScaleType($errors, $scaleType, $postData, $fillData['tableData']);
            }

            if (empty($errors)) {
                $scale = new Scale();
                $scale->setUser($this->getUser());

                if ($sourceType == "database") {
                    $scale->setDatabaseConnection($databaseConnection);
                } else {
                    $tempCsvFile = new File($this->tempCsvFilePath . $csvFileName);
                    $tempCsvFile->move(substr($scale->getBaseFilePath(), 4), $csvFileName);
                    $scale->setCsvFileName($csvFileName);
                }

                $scale->setTable($tableName);
                $scale->setName($scaleName);
                $scale->setType($scaleType);

                $context = new Context();
                $context->setName($scaleName);
                $context->setDimCount(2);
                $context->setIsPublic(false);
                $context->setScale($scale);
                $scale->setContext($context);

                $errors = $scaleService->updateContextByScaleType($context, $scale, $postData, $errors);

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
            'fillData' => $fillData,
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

        $scaleService = $this->get("app.scale_service");
        $tableData = $scaleService->getTableData($scale);

        return $this->render("@App/Scale/scale.html.twig", array(
            'scale' => $scale,
            'tableData' => $tableData,
            'activeMenu' => "my_scales",
        ));
    }

    /**
     * @Route("/delete-scale/{id}", name="delete_scale")
     *
     * @param $id
     * @return Response
     */
    public function deleteScaleAction($id)
    {
        /** @var Scale $scale */
        $scale = $this->getRepo("AppBundle:Scale")->find($id);

        if (!$this->isValidScale($scale, array("not null", "can view", "is own"))) {
            return $this->renderFoundError("my_scales");
        }

        $em = $this->getManager();
        $em->remove($scale);
        $em->flush();

        return $this->redirect($this->generateUrl("list_user_scales"));
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

        $scaleService = $this->get("app.scale_service");
        $context = $scaleService->generateContextFromScale($scale, $column);
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
     * @Route("/upload-temp-csv-file", name="upload_temp_csv_file")
     *
     * @param Request $request
     * @return Response
     */
    public function uploadTempCsvFileAction(Request $request)
    {
        $errors = array();

        if ($request->isMethod("POST")) {
            /** @var UploadedFile $uploadedFile */
            $uploadedFile = $request->files->get("file");
            $extension = null;

            if ($uploadedFile == null) {
                $errors['file'] = "The file is missing or an error occurred during it's upload.";
            } else if ($uploadedFile->getClientSize() > 10000000) {
                $errors['file'] = "The file size cannot exceed 10MB.";
            } else {
                $extension = $uploadedFile->getClientOriginalExtension();
                if (!in_array($extension, array("csv"))) {
                    $errors['file'] = "Only 'csv' files are currently supported.";
                }
            }

            if (empty($errors)) {
                $fileName = uniqid() . ".csv";
                $uploadedFile->move($this->tempCsvFilePath, $fileName);

                $request->getSession()->set("csvFileName", $fileName);

                return $this->redirect($this->generateUrl("create_new_scale"));
            }
        }

        $request->getSession()->set("errors", $errors);

        return $this->redirect($this->generateUrl("create_new_scale"));
    }

}
