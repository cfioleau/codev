<?php
/*
   This file is part of CodevTT.

   CodevTT is free software: you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.

   CodevTT is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with CodevTT.  If not, see <http://www.gnu.org/licenses/>.
*/

require_once('indicator_plugins/progress_history_indicator.class.php');

include_once('classes/command.class.php');
include_once('classes/commandset_cache.class.php');
include_once('classes/servicecontract_cache.class.php');
include_once('classes/team_cache.class.php');

require_once('smarty_tools.php');

/**
 * @param int $teamid
 * @param int $selectedCmdSetId
 * @return mixed[]
 */
function getCommandSets($teamid, $selectedCmdSetId) {
   $commandsets = array();
   if (0 != $teamid) {

   $team = TeamCache::getInstance()->getTeam($teamid);
   $commandsetList = $team->getCommandSetList();

   foreach ($commandsetList as $id => $commandset) {
      $commandsets[] = array(
         'id' => $id,
         'name' => $commandset->getName(),
         'selected' => ($id == $selectedCmdSetId)
      );
   }
}
   return $commandsets;
}

/**
 * @param CommandSet $cmd
 * @return mixed[]
 */
function getParentContracts(CommandSet $cset) {
   $contracts = array();

   $contractList = $cset->getServiceContractList();

   // TODO return URL for 'name' ?

   foreach ($contractList as $contract) {
      $teamid = $contract->getTeamid();
      $team = TeamCache::getInstance()->getTeam($teamid);

      $contracts[] = array(
         'id' => $contract->getId(),
         'name' => $contract->getName(),
         'team' => $team->name
      );
   }
   return $contracts;
}

/**
 * @param int $commandsetid
 * @param int $type
 * @return mixed[]
 */
function getCommandSetCommands($commandsetid, $type) {
   $commands = array();

   if (0 != $commandsetid) {

      $commandset = CommandSetCache::getInstance()->getCommandSet($commandsetid);

      $cmdList = $commandset->getCommands($type);
      foreach ($cmdList as $id => $cmd) {

         $issueSelection = $cmd->getIssueSelection();
         $cmdDetailedMgr = SmartyTools::getIssueSelectionDetailedMgr($issueSelection);

         $cmdDetailedMgr['name'] = $cmd->getName();
         $cmdDetailedMgr['reference'] = $cmd->getReference();
         $cmdDetailedMgr['description'] = $cmd->getDesc();

         $teamid = $cmd->getTeamid();
         $team = TeamCache::getInstance()->getTeam($teamid);
         $cmdDetailedMgr['team'] = $team->getName();

         $commands[$id] = $cmdDetailedMgr;
      }
   }
   return $commands;
}

/**
 * @param int $commandsetid
 * @param int $type Command::type_general
 * @return mixed[]
 */
function getCommandSetDetailedMgr($commandsetid, $type) {
   $csetDetailedMgr = NULL;
   if (0 != $commandsetid) {
      $commandset = CommandSetCache::getInstance()->getCommandSet($commandsetid);

      $issueSelection = $commandset->getIssueSelection($type);
      $csetDetailedMgr = SmartyTools::getIssueSelectionDetailedMgr($issueSelection);
   }
   return $csetDetailedMgr;
}

/**
 * @param CommandSet $commandSet
 * @return string
 */
function getCSetProgressHistory(CommandSet $commandSet) {
   $cmdIssueSel = $commandSet->getIssueSelection(Command::type_general);

   $startTT = $cmdIssueSel->getFirstTimetrack();
   if ((NULL != $startTT) && (0 != $startTT->date)) {
      $startTimestamp = $startTT->date;
   } else {
      $startTimestamp = $commandSet->getDate();
      #echo "cmd getStartDate ".date("Y-m-d", $startTimestamp).'<br>';
      if (0 == $startTimestamp) {
         $team = TeamCache::getInstance()->getTeam($commandSet->getTeamid());
         $startTimestamp = $team->date;
         #echo "team Date ".date("Y-m-d", $startTimestamp).'<br>';
      }
   }

   $endTT = $cmdIssueSel->getLatestTimetrack();
   $endTimestamp = ((NULL != $endTT) && (0 != $endTT->date)) ? $endTT->date : time();

   $params = array('startTimestamp' => $startTimestamp, // $cmd->getStartDate(),
                   'endTimestamp' => $endTimestamp,
                   'interval' => 14 );

   $progressIndicator = new ProgressHistoryIndicator();
   $progressIndicator->execute($cmdIssueSel, $params);

   return $progressIndicator->getSmartyObject();
}

/**
 * @param SmartyHelper $smartyHelper
 * @param CommandSet $commandset
 */
function displayCommandSet(SmartyHelper $smartyHelper, CommandSet $commandset) {
   #$smartyHelper->assign('commandsetId', $commandset->getId());
   $smartyHelper->assign('teamid', $commandset->getTeamid());
   $smartyHelper->assign('commandsetName', $commandset->getName());
   $smartyHelper->assign('commandsetReference', $commandset->getReference());
   $smartyHelper->assign('commandsetDesc', $commandset->getDesc());
   $smartyHelper->assign('commandsetBudget', $commandset->getBudgetDays());
   $smartyHelper->assign('commandsetCost', $commandset->getCost());
   $smartyHelper->assign('commandsetCurrency', $commandset->getCurrency());
   $smartyHelper->assign('commandsetDate', date("Y-m-d", $commandset->getDate()));

   $commands = getCommandSetCommands($commandset->getId(), Command::type_general);
   $smartyHelper->assign('nbCommands', count($commands));
   $smartyHelper->assign('cmdList', $commands);
   $smartyHelper->assign('cmdsetDetailedMgr', getCommandSetDetailedMgr($commandset->getId(), Command::type_general));

   $smartyHelper->assign('jqplotTitle', 'Historical Progression Chart');
   $smartyHelper->assign('jqplotYaxisLabel', '% Progress');
   $smartyHelper->assign('jqplotData', getCSetProgressHistory($commandset));
}

?>
