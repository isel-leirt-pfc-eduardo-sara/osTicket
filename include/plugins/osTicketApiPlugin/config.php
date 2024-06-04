<?php

class osTicketApiPluginConfig extends PluginConfig {
    // Define the options that will be available for this plugin
    function getOptions() {
        return array(
            'first_run' => new BooleanField(array(
                'id' => 'first_run',
                'label' => 'First Run',
                'configuration' => array(
                    'desc' => 'Running for the first time?'
                )
            ))
        );
    }
}

?>
