<?php
/*********************************************************************
    http.php

    HTTP controller for the osTicket API

    Jared Hancock
    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
require 'api.inc.php';
# Include the main api urls
require_once INCLUDE_DIR."class.dispatcher.php";
$dispatcher = patterns('',
        url('^/tasks/', patterns('',
                url_post("^cron$", array('api.cron.php:CronApiController', 'execute'))
         )),
        // Departments
        url_get("^/departments\.(xml|json|email)$", array('api.depts.php:DeptApiController','getDepartments')),
        url_post("^/departments\.(?P<format>xml|json|email)$", array('api.depts.php:DeptApiController','createDepartment')),
        url_put("^/departments\.(?P<format>xml|json|email)$", array('api.depts.php:DeptApiController','updateDepartment')),
        url_delete("^/departments", array('api.depts.php:DeptApiController','deleteDepartment')),
        // SLAs
        url_get("^/sla\.(xml|json|email)$", array('api.sla.php:SlaApiController','getSla')),
        url_post("^/sla\.(?P<format>xml|json|email)$", array('api.sla.php:SlaApiController','createSla')),
        url_put("^/sla\.(?P<format>xml|json|email)$", array('api.sla.php:SlaApiController','updateSla')),
        url_delete("^/sla", array('api.sla.php:SlaApiController','deleteSla')),
        // Staff
        url_get("^/staff\.(xml|json|email)$", array('api.staff.php:StaffApiController','getStaffMembers')),
        url_post("^/staff\.(?P<format>xml|json|email)$", array('api.staff.php:StaffApiController','createStaffMember')),
        url_put("^/staff\.(?P<format>xml|json|email)$", array('api.staff.php:StaffApiController','updateStaffMember')),
        url_delete("^/staff", array('api.staff.php:StaffApiController','deleteStaffMember')),
        // Teams
        url_get("^/teams\.(xml|json|email)$", array('api.teams.php:TeamsApiController','getTeams')),
        url_post("^/teams\.(?P<format>xml|json|email)$", array('api.teams.php:TeamsApiController','createTeam')),
        url_put("^/teams\.(?P<format>xml|json|email)$", array('api.teams.php:TeamsApiController','updateTeam')),
        url_delete("^/teams", array('api.teams.php:TeamsApiController','deleteTeam')),
        // Tickets
        url_get("^/tickets", array('api.tickets.php:TicketApiController','getTickets')),        
        url_put("^/tickets\.(?P<format>xml|json|email)$", array('api.tickets.php:TicketApiController','updateTicket')),
        url_delete("^/tickets", array('api.tickets.php:TicketApiController','deleteTicket')),
        url_post("^/tickets\.(?P<format>xml|json|email)$", array('api.tickets.php:TicketApiController','create')),
        //Ticket Priorities
        url_get("^/priorities\.(xml|json|email)$", array('api.priorities.php:PriorityApiController','getPriorities')),
        //Ticket Status
        url_get("^/status\.(xml|json|email)$", array('api.status.php:StatusApiController','getStatuses')),
        //Ticket Topics
        url_get("^/topics\.(xml|json|email)$", array('api.topics.php:TopicApiController','getTopics')),
        url_post("^/topics\.(?P<format>xml|json|email)$", array('api.topics.php:TopicApiController','createTopic')),
        url_put("^/topics\.(?P<format>xml|json|email)$", array('api.topics.php:TopicApiController','updateTopic')),
        url_delete("^/topics", array('api.topics.php:TopicApiController','deleteTopic')),
        // Users
        url_get("^/users\.(xml|json|email)$", array('api.users.php:UserApiController','getUsers')),
        url_post("^/users\.(?P<format>xml|json|email)$", array('api.users.php:UserApiController','createUser')),
        url_put("^/users\.(?P<format>xml|json|email)$", array('api.users.php:UserApiController','updateUser')),
        url_delete("^/users", array('api.users.php:UserApiController','deleteUser'))
    );

// Send api signal so backend can register endpoints
Signal::send('api', $dispatcher);
# Call the respective function
print $dispatcher->resolve(Osticket::get_path_info());
?>
