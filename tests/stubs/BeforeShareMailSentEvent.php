<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 STRATO GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\ShareByMail\Event;

use OCP\EventDispatcher\Event;
use OCP\Mail\IMessage;
use OCP\Share\IShare;

/**
 * CI stub — mirrors the real class for environments where nc-server is not present.
 * Both classes in one file because PHPUnit resolves them via bootstrap, not autoload.
 */
abstract class AbstractBeforeShareMailSentEvent extends Event {
	private bool $mailHandled = false;

	/** @param string[] $resolvedEmails */
	public function __construct(
		private IShare $share,
		private array $resolvedEmails = [],
		private ?IMessage $message = null,
		protected array $templateData = [],
	) {
		parent::__construct();
	}

	public function getShare(): IShare {
		return $this->share;
	}

	/** @return string[] */
	public function getResolvedEmails(): array {
		return $this->resolvedEmails;
	}

	public function getMessage(): ?IMessage {
		return $this->message;
	}

	public function markMailHandled(): void {
		$this->mailHandled = true;
	}

	public function isMailHandled(): bool {
		return $this->mailHandled;
	}
}

class BeforeShareMailSentEvent extends AbstractBeforeShareMailSentEvent {
	public function getSenderUserId(): string {
		return (string)($this->templateData['senderUserId'] ?? '');
	}

	public function getFileName(): string {
		return (string)($this->templateData['filename'] ?? '');
	}

	public function getResourceUrl(): string {
		return (string)($this->templateData['link'] ?? '');
	}

	public function getNote(): string {
		return (string)($this->templateData['note'] ?? '');
	}

	public function getExpiration(): ?\DateTime {
		$v = $this->templateData['expiration'] ?? null;
		return $v instanceof \DateTime ? $v : null;
	}
}
