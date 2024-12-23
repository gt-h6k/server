<?php

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC;

use OCP\IConfig;
use OCP\IDateTimeZone;
use OCP\ISession;
use Psr\Log\LoggerInterface;

class DateTimeZone implements IDateTimeZone {
	/** @var IConfig */
	protected $config;

	/** @var ISession */
	protected $session;

	/**
	 * Constructor
	 *
	 * @param IConfig $config
	 * @param ISession $session
	 */
	public function __construct(IConfig $config, ISession $session) {
		$this->config = $config;
		$this->session = $session;
	}

	/**
	 * Get the timezone of the current user, based on their session information and config data
	 *
	 * @param bool|int $timestamp
	 * @return \DateTimeZone
	 */
	public function getTimeZone($timestamp = false) {
		$timeZone = $this->config->getUserValue($this->session->get('user_id'), 'core', 'timezone', null);
		if ($timeZone === null) {
			if ($this->session->exists('timezone')) {
				return $this->guessTimeZoneFromOffset($this->session->get('timezone'), $timestamp);
			}
			$timeZone = $this->getDefaultTimeZone();
		}

		try {
			return new \DateTimeZone($timeZone);
		} catch (\Exception $e) {
			\OC::$server->get(LoggerInterface::class)->debug('Failed to created DateTimeZone "' . $timeZone . '"', ['app' => 'datetimezone']);
			return new \DateTimeZone($this->getDefaultTimeZone());
		}
	}

	/**
	 * Guess the DateTimeZone for a given offset
	 *
	 * We first try to find a Etc/GMT* timezone, if that does not exist,
	 * we try to find it manually, before falling back to UTC.
	 *
	 * @param mixed $offset
	 * @param bool|int $timestamp
	 * @return \DateTimeZone
	 */
	protected function guessTimeZoneFromOffset($offset, $timestamp) {
		try {
			// Note: the timeZone name is the inverse to the offset,
			// so a positive offset means negative timeZone
			// and the other way around.
			if ($offset > 0) {
				$timeZone = 'Etc/GMT-' . $offset;
			} else {
				$timeZone = 'Etc/GMT+' . abs($offset);
			}

			return new \DateTimeZone($timeZone);
		} catch (\Exception $e) {
			// If the offset has no Etc/GMT* timezone,
			// we try to guess one timezone that has the same offset
			foreach (\DateTimeZone::listIdentifiers() as $timeZone) {
				$dtz = new \DateTimeZone($timeZone);
				$dateTime = new \DateTime();

				if ($timestamp !== false) {
					$dateTime->setTimestamp($timestamp);
				}

				$dtOffset = $dtz->getOffset($dateTime);
				if ($dtOffset == 3600 * $offset) {
					return $dtz;
				}
			}

			// No timezone found, fallback to UTC
			\OC::$server->get(LoggerInterface::class)->debug('Failed to find DateTimeZone for offset "' . $offset . '"', ['app' => 'datetimezone']);
			return new \DateTimeZone($this->getDefaultTimeZone());
		}
	}

	/**
	 * Get the default timezone of the server
	 *
	 * Falls back to UTC if it is not yet set.
	 *
	 * @return string
	 */
	protected function getDefaultTimeZone() {
		$serverTimeZone = date_default_timezone_get();
		return $serverTimeZone ?: 'UTC';
	}
}
