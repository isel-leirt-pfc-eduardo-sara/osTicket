<?php

include_once INCLUDE_DIR.'bootstrap.php';
require 'config.php';

define('PLUGIN_FILES_DIR', INCLUDE_DIR.'plugins/osTicketApiPlugin/files/');
define('ADD_DIR', PLUGIN_FILES_DIR.'add/');
define('INSERT_DIR', PLUGIN_FILES_DIR.'insert/');

define('EXPECTED_VERSION', '1.18');

class osTicketApiPlugin extends Plugin {
    var $config_class = 'osTicketApiPluginConfig'; // Necessary for the plugin system to find the configuration
    
    function bootstrap() {
        if (MAJOR_VERSION !== EXPECTED_VERSION) {
            $this->delete(); // Delete plugin instance
            throw new Exception(
                "osTicket version mismatch: Expected '".EXPECTED_VERSION."' but found '".MAJOR_VERSION."'. "
                ."Plugin instance deleted."
            );
        }
        if ($this->isFirstRun()) {
            $this->doFirstTimeSetup();
        }
    }
    
    function isFirstRun() {
        $firstRun = $this->getConfig()->get('first_run');
        if ($firstRun) {
            $this->getConfig()->set('first_run', false);
            return true;
        }
        return false;
    }
    
    function doFirstTimeSetup() {
        $this->addPluginFiles();
        $this->insertPluginCode();
        
        global $msg;
        $msg = 'First run configuration completed';
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
            throw new Exception("Could not replace temporary file with destination file.");
        }
    }
}

?>
