<?php

include_once INCLUDE_DIR.'class.api.php';
include_once INCLUDE_DIR.'class.staff.php';
require_once INCLUDE_DIR.'class.user.php';
require_once INCLUDE_DIR.'class.organization.php';
require_once INCLUDE_DIR.'class.faq.php';
require_once INCLUDE_DIR.'class.email.php';
require_once INCLUDE_DIR.'class.dept.php';

class StaffApiController extends ApiController {
    function getStaffMembers() {
        $this->validateAndExecute([$this, '_getStaffMembers']);
    }
    
    function createStaffMember($format) {
        $this->validateAndExecute([$this, '_createStaffMember'], $format);
    }
    
    function updateStaffMember($format) {
        $this->validateAndExecute([$this, '_updateStaffMember'], $format);
    }
    
    function deleteStaffMember($id=null) {
        $this->validateAndExecute([$this, '_deleteStaffMember'], $id);
    }
    
    function _getStaffMembers() {
        $staff = array();
        $staffMembers = Staff::getStaffMembers();
        foreach ($staffMembers as $id => $member) {
            $member = Staff::lookup(array('staff_id' => $id));
            if ($member) {
                $staff[] = array(
                    'id' => $member->getId(),
                    'name' => $member->getFirstName().' '.$member->getLastName(),
                    'username' => $member->getUserName(),
                    'email' => $member->getEmail()
                );
            }
        }
        
        // Sort the staff members by their ID
        usort($staff, function($a, $b) {
            return $a['id'] - $b['id'];
        });
        
        if ($staff) {
            $resp = json_encode(array('staff' => $staff), JSON_PRETTY_PRINT);
            $this->response(200, $resp);
        } else {
            $this->exerr(500, _S(API::ERR_INTERNAL_SERVER_ERROR));
        }
    }
    
    function _createStaffMember($format) {
        $vars = $this->getRequest($format);
        
        $dept = Dept::lookup($vars['dept_id']);
        if (!$dept) {
            $this->response(404, _S("dept_id: Department not found"));
        }
        
        $vars = array_merge($vars, ['role_id' => 1]); // Provide 'All Access' to new agent        
        
        // The '$staff->update()' method checks if '$vars['isadmin']' is set, regardless of its actual value.
        // To ensure consistent behavior, we set '$vars['isadmin']' to 'null' when its value is not 'true'.
        $vars['isadmin'] = ($vars['isadmin'] === true) ? true : null;
        
        $vars['perms'] = [
            User::PERM_CREATE,
            User::PERM_DELETE,
            User::PERM_EDIT,
            User::PERM_MANAGE,
            User::PERM_DIRECTORY,
            Organization::PERM_CREATE,
            Organization::PERM_DELETE,
            Organization::PERM_EDIT,
            FAQ::PERM_MANAGE,
            Staff::PERM_STAFF,
            Dept::PERM_DEPT
        ];
        
        if ($vars['isadmin'] == 1) {
            $vars['perms'][] = Email::PERM_BANLIST;
        }
        
        $staff = Staff::create();
        
        $errors = array();
        $staff->update($vars, $errors);
        
        if ($errors) {
            $this->exerr(400, _S($errors));
        } 
        
        $staff->setExtraAttr('def_assn_role', true);
        
        $passwd = "changeit";
        $staff->setPassword($passwd);
        $staff->save();
        
        $staff->sendResetEmail('registration-staff', false);
        
        if ($staff) {
            $this->response(201, json_encode(array('id' => $staff->getId(), 'passwd' => $passwd)), JSON_PRETTY_PRINT);
        } else {
            $this->exerr(500, _S(API::ERR_INTERNAL_SERVER_ERROR));
        }
    }
        
    function _updateStaffMember($format) {
        $vars = $this->getRequest($format);
        
        if (!$vars['id']) {
            $this->response(400, _S(API::ERR_MISSING_ID));
        }

        $staff = Staff::lookup($vars['id']);
        
        if (!$staff) {
            $this->response(404, _S("id: Staff member not found"));
        }
        
        if (!$vars['firstname'] && !$vars['lastname'] && !$vars['username'] &&
            !$vars['email'] && !$vars['dept_id'] && !isset($vars['isadmin'])) {
            $this->response(400, _S(API::ERR_MISSING_UPDATABLE_PROPERTIES));
        }
        
        if ($vars['dept_id']) {
            $dept = Dept::lookup($vars['dept_id']);
            if (!$dept) {
                $this->response(404, _S("dept_id: Department not found"));
            }
        }
        
        // The '$staff->update()' method checks if '$vars['isadmin']' is set, regardless of its actual value.
        // To ensure consistent behavior, we set '$vars['isadmin']' to 'null' when its value is not 'true'.
        $vars['isadmin'] = ($vars['isadmin'] === true) ? true : null;
        
        // Same logic for '$vars['onvacation']'
        $vars['onvacation'] = ($vars['onvacation'] === true) ? true : null;
        
        $vars['perms'] = [
            User::PERM_CREATE,
            User::PERM_DELETE,
            User::PERM_EDIT,
            User::PERM_MANAGE,
            User::PERM_DIRECTORY,
            Organization::PERM_CREATE,
            Organization::PERM_DELETE,
            Organization::PERM_EDIT,
            FAQ::PERM_MANAGE,
            Staff::PERM_STAFF,
            Dept::PERM_DEPT
        ];
        
        if ($vars['isadmin'] == 1) {
            $vars['perms'][] = Email::PERM_BANLIST;
        }
        
        // Merge existing properties with updated ones to ensure retention of unchanged data
        $vars = array_merge($staff->ht, $vars);
        
        $errors = array();
        $staff->update($vars, $errors);
                
        if ($errors) {
            $this->exerr(400, _S($errors));
        }
        
        $staff->save();
        $staff->setExtraAttr('def_assn_role', true);
        
        if ($staff) {
            $this->response(204, _S("Staff member updated successfully"));
        } else {
            $this->exerr(500, _S(API::ERR_INTERNAL_SERVER_ERROR));
        }
    }
    
    function _deleteStaffMember($id=null) {
        if (!$id || !is_numeric($id)) {
            $this->response(400, _S(API::ERR_INVALID_QUERY_PARAMETER));
        }
        
        $staff = Staff::lookup($id);
        
        if (!$staff) {
            $this->response(404, _S("Staff member not found"));
        }
        
        // '$thisstaff' needs to be set for '$staff->delete()' to work, but it
        // cannot be the same staff member being deleted. Therefore, we create
        // a temporary staff member instance to fulfill this requirement. This 
        // temporary staff member is not saved in the system and is used solely
        // to allow the deletion process to proceed.
        global $thisstaff;
        $thisstaff = Staff::create();
        
        if ($staff->delete()) {
            $this->response(204, _S("Staff member deleted successfully"));
        } else {
            $this->exerr(500, _S(API::ERR_INTERNAL_SERVER_ERROR));
        }
    }
}

?>