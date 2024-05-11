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
        url_get("^/departments\.xml|json|email$", array('api.depts.php:DeptApiController','getDepartments')),
        url_post("^/departments\.(?P<format>xml|json|email)$", array('api.depts.php:DeptApiController','createDepartment')),
        url_put("^/departments\.(?P<format>xml|json|email)$", array('api.depts.php:DeptApiController','updateDepartment')),
        url_delete("^/departments\.xml|json|email", array('api.depts.php:DeptApiController','deleteDepartment')),
        // Tickets
        url_post("^/tickets\.(?P<format>xml|json|email)$", array('api.tickets.php:TicketApiController','create'))
    );

// Send api signal so backend can register endpoints
Signal::send('api', $dispatcher);
# Call the respective function
print $dispatcher->resolve(Osticket::get_path_info());
?>
