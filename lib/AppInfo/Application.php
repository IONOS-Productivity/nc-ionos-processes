<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 STRATO GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\IonosProcesses\AppInfo;

use OCA\IonosProcesses\Listener\BeforeShareMailSentEventListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
	public const APP_ID = 'nc_ionos_processes';

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		include_once __DIR__ . '/../../vendor/autoload.php';

		if (class_exists(\OCA\ShareByMail\Event\BeforeShareMailSentEvent::class)) {
			$context->registerEventListener(
				\OCA\ShareByMail\Event\BeforeShareMailSentEvent::class,
				BeforeShareMailSentEventListener::class,
			);
		}
	}

	public function boot(IBootContext $context): void {
	}
}
