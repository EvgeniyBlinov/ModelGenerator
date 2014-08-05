<?php
/**
 * ModelGenerator
 * Copyright (c) 2014 Evgeniy Blinov (http://blinov.in.ua/)
 * 
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 * 
 * @link       https://github.com/EvgeniyBlinov/ModelGenerator for the canonical source repository
 * @author     Evgeniy Blinov <evgeniy_blinov@mail.ru>
 * @copyright  Copyright (c) 2014 Evgeniy Blinov
 * @license    http://www.opensource.org/licenses/mit-license.php MIT License
 */
defined('SCRIPT_NAME') or define('SCRIPT_NAME', $argv[0]);
$scriptStatus = 0;

/**
 * @var array of default config params
 */
$config = array(
    'port'     => '3306',
    'host'     => '127.0.0.1',
    'fileMode' => 'x+'
);

/**
 * @var array of required config params
 */
$configRequireParams = array(
    'database-name',
    'user',
    'password',
    'config'
);

/**
 * Print usage info
 * @param integer $scriptStatus
 * @return void
 */
function usage($scriptStatus = 100) 
{
    $scriptName = SCRIPT_NAME;
echo<<<EOF
Usage: {$scriptName} [parameter]

parameters:
    --database-name - mysql database name
    --user          - mysql user name
    --password      - mysql user password
    --host          - mysql host
    --verbose       - verbose mode
    --config        - JSON string configuration for generator
EOF;
    echo "\n";
    exit((integer) $scriptStatus);
}

/**
 * Convert underscores to CamelCase in string
 * @param string $string
 * @return string
 */
function underscore2CC($string)
{
    return preg_replace('/_([A-z])/e', 'strtoupper("$1")', ucfirst($string));
}

// apply config params
for ($i = 1; $i < count($argv); $i++) {
    if (preg_match('/^--(?<param>[^$]*)/', $argv[$i], $matches)) {
        if (isset($matches['param'])) {
            if (in_array($matches['param'], array('verbose'))) {
                $config[$matches['param']] = true;
            } else {
                $config[$matches['param']] = $argv[++$i];
            }
        }
    }
}

// call usage() if required params not found 
if (count(array_intersect_key($config, array_flip($configRequireParams))) != count($configRequireParams)) {
    usage(101);
}

/**
 * Get DB meta data
 * @param array $config
 * @return array
 */
function getDBMetaData($config)
{
    try {
        $db = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s', $config['host'], $config['port'], $config['database-name']), $config['user'], $config['password']);

        $stmt = $db->prepare(sprintf('
                    SELECT TABLE_NAME
                    FROM INFORMATION_SCHEMA.TABLES
                    WHERE TABLE_TYPE = "BASE TABLE" AND TABLE_SCHEMA="%s"', $config['database-name']));

        // call the stored procedure
        $stmt->execute();
        
        $tables = array_map(function($record){ return $record['TABLE_NAME']; }, $stmt->fetchAll());
        $allTables = array();
        
        for ($i = 0, $tablesCount = count($tables); $i < $tablesCount; $i++) {
            $stmt = $db->prepare(sprintf('SHOW columns FROM `%s`', $tables[$i]));
            $stmt->execute();
            $allTables[$tables[$i]] = array('columns' => array_map(function($record){ return array('name' => $record['Field'], 'type' => $record['Type']); }, $stmt->fetchAll()));
        }
        
        return $allTables;
        
    } catch (PDOException $e) {
        echo "Error!: " . $e->getMessage() . "<\n";
        exit(200);
    }
}

/**
 * Validate generator config
 * @param array $generatorConfigParams
 * return mixed
 */
function validateGeneratorConfig($generatorConfigParams)
{
    $generatorConfigRequiredParams = array('template', 'output');
    $generatorConfig = array_intersect_key($generatorConfigParams, array_flip($generatorConfigRequiredParams));
    if (count($generatorConfig) == count($generatorConfigRequiredParams)) {
        return $generatorConfigParams;
    }
    return;
}

/**
 * Get generator config objecs
 * @param array $config
 * @return array
 */
function getGeneratorConfigObjecs($config)
{
    if (($generatorConfigParams = json_decode($config['config'], true)) && is_array($generatorConfigParams)) {
        $validatorResult = validateGeneratorConfig($generatorConfigParams);
        if (null != $validatorResult) {
            return array(validateGeneratorConfig($generatorConfigParams));
        } else {
            $generatorConfigParamsMulti = array();
            foreach ($generatorConfigParams as $key=>$generatorConfigParam) {
                $validatorResult = validateGeneratorConfig($generatorConfigParam);
                if (null != $validatorResult) {
                    $generatorConfigParamsMulti[] = $validatorResult;
                }
            }
            
            if (count($generatorConfigParamsMulti)) {
                return $generatorConfigParamsMulti;
            }
        }
    }
    echo "Error: option --config should be JSON string!\n";
    usage(102);
}

$allTables = getDBMetaData($config);
$generatorConfigObjecs = getGeneratorConfigObjecs($config);

$models = array();
// for each generator objects
foreach ($generatorConfigObjecs as $GCobject) {    
    // make directories if not exists
    if (!is_dir($GCobject['output'])) {
        mkdir($GCobject['output'], 0777, true);
    }
    
    if (!file_exists($GCobject['template'])) {
        echo 'Error: Template file ', $GCobject['template'], " not found!\n";
        usage(103);
    }
    
    // for each DB tables
    foreach ($allTables as $tableName => $tableData) {
        // extract base name of path
        preg_match('/(?<name>[^\/]+)\..+$/', $GCobject['template'], $matches);
        // get template to variable
        ob_start();
        include ($GCobject['template']);
        $template = ob_get_contents();
        ob_end_clean();

        // write code to file
        $fileMode = isset($GCobject['mode']) ? $GCobject['mode'] : $config['fileMode'];
        $fileName = sprintf('%s/%s%s.php', $GCobject['output'], underscore2CC($tableName), $matches['name']);
        if ($handle = @fopen($fileName, $fileMode)) {
            fwrite($handle, $template);
            fclose($handle);
            if (isset($config['verbose'])) {
                echo "File $fileName successfully created.\n";
            }
        } else {
            if (isset($config['verbose'])) {
                echo "File $fileName not created.\n";
            }
        }
    }
}

echo "All operations done.\n";
exit($scriptStatus);
