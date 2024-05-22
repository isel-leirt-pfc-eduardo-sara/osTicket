<?php

include_once INCLUDE_DIR.'class.api.php';
include_once INCLUDE_DIR.'class.team.php';

class TeamsApiController extends ApiController {
    function getTeams() {
        $this->validateAndExecute([$this, '_getTeams']);
    }
    
    function createTeam($format) {
        $this->validateAndExecute([$this, '_createTeam'], $format);
    }
    
    function updateTeam($format) {
        $this->validateAndExecute([$this, '_updateTeam'], $format);
    }
    
    function deleteTeam($id=null) {
        $this->validateAndExecute([$this, '_deleteTeam'], $id);
    }
    
    function _getTeams() {
        $teamsRaw = Team::getTeams();
        $teams = array();
        foreach ($teamsRaw as $id => $name) {
            $teams[] = ['id' => $id, 'name' => $name];
        }
        if ($teams) {
            $resp = json_encode(array('teams' => $teams), JSON_PRETTY_PRINT);
            $this->response(200, $resp);
        } else {
            $this->exerr(500, _S(API::ERR_INTERNAL_SERVER_ERROR));
        }
    }
    
    function _createTeam($format) {
        $vars = $this->getRequest($format);
        
        $team = Team::create();
        
        foreach ($vars['members'] as $staff_id) {
            $vars['member_alerts'][$staff_id] = TeamMember::FLAG_ALERTS;
        }
        
        $errors = array();
        $team->update($vars, $errors);
        
        if ($errors) {
            $this->exerr(400, _S($errors));
        } 
        
        if ($team) {
            $this->response(201, json_encode($team->getId()), JSON_PRETTY_PRINT);
        } else {
            $this->exerr(500, _S(API::ERR_INTERNAL_SERVER_ERROR));
        }
    }
        
    function _updateTeam($format) {
        $vars = $this->getRequest($format);
        
        if (!$vars['id']) {
            $this->response(400, _S(API::ERR_MISSING_ID));
        }

        $team = Team::lookup($vars['id']);
        if (!$team) {
            $this->response(404, _S("Team not found"));
        }
        
        if (!$vars['name'] && !$vars['members']) {
            $this->response(400, _S(API::ERR_MISSING_UPDATABLE_PROPERTIES));
        }
        
        // If no 'members' are provided in the request, indicating no update to members,
        // fetch the current team members and populate $vars['members'] with their 'staff_id'
        if (!isset($vars['members'])) {
            $members = $team->getMembers();
            $vars['members'] = array_map(function($member) {
                return $member->staff_id;
            }, $members);
        }
        
        foreach ($vars['members'] as $staff_id) {
            $vars['member_alerts'][$staff_id] = TeamMember::FLAG_ALERTS;
        }
        
        // Merge existing properties with updated ones to ensure retention of unchanged data
        $vars = array_merge($team->ht, $vars);
        $vars['isenabled'] = true;
        
        $errors = array();
        $team->update($vars, $errors);
                
        if ($errors) {
            $this->exerr(400, _S($errors));
        }
        
        if ($team) {
            $this->response(204, _S("Team updated successfully"));
        } else {
            $this->exerr(500, _S(API::ERR_INTERNAL_SERVER_ERROR));
        }
    }
    
    function _deleteTeam($id=null) {
        if (!$id || !is_numeric($id)) {
            $this->response(400, _S(API::ERR_INVALID_QUERY_PARAMETER));
        }
        
        $team = Team::lookup($id);
        if (!$team) {
            $this->response(404, _S("Team not found"));
        }
        
        // '$thisstaff' needs to be set for '$team->delete()' to work. Therefore,
        // we create a temporary staff member instance to fulfill this requirement.
        // This temporary staff member is not saved in the system and is used solely
        // to allow the deletion process to proceed.
        global $thisstaff;
        $thisstaff = Staff::create();
        
        if ($team->delete()) {
            $this->response(204, _S("Team deleted successfully"));
        } else {
            $this->exerr(500, _S(API::ERR_INTERNAL_SERVER_ERROR));
        }
    }
}

?>