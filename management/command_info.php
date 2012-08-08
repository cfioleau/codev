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

require('management/command_tools.php');

require_once('smarty_tools.php');
require_once('tools.php');

/**
 * @param int $teamid
 * @param int $selectedCmdId
 * @return mixed[]
 */
function getCommands($teamid, $selectedCmdId) {
   $commands = array();
   if (0 != $teamid) {
      $team = TeamCache::getInstance()->getTeam($teamid);
      $cmdList = $team->getCommands();

      foreach ($cmdList as $id => $cmd) {
         $commands[] = array(
            'id' => $id,
            'name' => $cmd->getName(),
            'reference' => $cmd->getReference(),
            'selected' => ($id == $selectedCmdId)
         );
      }
   }
   return $commands;
}

/**
 * Get consistency errors
 * @param Command $cmd
 * @return mixed[]
 */
function getConsistencyErrors(Command $cmd) {
   global $statusNames;

   $consistencyErrors = array(); // if null, array_merge fails !

   $cerrList = $cmd->getConsistencyErrors();
   if (count($cerrList) > 0) {
      foreach ($cerrList as $cerr) {
         $issue = IssueCache::getInstance()->getIssue($cerr->bugId);
         $user = UserCache::getInstance()->getUser($cerr->userId);
         $consistencyErrors[] = array(
             'issueURL' => Tools::issueInfoURL($cerr->bugId, '[' . $issue->getProjectName() . '] ' . $issue->summary),
             'issueStatus' => $statusNames[$cerr->status],
             'user' => $user->getName(),
             'severity' => $cerr->getLiteralSeverity(),
             'severityColor' => $cerr->getSeverityColor(),
             'desc' => $cerr->desc);
      }
   }

   return $consistencyErrors;
}

// =========== MAIN ==========
$smartyHelper = new SmartyHelper();
$smartyHelper->assign('pageName', T_('Command'));
$smartyHelper->assign('activeGlobalMenuItem', 'Management');

if (isset($_SESSION['userid'])) {
   $userid = $_SESSION['userid'];
   $session_user = UserCache::getInstance()->getUser($userid);

   // use the teamid set in the form, if not defined (first page call) use session teamid
   $teamid = Tools::getSecurePOSTIntValue('teamid', 0);
   if(0 == $teamid) {
      if(isset($_SESSION['teamid'])) {
         $teamid = $_SESSION['teamid'];
      }
   }
   $_SESSION['teamid'] = $teamid;

   // if cmdid set in URL, use it. else:
   // use the cmdid set in the form, if not defined (first page call) use session cmdid
   $cmdid = Tools::getSecureGETIntValue('cmdid', 0);
   if (0 == $cmdid) {
      if(isset($_POST['cmdid'])) {
         $cmdid = $_POST['cmdid'];
      } else if(isset($_SESSION['cmdid'])) {
         $cmdid = $_SESSION['cmdid'];
      }
   }
   $_SESSION['cmdid'] = $cmdid;

   // set TeamList (including observed teams)
   $teamList = $session_user->getTeamList();

   $action = isset($_POST['action']) ? $_POST['action'] : '';

   // ------ Display Command
   if (0 != $cmdid) {
      $cmd = CommandCache::getInstance()->getCommand($cmdid);

      if (array_key_exists($cmd->getTeamid(), $teamList)) {
         $teamid = $cmd->getTeamid();
         $_SESSION['teamid'] = $teamid;

         displayCommand($smartyHelper, $cmd);

         // ConsistencyCheck
         $consistencyErrors = getConsistencyErrors($cmd);
         if(count($consistencyErrors) > 0) {

            $smartyHelper->assign('ccheckButtonTitle', count($consistencyErrors).' '.T_("Errors"));
            $smartyHelper->assign('ccheckBoxTitle', count($consistencyErrors).' '.T_("Errors"));
            $smartyHelper->assign('ccheckErrList', $consistencyErrors);
         }

         // access rights
         if (($session_user->isTeamManager($cmd->getTeamid())) ||
            ($session_user->isTeamLeader($cmd->getTeamid()))) {

            $smartyHelper->assign('isEditGranted', true);
         }
      } else {
         // TODO smarty error msg
         echo T_('Sorry, You are not allowed to see this command');
      }
   } else {
      unset($_SESSION['commandsetid']);
      unset($_SESSION['servicecontractid']);

      if ('displayCommand' == $action) {
         header('Location:command_edit.php?cmdid=0');
      }
   }

   $smartyHelper->assign('teamid', $teamid);
   $smartyHelper->assign('teams', SmartyTools::getSmartyArray($teamList, $teamid));

   $smartyHelper->assign('commandid', $cmdid);
   $smartyHelper->assign('commands', getCommands($teamid, $cmdid));
}

$smartyHelper->displayTemplate($mantisURL);

?>
