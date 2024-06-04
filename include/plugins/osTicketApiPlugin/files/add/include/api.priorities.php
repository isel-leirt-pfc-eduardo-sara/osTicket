<?php
include_once INCLUDE_DIR.'class.api.php';
include_once INCLUDE_DIR.'class.priority.php';

class PriorityApiController extends ApiController {
    function getPriorities() {
        $this->validateAndExecute([$this, '_getPriorities']); 
    }

    function _getPriorities() {
        $prio = array();
        $priorities = Priority::getPriorities(); 
        
        foreach ($priorities as $id => $name) {
            $prio[] = array(
                'id' => $id,
                'name' => $name
            );
        }
        
        if ($priorities) {
            $resp = json_encode(array('priorities' => $prio), JSON_PRETTY_PRINT);
            $this->response(200, $resp);
        } else {
            $this->exerr(500, _S(API::ERR_INTERNAL_SERVER_ERROR));
        }
    }
}
?>