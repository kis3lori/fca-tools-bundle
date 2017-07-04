<?php

namespace AppBundle\Controller;

use AppBundle\Document\Feature;
use AppBundle\Document\Vote;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Security("has_role('ROLE_USER')")
 *
 * Class FeatureVotingController
 * @package AppBundle\Controller
 */
class FeatureVotingController extends BaseController
{
    /**
     * @Route("/feature-voting", name="feature_voting")
     *
     * @return Response
     */
    public function featureVotingAction()
    {
        $featureVotesArray = array();

        $features = $this->getRepo("AppBundle:Feature")->findAll();
        $votes = $this->getRepo("AppBundle:Vote")->findAll();
        $maxVotes = 1;

        /** @var Feature $feature */
        foreach ($features as $feature) {
            $nrVotes = 0;
            $userVoted = 0;

            /** @var Vote $vote */
            foreach ($votes as $vote) {
                if ($feature == $vote->getFeature()) {
                    $nrVotes++;

                    if ($vote->getUser() == $this->getUser()) {
                        $userVoted = 1;
                    }
                }
            }

            $name = $feature->getName();
            $maxVotes = max($maxVotes, $nrVotes);
            $featureVotesArray[$nrVotes . "/" . $name . "/" . $userVoted] = array(
                "feature" => $feature,
                "nrVotes" => $nrVotes,
                "userVoted" => $userVoted,
            );
        }

        foreach ($featureVotesArray as $key => $item) {
            $featureVotesArray[$key]["ratio"] = $item["nrVotes"] / $maxVotes;
        }

        krsort($featureVotesArray);

        return $this->render('@App/FeatureVoting/featuresList.html.twig', array(
            'activeMenu' => "feature_voting",
            'featureVotesArray' => array_values($featureVotesArray),
        ));
    }

    /**
     * @Route("/propose-a-new-feature", name="create_new_feature")
     *
     * @param Request $request
     * @return Response
     */
    public function createNewFeatureAction(Request $request)
    {
        $errors = array();

        if ($request->isMethod("POST")) {
            $postData = $request->request;

            if (!$postData->has("name") || $postData->get("name") == "") {
                $errors["name"] = "The name of the feature cannot be empty.";
            }

            if (!$postData->has("description") || $postData->get("description") == "") {
                $errors["description"] = "The description of the feature cannot be empty.";
            }

            if (empty($errors)) {
                $feature = new Feature();
                $feature->setName($postData->get("name"));
                $feature->setDescription($postData->get("description"));
                $feature->setUser($this->getUser());

                if ($this->getUser()->isAdmin()) {
                    $feature->setApproved(true);
                } else {
                    $feature->setApproved(null);
                }

                $em = $this->getManager();
                $em->persist($feature);
                $em->flush();

                return $this->redirect($this->generateUrl("feature_voting"));
            }
        }

        return $this->render("@App/FeatureVoting/addNewFeature.html.twig", array(
            'activeMenu' => "feature_voting",
            'errors' => $errors,
        ));
    }

    /**
     * @Route("/vote/{featureId}", name="toggle_vote")
     *
     * @param $featureId
     * @return Response
     */
    public function votingAction($featureId)
    {
        $em = $this->getManager();

        /** @var Feature $feature */
        $feature = $this->getRepo("AppBundle:Feature")->find($featureId);
        if ($feature == null) {
            return $this->renderError("No feature was found with the given id.", "feature_voting");
        }

        /** @var Vote $vote */
        foreach ($feature->getVotes() as $vote) {
            if ($vote->getUser() == $this->getUser()) {
                $em->remove($vote);
                $em->flush();

                return $this->redirect($this->generateUrl("feature_voting"));
            }
        }

        $vote = new Vote();
        $vote->setFeature($feature);
        $vote->setUser($this->getUser());

        $em->persist($vote);
        $em->flush();

        return $this->redirect($this->generateUrl("feature_voting"));
    }

    /**
     * @Security("has_role('ROLE_ADMIN')")
     * @Route("/delete-feature/{featureId}", name="deleteFeature")
     *
     * @param $featureId
     * @return Response
     */
    public function deleteFeatureAction($featureId)
    {
        $feature = $this->getRepo("AppBundle:Feature")->find($featureId);
        if ($feature !== null) {
            $em = $this->getManager();
            $em->remove($feature);

            $votes = $this->getRepo("AppBundle:Vote")->findBy(array('feature' => $featureId));
            foreach ($votes as $vote) {
                $em->remove($vote);
            }

            $em->flush();
        }

        return $this->redirect($this->generateUrl("feature_voting"));
    }

    /**
     * @Security("has_role('ROLE_ADMIN')")
     * @Route("/approve-feature/{featureId}", name="approve_feature")
     *
     * @param $featureId
     * @return Response
     */
    public function approveFeatureAction($featureId)
    {
        $feature = $this->getRepo("AppBundle:Feature")->find($featureId);
        if (!$feature->getApproved()) {
            $feature->setApproved(true);

            $em = $this->getManager();
            $em->persist($feature);
            $em->flush();
        }

        return $this->redirect($this->generateUrl("feature_voting"));
    }

    /**
     * @Security("has_role('ROLE_ADMIN')")
     * @Route("/reject-feature/{featureId}", name="reject_feature")
     *
     * @param $featureId
     * @return Response
     */
    public function rejectFeatureAction($featureId)
    {
        $feature = $this->getRepo("AppBundle:Feature")->find($featureId);
        if ($feature->getApproved()) {
            $feature->setApproved(false);

            $em = $this->getManager();
            $em->persist($feature);
            $em->flush();
        }

        return $this->redirect($this->generateUrl("feature_voting"));
    }
}