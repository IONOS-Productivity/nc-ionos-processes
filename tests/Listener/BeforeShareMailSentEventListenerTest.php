<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 STRATO GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\IonosProcesses\Tests\Listener;

use DateTime;
use OCA\IonosProcesses\Listener\BeforeShareMailSentEventListener;
use OCA\IonosProcesses\Service\IonosMailerService;
use OCA\ShareByMail\Event\BeforeShareMailSentEvent;
use OCP\IL10N;
use OCP\Mail\IMessage;
use OCP\Share\IShare;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BeforeShareMailSentEventListenerTest extends TestCase {
	public const MOCK_USER_ID = '123e4567-e89b-12d3-a456-426614174000';
	public const MOCK_SHARE_TOKEN = 'mock-token';
	public const MOCK_NOTE = 'mock-note';
	public const MOCK_FILENAME = 'mock-file.txt';
	public const MOCK_URL = 'https://cloud.example.com/share/mock-token';
	public const MOCK_RECIPIENT = 'mock-recipient@example.com';

	private LoggerInterface $mockLogger;
	private IonosMailerService $mockMailer;
	private IL10N $mockL10N;
	private IShare $mockShare;
	private IMessage $mockMessage;

	private BeforeShareMailSentEventListener $listener;

	protected function setUp(): void {
		$this->mockLogger = $this->getMockBuilder(LoggerInterface::class)->getMock();

		$this->mockMailer = $this->getMockBuilder(IonosMailerService::class)
			->disableOriginalConstructor()
			->onlyMethods(['send'])
			->getMock();

		$this->mockL10N = $this->getMockBuilder(IL10N::class)
			->disableOriginalConstructor()
			->getMock();

		$this->mockShare = $this->getMockBuilder(IShare::class)->getMock();
		$this->mockMessage = $this->getMockBuilder(IMessage::class)->getMock();

		$this->listener = new BeforeShareMailSentEventListener(
			$this->mockLogger,
			$this->mockL10N,
			$this->mockMailer,
		);
	}

	private function makeEvent(
		array $resolvedEmails = [self::MOCK_RECIPIENT],
		array $templateData = [],
	): BeforeShareMailSentEvent {
		if (empty($templateData)) {
			$templateData = [
				'senderUserId' => self::MOCK_USER_ID,
				'filename' => self::MOCK_FILENAME,
				'link' => self::MOCK_URL,
				'initiator' => 'Test User',
				'shareWith' => 'other@example.com',
				'note' => self::MOCK_NOTE,
				'expiration' => null,
			];
		}
		return new BeforeShareMailSentEvent($this->mockShare, $resolvedEmails, $this->mockMessage, $templateData);
	}

	public function testNonShareTypeEmailIsIgnored(): void {
		$this->mockShare->method('getShareType')->willReturn(IShare::TYPE_USER);
		$this->mockMailer->expects($this->never())->method('send');

		$event = $this->makeEvent();
		$this->listener->handle($event);

		$this->assertFalse($event->isMailHandled());
	}

	public function testEmptyRecipientsMarkHandledAndLogsWarning(): void {
		$this->mockShare->method('getShareType')->willReturn(IShare::TYPE_EMAIL);
		$this->mockShare->method('getToken')->willReturn(self::MOCK_SHARE_TOKEN);

		$this->mockMailer->expects($this->never())->method('send');
		$this->mockLogger
			->expects($this->once())
			->method('warning')
			->with($this->stringContains(self::MOCK_SHARE_TOKEN));

		$event = $this->makeEvent([]);
		$this->listener->handle($event);

		$this->assertTrue($event->isMailHandled());
	}

	public function testSuccessfulSendWithoutExpiration(): void {
		$mockLanguageCode = 'lang_LOCALE';

		$this->mockShare->method('getShareType')->willReturn(IShare::TYPE_EMAIL);
		$this->mockShare->method('getToken')->willReturn(self::MOCK_SHARE_TOKEN);
		$this->mockL10N->method('getLanguageCode')->willReturn($mockLanguageCode);

		$this->mockMailer
			->expects($this->once())
			->method('send')
			->with(
				BeforeShareMailSentEventListener::EVENT_NAME_SHARE_BY_LINK,
				[
					'senderUserId' => self::MOCK_USER_ID,
					'fileName' => self::MOCK_FILENAME,
					'resourceUrl' => self::MOCK_URL,
					'note' => self::MOCK_NOTE,
					'expirationDate' => null,
					'language' => $mockLanguageCode,
					'receiverEmails' => [self::MOCK_RECIPIENT],
				],
			);

		$event = $this->makeEvent();
		$this->listener->handle($event);

		$this->assertTrue($event->isMailHandled());
	}

	public function testSuccessfulSendWithExpiration(): void {
		$mockLanguageCode = 'lang_LOCALE';
		$mockTimestamp = 123456789;
		$expiration = $this->getMockBuilder(DateTime::class)->getMock();
		$expiration->method('getTimestamp')->willReturn($mockTimestamp);

		$this->mockShare->method('getShareType')->willReturn(IShare::TYPE_EMAIL);
		$this->mockShare->method('getToken')->willReturn(self::MOCK_SHARE_TOKEN);
		$this->mockL10N->method('getLanguageCode')->willReturn($mockLanguageCode);

		$templateData = [
			'senderUserId' => self::MOCK_USER_ID,
			'filename' => self::MOCK_FILENAME,
			'link' => self::MOCK_URL,
			'initiator' => 'Test User',
			'shareWith' => 'other@example.com',
			'note' => self::MOCK_NOTE,
			'expiration' => $expiration,
		];

		$this->mockMailer
			->expects($this->once())
			->method('send')
			->with(
				BeforeShareMailSentEventListener::EVENT_NAME_SHARE_BY_LINK,
				[
					'senderUserId' => self::MOCK_USER_ID,
					'fileName' => self::MOCK_FILENAME,
					'resourceUrl' => self::MOCK_URL,
					'note' => self::MOCK_NOTE,
					'expirationDate' => $mockTimestamp,
					'language' => $mockLanguageCode,
					'receiverEmails' => [self::MOCK_RECIPIENT],
				],
			);

		$event = $this->makeEvent([self::MOCK_RECIPIENT], $templateData);
		$this->listener->handle($event);

		$this->assertTrue($event->isMailHandled());
	}
}
