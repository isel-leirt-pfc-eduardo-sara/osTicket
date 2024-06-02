<?php

include_once INCLUDE_DIR.'class.api.php';
include_once INCLUDE_DIR.'class.sla.php';

class SlaApiController extends ApiController {
    function getSla() {
        $this->validateAndExecute([$this, '_getSla']); 
    }
    
    function createSla($format) {
        $this->validateAndExecute([$this, '_createSla'], $format);
    }
    
    function updateSla($format) {
        $this->validateAndExecute([$this, '_updateSla'], $format);
    }

    function deleteSla($id=null) {
        $this->validateAndExecute([$this, '_deleteSla'], $id);
    }

    //Auxiliary functions
    function _getSla() {
        $slaList = SLA::getSLAs();
        
        $slaArray = array();
        foreach ($slaList as $id => $name) {

            $parts = explode('(', $name);
            $slaName = trim($parts[0]);

            $slaArray[] = array(
                'id' => $id,
                'name' => $slaName
            );
        }

        if ($slaArray) {
            $resp = json_encode(array('slas' => $slaArray), JSON_PRETTY_PRINT);
            $this->response(200, $resp);
        } else {
            $this->exerr(500, _S(API::ERR_INTERNAL_SERVER_ERROR));
        }
    }
    
    function _createSla($format) {
        $vars = $this->getRequest($format);
        $errors = array();
        
        if (empty($vars['name'])) {
            $errors['name'] = __('Name is required');
        }
        if (empty($vars['grace_period'])) {
            $errors['grace_period'] = __('Grace period is required');
        } elseif (!is_numeric($vars['grace_period'])) {
            $errors['grace_period'] = __('Grace period must be numeric');
        }

        if ($errors) {
            $this->response(400, json_encode($errors));
            return;
        }
        
        $sla = SLA::__create($vars, $errors);
        
        if ($sla) {
            $this->response(201, json_encode($sla->getId()), JSON_PRETTY_PRINT);
        } else {
            $this->exerr(500, _S($errors));
        }
    }
    
    function _updateSla($format) {
        $vars = $this->getRequest($format);
        $errors = array();

        if (empty($vars['id'])) {
            $this->response(400, _S(API::ERR_MISSING_ID));
        }
        if (empty($vars['name']) && empty($vars['grace_period'])) {
            $this->response(400, _S(API::ERR_MISSING_UPDATABLE_PROPERTIES));
        }
        
        $sla = SLA::lookup($vars['id']);
        
        if (!$sla) {
            $this->exerr(404, _S("SLA not found"));
            return;
        }
        
        // Merge existing properties with updated ones
        $vars = array_merge($sla->ht, $vars);

        if (!$sla->update($vars, $errors)) {
            $this->response(400, json_encode($errors));
            return;
        }

        if ($sla->save()) {
            $this->response(204, _S("SLA updated successfully")); 
        } else {
            $this->exerr(500, _S("Internal error occurred while saving SLA"));
        }
    }

    function _deleteSla($id=null) {
        if (!$id || !is_numeric($id)) {
            $this->response(400, _S(API::ERR_INVALID_QUERY_PARAMETER));
            return;
        }
        
        $sla = SLA::lookup($id);
        
        if (!$sla) {
            $this->response(404, _S("SLA not found"));
            return;
        }
        
        if ($sla->delete()) {
            $this->response(204, _S("SLA deleted successfully"));
        } else {
            $this->exerr(500, _S(API::ERR_INTERNAL_SERVER_ERROR));
        }
    }
}

?>