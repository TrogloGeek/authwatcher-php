<?php

function errcho($msgs)
{
    $msgs = (array) $msgs;
    foreach ($msgs as $msg) {
        fwrite(STDERR, $msg . PHP_EOL);
    }
}

function clidie($msgs, $status = 0)
{
    errcho($msgs);
    exit($status);
}

if (php_sapi_name() !== 'cli') {
    clidie('Wrong PHP SAPI', 1);
}

$options_available = array(
        array('c', 'iniconf', ':', 'Read INI config from this file', dirname(__FILE__).DIRECTORY_SEPARATOR.'authwatcher.ini'),
        array('l', 'filters', ':', 'Read filters from this INI section', 'logprocessor'),
        array('f', 'logfile', ':', 'Log file to process', '/var/log/auth.log')
);

function print_usage()
{
    global $argv;
    global $options_available;
    echo 'usage: '.basename($argv[0]).' [options]'.PHP_EOL;
    echo basename($argv[0]).' --help: prints this help message'.PHP_EOL;
    echo basename($argv[0]).' -h: prints this help message'.PHP_EOL;
    echo 'options:'.PHP_EOL;
    foreach ($options_available as $opt) {
        switch ($opt[2]) {
            case ':':
                echo '-'.$opt[0].' <value>'.PHP_EOL;
                echo '--'.$opt[1].' <value>'.PHP_EOL;
                echo str_repeat(' ', 8).$opt[3].PHP_EOL;
                echo str_repeat(' ', 8).'Default value: '.$opt[4].PHP_EOL;
            break;
        }
    }
    exit(0);
}

if ($argc == 2 && in_array($argv[1], array('-h', '--help'))) {
    print_usage();
}

$shortopts = '';
$longopts = array();
$optvalues = array();
$shortmap = array();
foreach ($options_available as $opt) {
    $shortmap[$opt[0]] = $opt[1];
    $shortopts .= $opt[0].$opt[2];
    $longopts[] = $opt[1].$opt[2];
    if (!empty($opt[2])) {
        $optvalues[$opt[1]] = $opt[4];
    }
}

$opts = getopt($shortopts, $longopts);
if ($opts === false) {
    print_usage();
}
foreach ($opts as $k => $v) {
    if (isset($shortmap[$k])) {
        $k = $shortmap[$k];
    }
    $optvalues[$k] = $v;
}

$logfile = $optvalues['logfile'];
$iniconf = $optvalues['iniconf'];
$filters_section = $optvalues['filters'];

if (!file_exists($logfile)) {
    clidie($logfile . ' does not exist or is unreachable', 2);
} elseif (!is_file($logfile)) {
    clidie($logfile . ' is not a regular file', 2);
} elseif (!is_readable($logfile)) {
    clidie($logfile . ' cannot be read', 2);
}

$filterdir = dirname(__FILE__).DIRECTORY_SEPARATOR.'filter.d';
$config_all = parse_ini_file($iniconf, true, INI_SCANNER_NORMAL);
if ($config_all === false) {
    clidie('Error reading configuration '.$iniconf, 3);
} elseif (!isset($config_all['logprocessor'])) {
    clidie('Missing configuration section [logprocessor] in '.$iniconf, 3);
} elseif (!isset($config_all[$filters_section])) {
    clidie('Missing configuration section ['.$filters_section.'] in '.$iniconf, 3);
}
$config = $config_all['logprocessor'];
$filters_config = $config_all[$filters_section];
define('DATE_FORMAT', $filters_config['date_format']);
if (empty($filters_config['filters'])) {
    clidie('Empty filters list ['.$filters_section.'].filters', 4);
}
$filter_instances = array();
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'IFilter.php';
foreach ($filters_config['filters'] as $filter) {
    require $filterdir.DIRECTORY_SEPARATOR.$filter.'.php';
    $filter_instance = new $filter();
    if (!($filter_instance instanceof IFilter)) {
        errcho('Filter '.$filter.' does not implement IFilter interface, filter is ignored');
        continue;
    }
    if (isset($config_all['filter_'.$filter])) {
        $filter_instance->init($config_all['filter_'.$filter]);
    }
    $filter_instances[] = $filter_instance;
}
if (empty($filter_instances)) {
    clidie('No usable filter', 4);
}

$lines = file($logfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($lines === false) {
    clidie('Error reading '.$logfile, 5);
}
foreach ($lines as $ln => $line) {
    foreach ($filter_instances as $filter_instance) {
        $filter_instance->processLine($line);
    }
}

$ipReports = array();
foreach ($filter_instances as $filter_instance) {
    foreach ($filter_instance->getIpReport() as $ip => $reports) {
        foreach ($reports as $report) {
            $ipReports[$ip][] = get_class($filter_instance).': '.$report;
        }
    }
}

foreach ($ipReports as $ip => $msgs) {
    echo $ip.PHP_EOL;
    echo gethostbyaddr($ip).PHP_EOL;
    print_r(geoip_country_name_by_name($ip));
    echo PHP_EOL;
    foreach ($msgs as $msg) {
        echo str_repeat(' ', 8).$msg.PHP_EOL;
    }
}
