<?php

class osTicketApiPluginConfig extends PluginConfig {
    // Define the options that will be available for this plugin
    function getOptions() {
        return array(
            'integrate' => new BooleanField(array(
                'id' => 'integrate',
                'label' => 'Integrate',
                'configuration' => array(
                    'desc' => 'Insert/Add plugin code/files'
                )
            )),
            'cleanup' => new BooleanField(array(
                'id' => 'cleanup',
                'label' => 'Cleanup',
                'configuration' => array(
                    'desc' => 'Remove plugin code/files'
                )
            ))
        );
    }
}

?>
