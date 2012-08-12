<?php
require('../include/session.inc.php');

/*
   This file is part of CoDev-Timetracking.

   CoDev-Timetracking is free software: you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.

   CoDev-Timetracking is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with CoDev-Timetracking.  If not, see <http://www.gnu.org/licenses/>.
*/

require('../path.inc.php');

require('include/super_header.inc.php');

include_once('constants.php');

class ProjectInfoController extends Controller {

   /**
    * Initialize complex static variables
    * @static
    */
   public static function staticInit() {
      // Nothing special
   }

   protected function display() {
      if(isset($_SESSION['userid'])) {
         $user = UserCache::getInstance()->getUser($_SESSION['userid']);

         $teamList = $user->getTeamList();
         if (0 != count($teamList)) {
            // define the list of tasks the user can display
            // All projects from teams where I'm a Developper or Manager AND Observers
            $dTeamList = $user->getDevTeamList();
            $devProjList = (0 == count($dTeamList)) ? array() : $user->getProjectList($dTeamList);
            $managedTeamList = $user->getManagedTeamList();
            $managedProjList = (0 == count($managedTeamList)) ? array() : $user->getProjectList($managedTeamList);
            $oTeamList = $user->getObservedTeamList();
            $observedProjList = (0 == count($oTeamList)) ? array() : $user->getProjectList($oTeamList);
            $projList = $devProjList + $managedProjList + $observedProjList;

            if(isset($_GET['projectid'])) {
               $projectid = Tools::getSecureGETIntValue('projectid');
               $_SESSION['projectid'] = $projectid;
            }
            else if(isset($_SESSION['projectid'])) {
               $projectid = $_SESSION['projectid'];
            }
            else {
               $projectsid = array_keys($projList);
               $projectid = $projectsid[0];
            }

            $this->smartyHelper->assign('projects', SmartyTools::getSmartyArray($projList,$projectid));

            if (in_array($projectid, array_keys($projList))) {
               $isManager = true; // TODO
               $this->smartyHelper->assign("isManager", $isManager);

               $project = ProjectCache::getInstance()->getProject($projectid);

               $this->smartyHelper->assign("versionsOverview", $this->getVersionsOverview($project));

               if ($isManager) {
                  $this->smartyHelper->assign("versionsDetailedMgr", $this->getVersionsDetailedMgr($project));
               }

               $projectVersionList = $project->getVersionList();
               $this->smartyHelper->assign("versionsDetailed", $this->getVersionsDetailed($projectVersionList));

               $this->smartyHelper->assign("versionsIssues", $this->getVersionsIssues($projectVersionList));

               $this->smartyHelper->assign("currentIssuesInDrift", $this->getCurrentIssuesInDrift($projectVersionList, $isManager));

               $this->smartyHelper->assign("resolvedIssuesInDrift", $this->getResolvedIssuesInDrift($projectVersionList, $isManager));
            } else if ($projectid) {
               $this->smartyHelper->assign("error", "Sorry, you are not allowed to view the details of this project");
            }
         } else {
            $this->smartyHelper->assign("error", "Sorry, you need to be member of a Team to access this page.");
         }
      }
   }

   /**
    * Get versions overview
    * @param Project $project
    * @return mixed[]
    */
   private function getVersionsOverview(Project $project) {
      $versionsOverview = NULL;
      $projectVersionList = $project->getVersionList();
      foreach ($projectVersionList as $pv) {
         if (NULL == $pv) {
            continue;
         }

         $valuesMgr = $pv->getDriftMgr();

         $values = $pv->getDrift();

         $vdate =  $pv->getVersionDate();
         $date = "";
         if (is_numeric($vdate)) {
            $date = date("Y-m-d",$vdate);
         }

         $versionsOverview[] = array(
            'name' => $pv->name,
            'date' => $date,
            'progressMgr' => round(100 * $pv->getProgressMgr()),
            'progress' => round(100 * $pv->getProgress()),
            'driftMgrColor' => IssueSelection::getDriftColor($valuesMgr['percent']),
            'driftMgr' => round(100 * $valuesMgr['percent']),
            'driftColor' => IssueSelection::getDriftColor($values['percent']),
            'drift' => round(100 * $values['percent'])
         );
      }

      $drift = $project->getDrift();
      $driftMgr = $project->getDriftMgr();
      $versionsOverview[] = array(
         'progressMgr' => round(100 * $project->getProgressMgr()),
         'progress' => round(100 * $project->getProgress()),
         'driftMgrColor' => IssueSelection::getDriftColor($driftMgr['percent']),
         'driftMgr' => round(100 * $driftMgr['percent']),
         'driftColor' => IssueSelection::getDriftColor($drift['percent']),
         'drift' => round(100 * $drift['percent'])
      );

      return $versionsOverview;
   }

   /**
    * Get detailed mgr versions
    * @param Project $project
    * @return mixed[]
    */
   private function getVersionsDetailedMgr(Project $project) {
      $versionsDetailedMgr = NULL;
      $totalEffortEstimMgr = 0;
      $totalElapsed = 0;
      $totalBacklogMgr = 0;
      $totalReestimatedMgr = 0;
      $totalDriftMgr = 0;

      $projectVersionList = $project->getVersionList();

      // TOTAL (all Versions together)
      $allProjectVersions = new ProjectVersion($project->id, "Total");
      $issueList = $project->getIssues();
      foreach ($issueList as $issue) {
         $allProjectVersions->addIssue($issue->bugId);
      }
      $projectVersionList[] = $allProjectVersions;

      foreach ($projectVersionList as $pv) {
         $totalEffortEstimMgr += $pv->mgrEffortEstim;
         $totalElapsed += $pv->elapsed;
         $totalBacklogMgr += $pv->durationMgr;
         $totalReestimatedMgr += $pv->getReestimatedMgr();
         //$formatedList  = implode( ',', array_keys($pv->getIssueList()));

         $valuesMgr = $pv->getDriftMgr();
         $totalDriftMgr += $valuesMgr['nbDays'];

         $versionsDetailedMgr[] = array(
            'name' => $pv->name,
            //'progress' => round(100 * $pv->getProgress()),
            'effortEstim' => $pv->mgrEffortEstim,
            'reestimated' => $pv->getReestimatedMgr(),
            'elapsed' => $pv->elapsed,
            'backlog' => $pv->durationMgr,
            'driftColor' => IssueSelection::getDriftColor($valuesMgr['percent']),
            'drift' => round($valuesMgr['nbDays'],2)
         );
      }

      return $versionsDetailedMgr;
   }

   /**
    * Get detailed versions
    * @param ProjectVersion[] $projectVersionList
    * @return mixed[]
    */
   private function getVersionsDetailed(array $projectVersionList) {
      $versionsDetailed = NULL;
      $totalDrift = 0;
      $totalEffortEstim = 0;
      $totalElapsed = 0;
      $totalBacklog = 0;
      $totalReestimated = 0;

      foreach ($projectVersionList as $pv) {
         $totalEffortEstim += $pv->effortEstim + $pv->effortAdd;
         $totalElapsed += $pv->elapsed;
         $totalBacklog += $pv->duration;
         $totalReestimated += $pv->getReestimated();
         //$formatedList  = implode( ',', array_keys($pv->getIssueList()));

         $values = $pv->getDrift();
         $totalDrift += $values['nbDays'];

         $versionsDetailed[] = array(
            'name' => $pv->name,
            //'progress' => round(100 * $pv->getProgress()),
            'title' => 'title="'.($pv->effortEstim + $pv->effortAdd).'"',
            'effortEstim' => ($pv->effortEstim + $pv->effortAdd),
            'reestimated' => $pv->getReestimated(),
            'elapsed' => $pv->elapsed,
            'backlog' => $pv->duration,
            'driftColor' => IssueSelection::getDriftColor($values['percent']),
            'drift' => round($values['nbDays'],2)
         );
      }

      $versionsDetailed[] = array(
         'effortEstim' => $totalEffortEstim,
         'reestimated' => $totalReestimated,
         'elapsed' => $totalElapsed,
         'backlog' => $totalBacklog,
         'drift' => $totalDrift
      );

      return $versionsDetailed;
   }

   /**
    * Get version issues
    * @param ProjectVersion[] $projectVersionList
    * @return mixed[]
    */
   private function getVersionsIssues(array $projectVersionList) {
      global $status_new;
      $versionsIssues = NULL;
      $totalElapsed = 0;
      $totalBacklog = 0;
      foreach ($projectVersionList as $pv) {
         $totalElapsed += $pv->elapsed;
         // FIXME No remaing exist on ProjectVersion neither IssueSelection
         $totalBacklog += $pv->backlog;
         //$formatedList  = implode( ',', array_keys($pv->getIssueList()));

         // format Issues list
         $formatedResolvedList = "";
         $formatedOpenList = "";
         $formatedNewList = "";
         foreach ($pv->getIssueList() as $bugid => $issue) {

            if ($status_new == $issue->currentStatus) {
               if ("" != $formatedNewList) {
                  $formatedNewList .= ', ';
               }
               $formatedNewList .= Tools::issueInfoURL($bugid, '['.$issue->getProjectName().'] '.$issue->summary);

            } elseif ($issue->currentStatus >= $issue->getBugResolvedStatusThreshold()) {
               if ("" != $formatedResolvedList) {
                  $formatedResolvedList .= ', ';
               }
               $title = "(".$issue->getDrift().') ['.$issue->getProjectName().'] '.$issue->summary;
               $formatedResolvedList .= Tools::issueInfoURL($bugid, $title);
            } else {
               if ("" != $formatedOpenList) {
                  $formatedOpenList .= ', ';
               }
               $title = "(".$issue->getDrift().", ".$issue->getCurrentStatusName().') ['.$issue->getProjectName().'] '.$issue->summary;
               $formatedOpenList .= Tools::issueInfoURL($bugid, $title);
            }
         }

         $versionsIssues[] = array(
            'name' => $pv->name,
            'newList' => $formatedNewList,
            'openList' => $formatedOpenList,
            'resolvedList' => $formatedResolvedList
         );
      }

      /*
      // compute total progress
      if (0 == $totalBacklog) {
         $totalProgress = 1;  // if no Backlog, then Project is 100% done.
      } elseif (0 == $totalElapsed) {
         $totalProgress = 0;  // if no time spent, then no work done.
      } else {
         $totalProgress = $totalElapsed / ($totalElapsed + $totalBacklog);
      }
      */

      return $versionsIssues;
   }

   /**
    * Get all "non-resolved" issues that are in drift (ordered by version)
    * @param ProjectVersion[] $projectVersionList
    * @param boolean $isManager
    * @param boolean $withSupport
    * @return mixed[]
    */
   private function getCurrentIssuesInDrift(array $projectVersionList, $isManager, $withSupport = true) {
      $currentIssuesInDrift = NULL;
      foreach ($projectVersionList as $pv) {
         foreach ($pv->getIssueList() as $issue) {

            if ($issue->isResolved()) {
               // skip resolved issues
               continue;
            }

            $driftPrelEE = ($isManager) ? $issue->getDriftMgr($withSupport) : 0;
            $driftEE = $issue->getDrift($withSupport);

            if (($driftPrelEE > 0) || ($driftEE > 0)) {
               $driftMgrColor = NULL;
               $driftMgr = NULL;
               if ($isManager) {
                  if ($driftPrelEE < -1) {
                     $driftMgrColor = "#61ed66";
                  } else if ($driftPrelEE > 1) {
                     $driftMgrColor = "#fcbdbd";
                  }
                  $driftMgr = round($driftPrelEE, 2);
               }
               $driftColor = NULL;
               if ($driftEE < -1) {
                  $driftColor = "#61ed66";
               } else if ($driftEE > 1) {
                  $driftColor = "#fcbdbd";
               }

               $currentIssuesInDrift[] = array(
                  'issueURL' => Tools::issueInfoURL($issue->bugId),
                  'mantisURL' => Tools::mantisIssueURL($issue->bugId, NULL, true),
                  'projectName' => $issue->getProjectName(),
                  'targetVersion' => $issue->getTargetVersion(),
                  'driftMgrColor' => $driftMgrColor,
                  'driftMgr' => $driftMgr,
                  'driftColor' => $driftColor,
                  'drift' => round($driftEE, 2),
                  'backlog' => $issue->backlog,
                  'progress' => round(100 * $issue->getProgress()),
                  'currentStatusName' => $issue->getCurrentStatusName(),
                  'summary' => $issue->summary
               );
            }
         }
      }

      return $currentIssuesInDrift;
   }

   /**
    * Get all resolved issues that are in drift (ordered by version)
    * @param ProjectVersion[] $projectVersionList
    * @param boolean $isManager
    * @param boolean $withSupport
    * @return mixed[]
    */
   private function getResolvedIssuesInDrift(array $projectVersionList, $isManager, $withSupport = true) {
      $resolvedIssuesInDrift = NULL;
      foreach ($projectVersionList as $pv) {
         foreach ($pv->getIssueList() as $issue) {

            if (!$issue->isResolved()) {
               // skip non-resolved issues
               continue;
            }

            $driftPrelEE = ($isManager) ? $issue->getDriftMgr($withSupport) : 0;
            $driftEE = $issue->getDrift($withSupport);

            if (($driftPrelEE > 0) || ($driftEE > 0)) {
               $driftMgrColor = NULL;
               $driftMgr = NULL;
               if ($isManager) {
                  if ($driftPrelEE < -1) {
                     $driftMgrColor = "#61ed66";
                  } else if ($driftPrelEE > 1) {
                     $driftMgrColor = "#fcbdbd";
                  }
                  $driftMgr = round($driftPrelEE, 2);
               }
               $driftColor = NULL;
               if ($driftEE < -1) {
                  $driftColor = "#61ed66";
               } else if ($driftEE > 1) {
                  $driftColor = "#fcbdbd";
               }

               $resolvedIssuesInDrift[] = array(
                  'issueURL' => Tools::issueInfoURL($issue->bugId),
                  'mantisURL' => Tools::mantisIssueURL($issue->bugId, NULL, true),
                  'projectName' => $issue->getProjectName(),
                  'targetVersion' => $issue->getTargetVersion(),
                  'driftMgrColor' => $driftMgrColor,
                  'driftMgr' => $driftMgr,
                  'driftColor' => $driftColor,
                  'drift' => round($driftEE, 2),
                  'backlog' => $issue->backlog,
                  'progress' => round(100 * $issue->getProgress()),
                  'currentStatusName' => $issue->getCurrentStatusName(),
                  'summary' => $issue->summary
               );
            }
         }
      }

      return $resolvedIssuesInDrift;
   }

}

// ========== MAIN ===========
ProjectInfoController::staticInit();
$controller = new ProjectInfoController('Project Info','ProjectInfo');
$controller->execute();

?>
