<?php
include_once INCLUDE_DIR.'class.api.php';
include_once INCLUDE_DIR.'class.list.php';

class StatusApiController extends ApiController {
    function getStatuses() {
        $this->validateAndExecute([$this, '_getStatuses']); 
    }

    function _getStatuses() {
        $statuses = array();
        $statusList = TicketStatusList::getStatuses()->all(); 
        
        foreach ($statusList as $s) {
            $statuses[] = array(
                'id' => $s->ht['id'],
                'name' => $s->ht['name'],
            );
        }
        
        if ($statuses) {
            $resp = json_encode(array('statuses' => $statuses), JSON_PRETTY_PRINT);
            $this->response(200, $resp);
        } else {
            $this->exerr(500, _S(API::ERR_INTERNAL_SERVER_ERROR));
        }
    }
}
?>