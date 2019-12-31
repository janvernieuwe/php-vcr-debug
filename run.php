<?php

require_once __DIR__.'/StreamProcessor.php';
require_once __DIR__.'/SampleFilter.php';
$filter = new SampleFilter();
$filter->register();
$processor = new StreamProcessor();
$processor->appendCodeTransformer($filter);
$processor->intercept();

$uri = __DIR__ . '/IncludeFile.php';
$content = file_get_contents($uri);
include $uri;

