<?php

include_once INCLUDE_DIR.'class.api.php';
include_once INCLUDE_DIR.'class.ticket.php';

class TicketApiController extends ApiController {

    # Supported arguments -- anything else is an error. These items will be
    # inspected _after_ the fixup() method of the ApiXxxDataParser classes
    # so that all supported input formats should be supported
    function getRequestStructure($format, $data=null) {
        $supported = array(
            "alert", "autorespond", "source", "topicId",
            "attachments" => array("*" =>
                array("name", "type", "data", "encoding", "size")
            ),
            "message", "ip", "priorityId",
            "system_emails" => array(
                "*" => "*"
            ),
            "thread_entry_recipients" => array (
                "*" => array("to", "cc")
            )
        );
        # Fetch dynamic form field names for the given help topic and add
        # the names to the supported request structure
        if (isset($data['topicId'])
                && ($topic = Topic::lookup($data['topicId']))
                && ($forms = $topic->getForms())) {
            foreach ($forms as $form)
                foreach ($form->getDynamicFields() as $field)
                    $supported[] = $field->get('name');
        }

        # Ticket form fields
        # TODO: Support userId for existing user
        if(($form = TicketForm::getInstance()))
            foreach ($form->getFields() as $field)
                $supported[] = $field->get('name');

        # User form fields
        if(($form = UserForm::getInstance()))
            foreach ($form->getFields() as $field)
                $supported[] = $field->get('name');

        if(!strcasecmp($format, 'email')) {
            $supported = array_merge($supported, array('header', 'mid',
                'emailId', 'to-email-id', 'ticketId', 'reply-to', 'reply-to-name',
                'in-reply-to', 'references', 'thread-type', 'system_emails',
                'mailflags' => array('bounce', 'auto-reply', 'spam', 'viral'),
                'recipients' => array('*' => array('name', 'email', 'source'))
                ));

            $supported['attachments']['*'][] = 'cid';
        }

        return $supported;
    }

    /*
     Validate data - overwrites parent's validator for additional validations.
    */
    function validate(&$data, $format, $strict=true) {
        global $ost;

        //Call parent to Validate the structure
        if(!parent::validate($data, $format, $strict) && $strict)
            $this->exerr(400, __('Unexpected or invalid data received'));

        // Use the settings on the thread entry on the ticket details
        // form to validate the attachments in the email
        $tform = TicketForm::objects()->one()->getForm();
        $messageField = $tform->getField('message');
        $fileField = $messageField->getWidget()->getAttachments();

        // Nuke attachments IF API files are not allowed.
        if (!$messageField->isAttachmentsEnabled())
            $data['attachments'] = array();

        //Validate attachments: Do error checking... soft fail - set the error and pass on the request.
        if (isset($data['attachments']) && is_array($data['attachments'])) {
            foreach($data['attachments'] as &$file) {
                if ($file['encoding'] && !strcasecmp($file['encoding'], 'base64')) {
                    if(!($file['data'] = base64_decode($file['data'], true)))
                        $file['error'] = sprintf(__('%s: Poorly encoded base64 data'),
                            Format::htmlchars($file['name']));
                }
                // Validate and save immediately
                try {
                    $F = $fileField->uploadAttachment($file);
                    $file['id'] = $F->getId();
                }
                catch (FileUploadError $ex) {
                    $name = $file['name'];
                    $file = array();
                    $file['error'] = Format::htmlchars($name) . ': ' . $ex->getMessage();
                }
            }
            unset($file);
        }

        return true;
    }


    function create($format) {

        if (!($key=$this->requireApiKey()) || !$key->canCreateTickets()) //class.api.php
            return $this->exerr(401, __('API key not authorized'));

        $ticket = null;
        if (!strcasecmp($format, 'email')) {
            // Process remotely piped emails - could be a reply...etc.
            $ticket = $this->processEmailRequest();
        } else {
            // Get and Parse request body data for the format
            $ticket = $this->createTicket($this->getRequest($format)); // class.api.php
        }

        if ($ticket)
            $this->response(201, $ticket->getNumber());
        else
            $this->exerr(500, _S("unknown error"));

    }
    
    function updateTicket($format){
        $this->validateAndExecute([$this, '_updateTicket'],  $format);
    }

    function deleteTicket($id=null){
        $this->validateAndExecute([$this, '_deleteTicket'],  $id);
    }

    function getTickets($title=null, $number=null, $status=null,
    $topicId=null, $priorityId=null, $deptId=null, $staffId=null, 
    $teamId=null, $createdDate=null){
        // Extracting query parameters
        $query = $_GET;
        $this->validateAndExecute([$this, '_getTickets'], $query);
    }

    /* private helper functions */

    function _updateTicket ($format){ //To be checked
        $data = $this->getRequest($format);

        if (!$data['id']) {
            $this->response(400, _S(API::ERR_MISSING_ID));
        }
        
        if (!$data['priorityId'] && !$data['topicId'] 
            && !$data['slaId'] && !$data['deptId'] 
            && !$data['staffId'] && !$data['teamId'] 
            && !$data['statusId'] && !$data['message'] 
            && !$data['reply'] && !$data['note'] 
        ) {
            $this->response(400, _S(API::ERR_MISSING_UPDATABLE_PROPERTIES));
        }

        $ticket = Ticket::lookup($data['id']);

        if (!$ticket) {
            $this->response(404, _S("Ticket not found"));
        }

        /* ERROR while trying to update PRIORITY and TOPIC parameters because 
        of $thisstaff variable. Possible Solutions:
            1º Approach:
                global $thisstaff;
                $thisstaff = Staff::create();
            2º Approach:
                global $thisstaff;
                $thisstaff = Staff::lookup($ticket->getStaffId());
        PS: both have failded so far!!
       */

        //  Update ticket properties with received data, if present
        if (isset($data['priorityId'])) { 

            $priority = Priority::lookup($data['priorityId']);
            if ($priority) {
                 $vars = array(
                    'priorityId' => $data['priorityId'], 
                    'note' => "",
                    'duedate' => "",
                    'source' => "API",
                    'topicId' => $ticket->getTopicId(), 
                    'userId' => $ticket->getUserId()
                );
            
                $errors = array();
                if ($ticket->update($vars, $errors)) {
                    $this->response(204, _S("Ticket updated successfully"));
                } else {
                    $this->exerr(500, _S($errors));
                    return;
                }
            }else {
                $this->exerr(400, 'Invalid priority ID');
                return;
            }
        }

        if (isset($data['topicId'])) { 
            $topic = Topic::lookup($data['topicId']); 
            if ($topic) {
                $vars = array(
                    'topicId' => $data['topicId'],
                    'note' => "",
                    'duedate' => "",
                    'source' => "API",
                    'userId' => $ticket->getUserId()
                );
            
                $errors = array();
                if ($ticket->update($vars, $errors)) {
                    $this->response(204, _S("Ticket updated successfully"));
                } else {
                    $this->exerr(500, _S($errors));
                    return;
                }
            } else {
                $this->exerr(400, 'Invalid topic ID');
                return;
            }
        }

        $slaId = null;
        if (isset($data['slaId'])) {
            $slaId = $data['slaId'];
            if (!is_numeric($slaId)){
                $this->response(400, _S(API::ERR_INVALID_QUERY_PARAMETER));
                return;
            }
           if (!$ticket->setSLAId($data['slaId'])){
                $this->response(400, _S(API::ERR_INVALID_QUERY_PARAMETER));
                return;
           }     
        }

        if (isset($data['deptId'])) { 
           if (!$ticket->setDeptId($data['deptId'])) {
                $this->response(400, _S(API::ERR_INVALID_QUERY_PARAMETER));
                return;
           }
        }

        if (isset($data['staffId'])) { 
            if (!$ticket->setStaffId($data['staffId'])){
                $this->response(400, _S(API::ERR_INVALID_QUERY_PARAMETER));
                return;
            }           
        }

        if (isset($data['teamId'])) { 
            if (!$ticket->setTeamId($data['teamId'])){
                $this->response(400, _S(API::ERR_INVALID_QUERY_PARAMETER));
                return;
            }
        }
        
        $statusId = null;
        if (isset($data['statusId'])) { 
            $statusId = $data['statusId'];
            if (!is_numeric($statusId)){ 
                $this->response(400, _S(API::ERR_INVALID_QUERY_PARAMETER));
                return;
            }
            $status = TicketStatus::lookup($statusId);
            if(!$status){ 
                $this->response(400, _S(API::ERR_INVALID_QUERY_PARAMETER));
                return;
            }
            $ticket->setStatusId($data['statusId']);
        }

        // If there is a message, reply or note, add it to the ticket
        if (isset($data['message'])) {
            $vars = array(
                'message' => $data['message'],
                'userId' => $ticket->getUserId(), 
                'origin' => 'web', 
            );
            $errors = array();
            if (!$ticket->postMessage($vars, 'web', true)) {
                $this->exerr(500, 'Unable to post message to ticket');
                return;
            }
        }

        if (isset($data['reply'])) {
            $vars = array(
                'response' => $data['reply'],
                'staffId' => $ticket->getStaffId(),
            );
            $errors=[];

            if (!$ticket->postReply($vars, $errors)) {
                $this->exerr(500, 'Unable to post reply to ticket');
                return;
            }
        }

        if (isset($data['note'])) {
            $vars = array(
                'note' => $data['note'],
                'staffId' => $ticket->getStaffId(),
            );
            $errors=[];
            if (!$ticket->postNote($vars, $errors)) {
                $this->exerr(500, 'Unable to post note to ticket');
                return;
            }
        }
    
        if ($ticket->save()) {
            $this->response(204, _S("Ticket updated successfully"));
        } else {
            $this->exerr(500, _S($errors));
        }
    }


    function _deleteTicket($id=null) {
        if (!$id || !is_numeric($id)) {
            $this->response(400, _S(API::ERR_INVALID_QUERY_PARAMETER));
        }
        $ticket = Ticket::lookup($id);
        
        if (!$ticket) {
            $this->response(404, _S("Ticket not found"));
        }
        
        if ($ticket->delete()) {
            $this->response(204, _S("Ticket deleted successfully"));
        } else {
            $this->exerr(500, _S(API::ERR_INTERNAL_SERVER_ERROR));
        }
    }

    function _getTickets($query = []){

        $criteria = [];
        
        if (isset($query['title'])) {
            $criteria['subject'] = $query['title'];
        }
        if (isset($query['number'])) {
            $criteria['number'] = $query['number'];
        }
        if (isset($query['status'])) {
            $criteria['status_id'] = $query['status'];
        }
        if (isset($query['topicId'])) {
            $criteria['topic_id'] = $query['topicId'];
        }
        if (isset($query['priorityId'])) {
            $criteria['priority_id'] = $query['priorityId'];
        }
        if (isset($query['deptId'])) {
            $criteria['dept_id'] = $query['deptId'];
        }
        if (isset($query['staffId'])) {
            $criteria['staff_id'] = $query['staffId'];
        }
        if (isset($query['teamId'])) {
            $criteria['team_id'] = $query['teamId'];
        }
        if (isset($query['createdDate'])) {
            $criteria['created'] = $query['createdDate'];
        }

        $all_tickets = Ticket::objects()->all();
        $results = [];

        //Filtering tickets manually
        foreach ($all_tickets as $ticket) {
            $matches = true;
            foreach ($criteria as $key => $value) {
                switch ($key) {
                    case 'subject':
                        if (stripos($ticket->getSubject(), $value) === false) {
                            $matches = false;
                        }
                        break;
                    case 'number':
                        if ($ticket->getNumber() != $value) {
                            $matches = false;
                        }
                        break;
                    case 'status_id':
                        if ($ticket->getStatusId() != $value) {
                            $matches = false;
                        }
                        break;
                    case 'topic_id':
                        if ($ticket->getTopicId() != $value) {
                            $matches = false;
                        }
                        break;
                    case 'priority_id':
                        if ($ticket->getPriorityId() != $value) {
                            $matches = false;
                        }
                        break;
                    case 'dept_id':
                        if ($ticket->getDeptId() != $value) {
                            $matches = false;
                        }
                        break;
                    case 'staff_id':
                        if ($ticket->getStaffId() != $value) {
                            $matches = false;
                        }
                        break;
                    case 'team_id':
                        if ($ticket->getTeamId() != $value) {
                            $matches = false;
                        }
                        break;
                    case 'created':
                        $dateStr = $ticket->getCreateDate();
                        $date = explode(' ', $dateStr);
                        if ($date[0] != $value) {
                            $matches = false;
                        }
                        break;
                    default:
                        $matches = false;
                        break;
                }
                // If no criteria is meet, stop checking
                if (!$matches) {
                    break;
                }
            }

            // Add the ticket to 'results' if it checks all the criterias
            if ($matches) {
                $results[] = [
                    'ticket_id' => $ticket->getId(),
                    'subject' => $ticket->getSubject()
                ];
            }
        }

        if ($results) {
            $resp = json_encode(array('tickets' => $results), JSON_PRETTY_PRINT);
            $this->response(200, $resp);
        } else {
            $this->exerr(500, _S(API::ERR_INTERNAL_SERVER_ERROR));
        }
    }

    function createTicket($data, $source = 'API') {

        # Pull off some meta-data
        $alert       = (bool) (isset($data['alert'])       ? $data['alert']       : true);
        $autorespond = (bool) (isset($data['autorespond']) ? $data['autorespond'] : true);

        // Assign default value to source if not defined, or defined as NULL
        $data['source'] ??= $source;

        // Create the ticket with the data (attempt to anyway)
        $errors = array();
        if (($ticket = Ticket::create($data, $errors, $data['source'],
                        $autorespond, $alert)) &&  !$errors) //class.ticket.php (linha 4054)
            return $ticket;

        // Ticket create failed Bigly - got errors?
        $title = null;
        // Got errors?
        if (count($errors)) {
            // Ticket denied? Say so loudly so it can standout from generic
            // validation errors
            if (isset($errors['errno']) && $errors['errno'] == 403) {
                $title = _S('Ticket denied');
                $error = sprintf("%s: %s\n\n%s",
                        $title, $data['email'], $errors['err']);
            } else {
                // unpack the errors
                $error = Format::array_implode("\n", "\n", $errors);
            }
        } else {
            // unknown reason - default
            $error = _S('unknown error');
        }

        $error = sprintf('%s :%s',
                _S('Unable to create new ticket'), $error);
        return $this->exerr($errors['errno'] ?: 500, $error, $title);
    }

    function processEmailRequest() {
        return $this->processEmail();
    }

    function processEmail($data=false, array $defaults = []) {

        try {
            if (!$data)
                $data = $this->getEmailRequest();
            elseif (!is_array($data))
                $data = $this->parseEmail($data);
        } catch (Exception $ex)  {
            throw new EmailParseError($ex->getMessage());
        }

        $data = array_merge($defaults, $data);
        $seen = false;
        if (($entry = ThreadEntry::lookupByEmailHeaders($data, $seen))
            && ($message = $entry->postEmail($data))
        ) {
            if ($message instanceof ThreadEntry) {
                return $message->getThread()->getObject();
            }
            else if ($seen) {
                // Email has been processed previously
                return $entry->getThread()->getObject();
            }
        }

        // Allow continuation of thread without initial message or note
        elseif (($thread = Thread::lookupByEmailHeaders($data))
            && ($message = $thread->postEmail($data))
        ) {
            return $thread->getObject();
        }

        // All emails which do not appear to be part of an existing thread
        // will always create new "Tickets". All other objects will need to
        // be created via the web interface or the API
        try {
            return $this->createTicket($data, 'Email');
        } catch (TicketApiError $err) {
            // Check if the ticket was denied by a filter or banlist
            if ($err->isDenied() && $data['mid']) {
                // We need to log the Message-Id (mid) so we don't
                // process the same email again in subsequent fetches
                $entry = new ThreadEntry();
                $entry->logEmailHeaders(0, $data['mid']);
                // throw TicketDenied exception so the caller can handle it
                // accordingly
                throw new TicketDenied($err->getMessage());
            } else {
                // otherwise rethrow this bad baby as it is!
                throw $err;
            }
        }
    }
}



//Local email piping controller - no API key required!
class PipeApiController extends TicketApiController {

    // Overwrite grandparent's (ApiController) response method.
    function response($code, $resp) {

        // It's important to use postfix exit codes for local piping instead
        // of HTTP's so the piping script can process them accordingly
        switch($code) {
            case 201: //Success
                $exitcode = 0;
                break;
            case 400:
                $exitcode = 66;
                break;
            case 401: /* permission denied */
            case 403:
                $exitcode = 77;
                break;
            case 415:
            case 416:
            case 417:
            case 501:
                $exitcode = 65;
                break;
            case 503:
                $exitcode = 69;
                break;
            case 500: //Server error.
            default: //Temp (unknown) failure - retry
                $exitcode = 75;
        }
        //We're simply exiting - MTA will take care of the rest based on exit code!
        exit($exitcode);
    }

    static function process($sapi=null) {
        $pipe = new PipeApiController($sapi);
        if (($ticket=$pipe->processEmail()))
           return $pipe->response(201,
                   is_object($ticket) ? $ticket->getNumber() : $ticket);

        return $pipe->exerr(416, __('Request failed - retry again!'));
    }

    static function local() {
        return self::process('cli');
    }
}

class TicketApiError extends Exception {

    // Check if exception is because of denial
    public function isDenied() {
        return ($this->getCode() === 403);
    }
}

class TicketDenied extends Exception {}
class EmailParseError extends Exception {}

?>















