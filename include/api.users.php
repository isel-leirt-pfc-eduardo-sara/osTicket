<?php

include_once INCLUDE_DIR.'class.api.php';
include_once INCLUDE_DIR.'class.user.php';
include_once INCLUDE_DIR.'class.ticket.php';

class UserApiController extends ApiController {

    function createUser($format) {
        $this->validateAndExecute([$this, '_createUser'],  $format);
    }
    
    function updateUser($format){
        $this->validateAndExecute([$this, '_updateUser'],  $format);
    }

    function deleteUser($id=null){
        $this->validateAndExecute([$this, '_deleteUser'],  $id);
    }

    function getUsers(){
        $this->validateAndExecute([$this, '_getUsers']);
    }

    //Auxiliary functions
    
    function _createUser ($format){ 
        $vars = $this->getRequest($format);
        $errors = array();
        
         // Check if required data is present
        if (empty($vars['name'])) {
            $errors['name'] = __('Name is required');
        }
        if (empty($vars['email'])) {
            $errors['email'] = __('Email is required');
        } 

        if ($errors) {
            $this->response(400, json_encode($errors));
            return;
        }

        
        $user = User::fromVars($vars);

        if ($user) {
           // Generic password
            $password = 'changeit'; 
            
            // Set up the vars for UserAccount::register
            $username = $user->getName();
            $accountVars = array(
                'passwd1' => $password,
                'passwd2' => $password,
                'username' => $username->name
            );

            // Creating an account
            $account = $user->register($accountVars, $errors);

            if ($account && !$errors) {
                $account->setPassword($password);
                $user->save();
                $res = $account->sendConfirmEmail();
                if (!$res) {
                    $this->exerr(500, _S($errors));
                    return;
                }
    
                $response = array(
                    'id' => $user->getId(),
                    'password' => $password
                );

                $this->response(201, json_encode($response), JSON_PRETTY_PRINT);
            } else {
                $this->exerr(500, _S($errors));
                return;
            }

        } else {
            $this->exerr(500, _S($errors));
        }
    }

    function _updateUser ($format){ 
        $vars = $this->getRequest($format);

        if (!isset($vars['id'])) {
            $this->response(400, _S(API::ERR_MISSING_ID));
        }

        // Check if at least one of the updatable propirties (name or email) is provided
        if (!isset($vars['name']) && !isset($vars['email'])) {
            $this->response(400, _S(API::ERR_MISSING_UPDATABLE_PROPERTIES));
        }

        $user = User::lookup($vars['id']);

        if (!$user) {
            $this->response(404, _S("User not found"));
        }
       
        if(!isset($vars['name'])) $vars['name'] = $user->getName();
        if(!isset($vars['email'])) $vars['email'] = $user->getEmail();

        $errors = array();
        $result  = $user->updateInfo($vars, $errors);

        if ($result==true) {
            $this->response(204, _S("User updated successfully"));
        } else {
            $this->exerr(500, _S($errors));
        }
    }


    function _deleteUser($id=null) {
        if (!$id || !is_numeric($id)) {
            $this->response(400, _S(API::ERR_INVALID_QUERY_PARAMETER));
        }

        $user = User::lookup($id);

        if (!$user) {
            $this->response(404, _S("User not found"));
        }

        // Get all user tickets 
        $tickets = Ticket::objects()->filter(array('user_id' => $id));

        // Delete all user tickets
        foreach ($tickets as $ticket) {
            if (!$ticket->delete()) {
                $this->exerr(500, _S("Error deleting ticket with ID: " . $ticket->getId()));
                return;
            }
        }

        // After all the tickets are deleated, delete user
        if ($user->delete()) {
            $this->response(204, _S("User deleted successfully"));
        } else {
            $this->exerr(500, _S(API::ERR_INTERNAL_SERVER_ERROR));
        }
    }

    function _getUsers(){ 
        $users = User::objects()->all();

        $userIds = [];
        foreach ($users as $user) {
            $nameInfo = $user->getName();
            $fullName = $nameInfo->name;

            $userIds[] = [
                'id' => $user->getId(),
                'name' => $fullName
            ];
        }

        if ($userIds) {
            $resp = json_encode(['userIds' => $userIds], JSON_PRETTY_PRINT);
            $this->response(200, $resp);
        } else {
            $this->exerr(500, _S(API::ERR_INTERNAL_SERVER_ERROR));
        } 
    }
}

?>
