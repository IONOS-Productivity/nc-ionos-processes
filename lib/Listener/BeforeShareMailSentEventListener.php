<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 STRATO GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\IonosProcesses\Listener;

use OCA\IonosProcesses\Service\IonosMailerService;
use OCA\ShareByMail\Event\BeforeShareMailSentEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IL10N;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

/**
 * Intercepts BeforeShareMailSentEvent fired by ShareByMailProvider::sendEmail()
 * and routes share notifications through the internal IONOS mail delivery API.
 *
 * Calls markMailHandled() unconditionally in every TYPE_EMAIL code path so the
 * native Nextcloud SMTP send is suppressed. IONOS send failures propagate as
 * exceptions — no silent fallback to SMTP.
 *
 * Non-TYPE_EMAIL share types are not marked handled, so their native send still runs.
 *
 * @psalm-api
 * @implements IEventListener<BeforeShareMailSentEvent>
 */
class BeforeShareMailSentEventListener implements IEventListener {
	public const EVENT_NAME_SHARE_BY_LINK = 'share-by-link';

	/** @psalm-api */
	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly IL10N $l10n,
		private readonly IonosMailerService $mailer,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof BeforeShareMailSentEvent)) {
			return;
		}

		$share = $event->getShare();

		if ($share->getShareType() !== IShare::TYPE_EMAIL) {
			return;
		}

		$resolvedEmails = $event->getResolvedEmails();
		if (empty($resolvedEmails)) {
			$event->markMailHandled();
			$this->logger->warning("empty recipients for share with token '" . $share->getToken() . "', skipping");
			return;
		}

		$mailData = $event->getMailData();
		$senderUserId = isset($mailData['senderUserId']) && is_string($mailData['senderUserId'])
			? $mailData['senderUserId']
			: null;
		if ($senderUserId === null) {
			$event->markMailHandled();
			$this->logger->error("missing senderUserId in mail data for share with token '" . $share->getToken() . "'");
			return;
		}

		/** @var \DateTime|null $expiration */
		$expiration = $mailData['expiration'] ?? null;
		$data = [
			'senderUserId' => $senderUserId,
			'fileName' => isset($mailData['fileName']) && is_string($mailData['fileName']) ? $mailData['fileName'] : '',
			'resourceUrl' => isset($mailData['resourceUrl']) && is_string($mailData['resourceUrl']) ? $mailData['resourceUrl'] : '',
			'note' => isset($mailData['note']) && is_string($mailData['note']) ? $mailData['note'] : '',
			'expirationDate' => $expiration instanceof \DateTime ? $expiration->getTimestamp() : null,
			'language' => $this->l10n->getLanguageCode(),
			'receiverEmails' => $resolvedEmails,
		];

		$event->markMailHandled();
		$this->mailer->send(self::EVENT_NAME_SHARE_BY_LINK, $data);
	}
}
