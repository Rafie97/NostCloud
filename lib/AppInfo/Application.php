<?php

declare(strict_types=1);

namespace OCA\Notes\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\GenericEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Share\Events\BeforeShareCreatedEvent;
/** @phan-suppress-next-line PhanUnreferencedUseNormal */
use OCP\Share\IShare;


use swentel\nostr\Event\Event;
use swentel\nostr\Message\EventMessage;
use swentel\nostr\Relay\Relay;
use swentel\nostr\Key\Key;


class Application extends App implements IBootstrap
{
	public const APP_ID = 'notes';
	public static array $API_VERSIONS = ['0.2', '1.3'];

	public function __construct(array $urlParams = [])
	{
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void
	{
		$context->registerCapability(Capabilities::class);
		$context->registerSearchProvider(SearchProvider::class);
		$context->registerDashboardWidget(DashboardWidget::class);
		$context->registerEventListener(
			BeforeTemplateRenderedEvent::class,
			BeforeTemplateRenderedListener::class
		);

		$key = new Key();

		$private_key = $key->generatePrivateKey();
		$public_key = $key->getPublicKey($private_key);

		$note = new Event();
		$note->setContent('Hello world');
		$note->setKind(1);

		$signer = new Sign();
		$signer->signEvent($note, $private_key);

		$eventMessage = new EventMessage($note);

		$relayUrl = 'wss://relay.damus.io';
		$relay = new Relay($relayUrl, $eventMessage);
		$result = $relay->send();


		if (\class_exists(BeforeShareCreatedEvent::class)) {
			$context->registerEventListener(
				BeforeShareCreatedEvent::class,
				BeforeShareCreatedListener::class
			);
		} else {
			// FIXME: Remove once Nextcloud 28 is the minimum supported version
			\OCP\Server::get(IEventDispatcher::class)->addListener('OCP\Share::preShare', function ($event) {
				if (!$event instanceof GenericEvent) {
					return;
				}

				/** @var IShare $share */
				/** @phan-suppress-next-line PhanDeprecatedFunction */
				$share = $event->getSubject();

				$modernListener = \OCP\Server::get(BeforeShareCreatedListener::class);
				$modernListener->overwriteShareTarget($share);
			}, 1000);
		}
	}

	public function boot(IBootContext $context): void
	{
		$context->getAppContainer()->get(NotesHooks::class)->register();
	}
}
