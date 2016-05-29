<?php
$autoloadPaths = array(
    'vendor/autoload.php',
    '../../autoload.php',
);

$foundVendorAutoload = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require $path;
        $foundVendorAutoload = true;
        break;
    }
}

ini_set('memory_limit', '-1');
date_default_timezone_set(date_default_timezone_get());

$buildNumber    = time();
$resourceFolder = 'resources/';

$buildFolder = 'build/browscap-ua-test-' . $buildNumber . '/build/';
$cacheFolder = 'build/browscap-ua-test-' . $buildNumber . '/cache/';

// create build folder if it does not exist
if (!file_exists($buildFolder)) {
    mkdir($buildFolder, 0777, true);
}
if (!file_exists($cacheFolder)) {
    mkdir($cacheFolder, 0777, true);
}

echo 'init Logger ...', PHP_EOL;

$logger = new \Monolog\Logger('browscap');
$logger->pushHandler(new \Monolog\Handler\NullHandler(\Monolog\Logger::DEBUG));

echo 'build ini files ...', PHP_EOL;

$buildGenerator = new \Browscap\Generator\BuildGenerator(
    $resourceFolder,
    $buildFolder
);

$writerCollectionFactory = new \Browscap\Writer\Factory\PhpWriterFactory();
$writerCollection        = $writerCollectionFactory->createCollection($logger, $buildFolder);

$buildGenerator
    ->setLogger($logger)
    ->setCollectionCreator(new \Browscap\Helper\CollectionCreator())
    ->setWriterCollection($writerCollection)
;

$buildGenerator->run($buildNumber, false);

echo 'creating cache ...', PHP_EOL;

$cache = new \WurflCache\Adapter\File(array(\WurflCache\Adapter\File::DIR => $cacheFolder));
// Now, load an INI file into BrowscapPHP\Browscap for testing the UAs
$browscap = new \BrowscapPHP\Browscap();
$browscap
    ->setCache($cache)
    ->setLogger($logger)
;

$browscap->getCache()->flush();
$browscap->convertFile($buildFolder . '/full_php_browscap.ini');

$propertyHolder = new \Browscap\Data\PropertyHolder();

echo 'reading properties ...', PHP_EOL;

$fileContent = file_get_contents('resources/core/default-browser.json');
$json        = json_decode($fileContent, true);
$properties  = $json['userAgents'][0]['properties'];

unset($properties['RenderingEngine_Description']);

$data            = array();
$checks          = array();
$sourceDirectory = 'tests/fixtures/issues/';

$iterator = new \RecursiveDirectoryIterator($sourceDirectory);

foreach (new \RecursiveIteratorIterator($iterator) as $file) {
    /** @var $file \SplFileInfo */
    if (!$file->isFile() || $file->getExtension() != 'php') {
        continue;
    }

    echo 'expand test file ', $file->getPathname(), ' ...', PHP_EOL;

    $tests = require_once $file->getPathname();

    foreach ($tests as $key => $test) {
        if (isset($data[$key])) {
            continue;
        }

        //echo 'checking test ', $key, ' ...', PHP_EOL;

        $ua = null;

        if (isset($test[0])) {
            $ua = $test[0];
        } elseif (isset($test['ua'])) {
            $ua = $test['ua'];
        }

        if (null === $ua || isset($checks[$ua])) {
            continue;
        }

        $data[$key]  = $test;
        $checks[$ua] = $key;

        $newTest = array(
            'ua'         => $ua,
            'properties' => $properties,
            'lite'       => (array_key_exists('lite', $test) ? $test['lite'] : true),
            'standard'   => (array_key_exists('standard', $test) ? $test['standard'] : true),
        );

        $actualProps = (array) $browscap->getBrowser($ua);

        foreach ($properties as $property => $value) {
            $testProperties = null;

            if (isset($test[1])) {
                $testProperties = $test[1];
            } elseif (isset($test['properties'])) {
                $testProperties = $test['properties'];
            }

            if (null === $testProperties) {
                continue;
            }

            if (array_key_exists($property, $testProperties)) {
                $newTest['properties'][$property] = $testProperties[$property];
            } elseif (array_key_exists(strtolower($property), $actualProps)) {
                $newTest['properties'][$property] = $actualProps[strtolower($property)];
            } else {
                $newTest['properties'][$property] = $value;
            }
        }

        $tests[$key] = $newTest;
    }

    $content = "<?php\n\nreturn " . var_export($tests, true) . ";\n";
    $content = str_replace("=> \n    array (", '=> array(', $content);
    $content = str_replace("=> \n  array (", '=> array(', $content);
    $content = str_replace("\n      '", "\n            '", $content);
    $content = str_replace("\n    '", "\n        '", $content);
    $content = str_replace("\n  '", "\n    '", $content);
    $content = str_replace("\n    )", "\n        )", $content);
    $content = str_replace("\n  )", "\n    )", $content);
    $content = str_replace("array (", 'array(', $content);
    file_put_contents($file->getPathname(), $content);
}