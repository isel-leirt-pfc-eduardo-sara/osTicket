<?php

include_once INCLUDE_DIR.'bootstrap.php';
require_once(INCLUDE_DIR."/class.plugin.php");
require 'config.php';

define('PLUGIN_FILES_DIR', INCLUDE_DIR.'plugins/osTicketApiPlugin/files/');
define('ADD_DIR', PLUGIN_FILES_DIR.'add/');
define('INSERT_DIR', PLUGIN_FILES_DIR.'insert/');

define('EXPECTED_VERSION', '1.18');
define('SESSION_PREVIOUS_STATE_PLUGIN_PREFIX', 'previousActiveStatePlugin_');

class osTicketApiPlugin extends Plugin {
    const ERR_PLUGIN_DISABLED = 'osTicket-API Plugin is currently disabled';
    var $config_class = 'osTicketApiPluginConfig'; // Necessary for the plugin system to find the configuration
    
    function bootstrap() {
        if (MAJOR_VERSION !== EXPECTED_VERSION) {
            $this->delete(); // Delete plugin instance
            throw new Exception(
                "osTicket version mismatch: Expected '".EXPECTED_VERSION."' but found '".MAJOR_VERSION."'. "
                ."Plugin instance deleted."
            );
        }
        $this->integrate();
        $this->cleanup();
    }

     /**
    *Checks if plugin is globaly active
    *
    * @return bool True if active, false otherwise.
    */
    function isActive() {
        $currentlyActive = parent::isActive();
        $previouslyActive = $this->getCurrentActiveStatePlugin();
        if ($previouslyActive !== $currentlyActive) {
            $this->handleActivationChange($currentlyActive);
            $this->setCurrentActiveStatePlugin($currentlyActive);
        }
        return $currentlyActive;
    }
   
     /**
    *Handles plugin activation change.
    *
    * @param bool $currentlyActive The current state of the plugin.
    * @return void
    */
    private function handleActivationChange($currentlyActive) {
        error_log("Plugin is being " . ($currentlyActive ? "activated" :
        "deactivated") . ".");
        if ($currentlyActive) {
            $this->enablePlugin();
        } else {
            $this->disablePlugin();
        }
    }

    /**
    * Logic to be executed when the plugin is activated.
    *
    * @return void
    */
    function enablePlugin() {
        global $msg;
        $msg = 'Plugin enabled successfully.';
    }

    /*
    /**
     * Logic to be executed when the plugin is disabled.
     * @return void
     */
    function disablePlugin() { 
        global $msg;
        $msg = 'Plugin disabled successfully.';
    }
    
    function integrate() {
        $deploy = $this->getConfig()->get('integrate');
        if ($deploy) {
            $this->getConfig()->set('integrate', false);
            $this->addPluginFiles();
            $this->insertPluginCode();
            global $msg;
            $msg = 'Plugin code integrated successfully.';
        }
    }
    
    function cleanup() {
        $cleanup = $this->getConfig()->get('cleanup');
        if ($cleanup) {
            $this->getConfig()->set('cleanup', false);
            $this->removePluginFiles();
            $this->undoPluginCodeInsertions();
            global $msg;
            $msg = 'Plugin code removed successfully.';
        }
    }
    
    function addPluginFiles() {
        $this->recursiveCopy(ADD_DIR, ROOT_DIR);

        global $msg;
        $msg = 'Plugin files added to the codebase successfully.';
    }
    
    function insertPluginCode() {
        $this->regexInsert(INSERT_DIR.'api/http.json', ROOT_DIR.'api/http.php');
        $this->regexInsert(INSERT_DIR.'include/class.api.json', INCLUDE_DIR.'class.api.php');
        $this->regexInsert(INSERT_DIR.'include/class.dispatcher.json', INCLUDE_DIR.'class.dispatcher.php');
        $this->regexInsert(INSERT_DIR.'include/api.tickets.json', INCLUDE_DIR.'api.tickets.php');
        
        global $msg;
        $msg = 'Plugin code inserted into the codebase successfully.';
    }

    function recursiveCopy($srcDir, $dstDir) {
        // Ensure the source directory exists
        if (!is_dir($srcDir)) {
            throw new Exception("Source directory does not exist: "."'".$srcDir.".");
        }

        $dir = opendir($srcDir);
        @mkdir($dstDir);

        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($srcDir.'/'.$file)) {
                    $this->recursiveCopy($srcDir.'/'.$file, $dstDir.'/'.$file);
                } else {
                    copy($srcDir.'/'.$file, $dstDir.'/'.$file);
                }
            }
        }
        closedir($dir);
    }
    
    function regexInsert($srcJson, $dstFile) {
        // Read original file content
        $content = @file_get_contents($dstFile);
        if ($content === false) {
            throw new Exception("Could not read file: '".$dstFile."'");
        }

        // Read the insertions from the JSON file
        $insertionsJson = @file_get_contents($srcJson);
        if ($insertionsJson === false) {
            throw new Exception("Could not read insertions JSON file: '".$srcJson."'");
        }

        // Decode the JSON into an associative array
        $insertions = json_decode($insertionsJson, true);
        if ($insertions === null) {
            throw new Exception("Error decoding JSON file: '".$srcJson."'");
        }

        // Validate JSON structure
        foreach ($insertions as $index => $insertion) {
            if (!isset($insertion['pattern']) || !isset($insertion['newLines'])) {
                throw new Exception("Invalid JSON structure at index ".$index);
            }
        }

        // Process each insertion
        foreach ($insertions as $insertion) {
            $pattern = $insertion['pattern'];
            $newLines = $insertion['newLines'];

            // Convert new lines array to string
            $newLinesString = implode(PHP_EOL, $newLines);

            // Check if the new lines are already in the content
            if (strpos($content, $newLinesString) === false) {
                // Insert the new lines after the matched pattern
                $content = preg_replace_callback(
                    $pattern,
                    function ($matches) use ($newLinesString) {
                        return $matches[0].PHP_EOL.$newLinesString;
                    },
                    $content,
                    1 // Limit set to 1 replacement per match
                );

                if ($content === null) {
                    throw new Exception("Regex error occurred while processing pattern: '".$pattern."'");
                }
            }
        }

        // Write the modified content to a temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'tmp');
        if ($tempFile === false) {
            throw new Exception("Could not create temporary file.");
        }

        if (file_put_contents($tempFile, $content) === false) {
            throw new Exception("Could not write to temporary file: '".$tempFile."'");
        }

        // Replace the temporary file with the destination file
        if (!rename($tempFile, $dstFile)) {
            throw new Exception("Could not replace temporary file with destination file. $dstFile");
        }
    }

    function removePluginFiles() { 
        $srcDir = INCLUDE_DIR;
        $dstDir = ADD_DIR.'include/';
        $this->rmPlugFiles($srcDir, $dstDir);

        global $msg;
        $msg = 'Plugin files removed from the codebase successfully.';
    }

    function undoPluginCodeInsertions() {
        $this->regexUndoInsert(INSERT_DIR.'api/http.json', ROOT_DIR.'api/http.php');
        $this->regexUndoInsert(INSERT_DIR.'include/class.api.json', INCLUDE_DIR.'class.api.php');
        $this->regexUndoInsert(INSERT_DIR.'include/class.dispatcher.json', INCLUDE_DIR.'class.dispatcher.php');
        $this->regexUndoInsert(INSERT_DIR.'include/api.tickets.json', INCLUDE_DIR.'api.tickets.php');
        
        global $msg;
        $msg = 'Plugin code insertions undone successfully.';
    }

    function rmPlugFiles($srcDir, $dstDir) {
        if (!is_dir($srcDir)) {
            throw new Exception("Source directory does not exist: '".$srcDir."'.");
        }

        $dir = opendir($srcDir);

        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                $srcFile = $srcDir . '/' . $file;
                if (is_file($srcFile) && pathinfo($srcFile, PATHINFO_EXTENSION) === 'php') {
                    // Checks if the file exists in the destination directory
                    $dstFile = $dstDir . '/' . $file;
                    if (file_exists($dstFile)) {
                        // Removes the source file if it exists in the destination directory
                        unlink($srcFile);
                    }
                }   
            }
        }
        closedir($dir);
    }

    function regexUndoInsert($srcJson, $dstFile) {
         // Read original file content from destination file
        $content = @file_get_contents($dstFile);
        if ($content === false) {
            throw new Exception("Could not read file: '".$dstFile."'");
        }
    
        // Read the previous insertions from the JSON file
        $removalsJson = @file_get_contents($srcJson);
        if ($removalsJson === false) {
            throw new Exception("Could not read previous insertions JSON file: '".$srcJson."'");
        }
    
        // Decoding the JSON string into an associative array
        $removals = json_decode($removalsJson, true);
        if ($removals === null) {
            throw new Exception("Error decoding JSON file: '".$srcJson."'");
        }
    
         // Validate JSON structure
        foreach ($removals as $index => $removal) {
            if (!isset($removal['pattern']) || !isset($removal['newLines'])) {
                throw new Exception("Invalid JSON structure at index ".$index);
            }
        }
    
        // Process each insertion removal
        foreach ($removals as $removal) {
            $pattern = $removal['pattern'];
            $newLines = $removal['newLines'];
            
            // Converts the array of new lines to a string
            $newLinesString = implode(PHP_EOL, $newLines);
    
            // Remove only if the inserted lines are still present in the file
            if (strpos($content, $newLinesString) !== false) {
                
                // Remove newlines after matching pattern
                $content = preg_replace(
                    '/'.preg_quote($newLinesString, '/').'/', 
                    '', 
                    $content,
                    1 // Limit 1 substitution per match
                );
    
                if ($content === null) {
                    throw new Exception("Regex error occurred while processing pattern: '".$pattern."'");
                }
            }
        }
    
        // Write the modified content back to the detination file
        if (file_put_contents($dstFile, $content) === false) {
            throw new Exception("Unable to write to file: '".$dstFile."'");
        }
    }

     /**
    * Sets the current active state in the session for the plugin.
    *
    * @param bool $state The current active state.
    */
    private function setCurrentActiveStatePlugin($state) {
        $_SESSION[SESSION_PREVIOUS_STATE_PLUGIN_PREFIX] = $state;
    }

    /**
    * Retrieves the current active state of the plugin.
    *
    * @return bool|null The current active state, or null if not set.
    */
    private function getCurrentActiveStatePlugin() {
        return $_SESSION[SESSION_PREVIOUS_STATE_PLUGIN_PREFIX] ?? null;
    }
}

?>
