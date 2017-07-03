<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;

class VotingController extends BaseController
{
    /**
     * @Route("/voting", name="voting_feature")
     *
     * @return Response
     */
    public function votingFeature()
    {
        $features = $this->getRepo("AppBundle:Feature")->findAll();
        $votes = $this->getRepo("AppBundle:Vote")->findAll();
        $voteArray = array();

        $user = $this->getUser()->getId();

        foreach ($features as $feature) {
            $nrVotes = 0;
            $userVoted = 0;
            foreach ($votes as $vote) {
                if ($feature->getId() == $vote->getFeature()) {
                    $nrVotes++;
                    if ($vote->getUser() == $user) {
                        $userVoted = 1;
                    }
                }
            }
            $title = $feature->getTitle();
            $voteArray[$nrVotes . "/" . $title . "/" . $userVoted] = $feature;
        }
        krsort($voteArray);

        $roles = $this->getUser()->getRoles();
        $admin = false;
        foreach ($roles as $role) {
            if ($role == "ROLE_ADMIN") {
                $admin = true;
            }
        }

        return $this->render('@App/Feature/voting.html.twig', array(
            'activeMenu' => "voting_feature",
            'voteArray' => $voteArray,
            'admin' => $admin
        ));
    }

    /**
     * @Route("/vote/{featureId}/{userId}", name="vote")
     *
     * @param $id
     * @return Response
     */
    public function votingAction($featureId, $userId)
    {
        $em = $this->getManager();

        $features = $this->getRepo("AppBundle:Feature")->findAll();
        $votes = $this->getRepo("AppBundle:Vote")->findAll();
        foreach ($features as $feature) {
            $userVoted = 0;
            foreach ($votes as $vote) {
                if ($feature->getId() == $vote->getFeature()) {
                    if ($vote->getUser() == $userId) {
                        $userVoted = 1;
                    }
                }
            }
        }

        if ($userVoted == 0) {
            $vote = new Vote();

            $vote->setFeature($featureId);
            $vote->setUser($userId);

            $em->persist($vote);
            $em->flush();
        }

        $voteArray = $this->buildVoteArray($userId);

        $roles = $this->getUser()->getRoles();
        $admin = false;
        foreach ($roles as $role) {
            if ($role == "ROLE_ADMIN") {
                $admin = true;
            }
        }

        return $this->render("@App/Feature/voting.html.twig", array(
            'activeMenu' => "voting_feature",
            'voteArray' => $voteArray,
            'admin' => $admin
        ));
    }


    /**
     * @Route("/newFeature", name="newFeature")
     */
    public function newFeature()
    {
        return $this->render("@App/Feature/addNewFeature.html.twig", array(
            'activeMenu' => "voting_feature",
        ));
    }

    public function buildVoteArray($userId)
    {
        $voteArray = array();

        $features = $this->getRepo("AppBundle:Feature")->findAll();
        $votes = $this->getRepo("AppBundle:Vote")->findAll();
        foreach ($features as $feature) {
            $nrVotes = 0;
            $userVoted = 0;
            foreach ($votes as $vote) {
                if ($feature->getId() == $vote->getFeature()) {
                    $nrVotes++;
                    if ($vote->getUser() == $userId) {
                        $userVoted = 1;
                    }
                }
            }
            $title = $feature->getTitle();
            $voteArray[$nrVotes . "/" . $title . "/" . $userVoted] = $feature;
        }
        krsort($voteArray);
        return $voteArray;
    }

    /**
     * @Route("/add_new_feature/{userId}", name="add_new_feature")
     */
    public function addNewFeature($userId)
    {
        $roles = $this->getUser()->getRoles();
        $admin = false;
        foreach ($roles as $role) {
            if ($role == "ROLE_ADMIN") {
                $admin = true;
            }
        }

        $request = $this->getRequest();
        if ($request->isMethod("POST")) {
            $postData = $request->request;
            $feature = new Feature();
            $feature->setTitle($postData->get("title"));
            $feature->setDescription($postData->get("description"));
            if ($admin) {
                $feature->setApproved(true);
            } else {
                $feature->setApproved(false);
            }

            $em = $this->getManager();
            $em->persist($feature);
            $em->flush();
        }

        return $this->render("@App/Feature/voting.html.twig", array(
            'activeMenu' => "voting_feature",
            'voteArray' => $this->buildVoteArray($userId),
            'admin' => $admin
        ));
    }

    /**
     * @Route("/deleteFeature/{featureId}/{userId}", name="deleteFeature")
     */
    public function deleteFeature($featureId, $userId)
    {
        $feature = $this->getRepo("AppBundle:Feature")->find($featureId);
        if ($feature !== null) {
            $em = $this->getManager();
            $em->remove($feature);
            $votes = $this->getRepo("AppBundle:Vote")->findBy(array('feature' => $featureId));

            if ($votes !== null) {
                foreach ($votes as $vote) {
                    $em->remove($vote);
                }
            }
            $em->flush();
        }

        $roles = $this->getUser()->getRoles();
        $admin = false;
        foreach ($roles as $role) {
            if ($role == "ROLE_ADMIN") {
                $admin = true;
            }
        }

        return $this->render("@App/Feature/voting.html.twig", array(
            'activeMenu' => "voting_feature",
            'voteArray' => $this->buildVoteArray($userId),
            'admin' => $admin
        ));
    }

    /**
     * @Route("/approveFeature/{featureId}/{userId}", name="approveFeature")
     */
    public function approveFeature($featureId, $userId)
    {
        $em = $this->getManager();
        $feature = $this->getRepo("AppBundle:Feature")->find($featureId);
        if (!$feature->getApproved()) {
            $feature->setApproved(true);
            $em->flush();
        }

        $roles = $this->getUser()->getRoles();
        $admin = false;
        foreach ($roles as $role) {
            if ($role == "ROLE_ADMIN") {
                $admin = true;
            }
        }

        return $this->render("@App/Feature/voting.html.twig", array(
            'activeMenu' => "voting_feature",
            'voteArray' => $this->buildVoteArray($userId),
            'admin' => $admin
        ));
    }

    /**
     * @Route("/rejectFeature/{featureId}/{userId}", name="rejectFeature")
     */
    public function rejectFeature($featureId, $userId)
    {
        $em = $this->getManager();
        $feature = $this->getRepo("AppBundle:Feature")->find($featureId);
        if ($feature->getApproved()) {
            $feature->setApproved(false);
            $em->flush();
        }

        $roles = $this->getUser()->getRoles();
        $admin = false;
        foreach ($roles as $role) {
            if ($role == "ROLE_ADMIN") {
                $admin = true;
            }
        }

        return $this->render("@App/Feature/voting.html.twig", array(
            'activeMenu' => "voting_feature",
            'voteArray' => $this->buildVoteArray($userId),
            'admin' => $admin
        ));
    }
}