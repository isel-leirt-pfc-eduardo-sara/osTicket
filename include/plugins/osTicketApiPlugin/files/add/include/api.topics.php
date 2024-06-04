<?php

include_once INCLUDE_DIR.'class.api.php';
include_once INCLUDE_DIR.'class.topic.php';
include_once INCLUDE_DIR.'class.dept.php';
include_once INCLUDE_DIR.'class.sla.php';
include_once INCLUDE_DIR.'class.priority.php';

class TopicApiController extends ApiController {
    function getTopics() {
        $this->validateAndExecute([$this, '_getTopics']); 
    }
    
    function createTopic($format) {
        $this->validateAndExecute([$this, '_createTopic'], $format);
    }
    
    function updateTopic($format) {
        $this->validateAndExecute([$this, '_updateTopic'], $format);
    }
    
    function deleteTopic($id=null) {
        $this->validateAndExecute([$this, '_deleteTopic'], $id);
    }
    
    function _getTopics() {
        $topics = array();
        $allHelpTopics = Topic::getAllHelpTopics(); 
        
        foreach ($allHelpTopics as $id => $name) {
                $topics[] = array(
                    'id' => $id,
                    'name' => $name
                );
        }
        
        if ($topics) {
            $resp = json_encode(array('topics' => $topics), JSON_PRETTY_PRINT);
            $this->response(200, $resp);
        } else {
            $this->exerr(500, _S(API::ERR_INTERNAL_SERVER_ERROR));
        }
    }
    
    function _createTopic($format) {
        $vars = $this->getRequest($format);
        
        
        $errors = array();
        $topic = Topic::__create($vars, $errors);
        
        if ($topic) {
            $this->response(201, json_encode($topic->getId()), JSON_PRETTY_PRINT);
        } else {
            $this->exerr(500, _S($errors));
        }
    }
        
    function _updateTopic($format) {
        $vars = $this->getRequest($format);
        
        if (!$vars['id']) {
            $this->response(400, _S(API::ERR_MISSING_ID));
        }
        if (!$vars['topic'] && !$vars['ispublic'] && !$vars['dept_id'] && !$vars['priority_id'] && !$vars['sla_id']) {
            $this->response(400, _S(API::ERR_MISSING_UPDATABLE_PROPERTIES));
        }
        
        $topic = Topic::lookup($vars['id']);
        
        if (!$topic) {
            $this->response(404, _S("Topic not found"));
        }
        
        if(isset($vars['dept_id'])){
            $dept = Dept::lookup($vars['dept_id']);
        
            if (!$dept) {
                $this->response(404, _S("Department not found"));
            }
        }

        if(isset($vars['priority_id'])){
            $prio = Priority::lookup($vars['priority_id']);
        
            if (!$prio) {
                $this->response(404, _S("Priority not found"));
            }
        }

        if(isset($vars['sla_id'])){
            $sla = SLA::lookup($vars['sla_id']);
        
            if (!$sla) {
                $this->response(404, _S("SLA not found"));
            }
        }
        
        // Merge existing properties with updated ones to ensure retention of unchanged data
        $vars = array_merge($topic->ht, $vars);
        
        $errors = array();
        if ($topic->update($vars, $errors)) {
            $this->response(204, _S("Topic updated successfully"));
        } else {
            $this->exerr(500, _S($errors));
        }
    }
    
    function _deleteTopic($id=null) {
        if (!$id || !is_numeric($id)) {
            $this->response(400, _S(API::ERR_INVALID_QUERY_PARAMETER));
        }
        
        $topic = Topic::lookup($id);
        
        if (!$topic) {
            $this->response(404, _S("Topic not found"));
        }
        
        if ($topic->delete()) {
            $this->response(204, _S("Topic deleted successfully"));
        } else {
            $this->exerr(500, _S(API::ERR_INTERNAL_SERVER_ERROR));
        }
    }
}

?>