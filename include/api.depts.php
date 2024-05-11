<?php

include_once INCLUDE_DIR.'class.api.php';
include_once INCLUDE_DIR.'class.dept.php';

class DeptApiController extends ApiController {
    function getDepartments() {
        $this->validateAndExecute([$this, '_getDepartments']);
    }
    
    function createDepartment($format) {
        $this->validateAndExecute([$this, '_createDepartment'], $format);
    }
    
    function updateDepartment($format) {
        $this->validateAndExecute([$this, '_updateDepartment'], $format);
    }
    
    function deleteDepartment($id=null) {
        $this->validateAndExecute([$this, '_deleteDepartment'], $id);
    }
    
    function _getDepartments() {
        $deptIds = array();
        $deptNames = Dept::getDepartments();
        
        foreach ($deptNames as $name) {
            $id = Dept::getIdByName($name);
            if ($id) {
                $deptIds[] = array(
                    'id' => $id,
                    'name' => $name
                );
            } else { break; }
        }
        
        if ($deptIds) {
            $resp = json_encode(array('deptIds' => $deptIds), JSON_PRETTY_PRINT);
            $this->response(200, $resp);
        } else {
            $this->exerr(500, _S(API::ERR_INTERNAL_SERVER_ERROR));
        }
    }
    
    function _createDepartment($format) {
        $vars = $this->getRequest($format);
        $vars = array_merge($vars, ['flags' => Dept::FLAG_ACTIVE]);
        
        $errors = array();
        $dept = Dept::__create($vars, $errors);
        
        if ($dept) {
            $this->response(201, json_encode($dept->getId()), JSON_PRETTY_PRINT);
        } else {
            $this->exerr(500, _S($errors));
        }
    }
        
    function _updateDepartment($format) {
        $vars = $this->getRequest($format);
        
        if (!$vars['id']) {
            $this->response(400, _S(API::ERR_MISSING_ID));
        }
        if (!$vars['name'] && !$vars['sla_id'] && !$vars['ispublic']) {
            $this->response(400, _S(API::ERR_MISSING_UPDATABLE_PROPERTIES));
        }
        
        if (in_array($vars['name'], Dept::getDepartments())) {
            $this->response(400, _S("name: Department already exists"));
        }
        
        $dept = Dept::lookup($vars['id']);
        
        if (!$dept) {
            $this->response(404, _S("Department not found"));
        }
        
        // Merge existing properties with updated ones to ensure retention of unchanged data
        $vars = array_merge($dept->ht, $vars);
        
        $errors = array();
        $dept->update($vars, $errors);
        $dept->save();
        
        if ($dept) {
            $this->response(204, _S("Department updated successfully"));
        } else {
            $this->exerr(500, _S($errors));
        }
    }
    
    function _deleteDepartment($id=null) {
        if (!$id || !is_numeric($id)) {
            $this->response(400, _S(API::ERR_INVALID_QUERY_PARAMETER));
        }
        
        $dept = Dept::lookup($id);
        
        if (!$dept) {
            $this->response(404, _S("Department not found"));
        }
        
        if ($dept->delete()) {
            $this->response(204, _S("Department deleted successfully"));
        } else {
            $this->exerr(500, _S(API::ERR_INTERNAL_SERVER_ERROR));
        }
    }
}

?>