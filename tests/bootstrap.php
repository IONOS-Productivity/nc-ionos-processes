<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tests/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

if (!class_exists(\OCA\ShareByMail\Event\BeforeShareMailSentEvent::class)) {
	require_once __DIR__ . '/stubs/BeforeShareMailSentEvent.php';
}

\OC_App::loadApp(OCA\IonosProcesses\AppInfo\Application::APP_ID);
OC_Hook::clear();
