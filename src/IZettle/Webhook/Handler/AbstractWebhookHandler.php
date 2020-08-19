<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Swag\PayPal\IZettle\Webhook\Handler;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Swag\PayPal\IZettle\Api\Service\ApiKeyDecoder;
use Swag\PayPal\IZettle\Api\Webhook\Payload\AbstractPayload;
use Swag\PayPal\IZettle\Api\Webhook\Webhook;
use Swag\PayPal\IZettle\Util\IZettleSalesChannelTrait;
use Swag\PayPal\IZettle\Webhook\WebhookHandler;

abstract class AbstractWebhookHandler implements WebhookHandler
{
    use IZettleSalesChannelTrait;

    /**
     * @var ApiKeyDecoder
     */
    private $apiKeyDecoder;

    public function __construct(ApiKeyDecoder $apiKeyDecoder)
    {
        $this->apiKeyDecoder = $apiKeyDecoder;
    }

    /**
     * {@inheritdoc}
     */
    abstract public function getEventName(): string;

    /**
     * @return class-string<AbstractPayload>
     */
    abstract public function getPayloadClass(): string;

    abstract public function execute(AbstractPayload $payload, SalesChannelEntity $salesChannel, Context $context): void;

    /**
     * {@inheritdoc}
     */
    public function invoke(Webhook $webhook, SalesChannelEntity $salesChannel, Context $context): void
    {
        $payloadArray = \json_decode($webhook->getPayload(), true);

        $payloadClass = $this->getPayloadClass();

        $payload = new $payloadClass();
        $payload->assign($payloadArray);

        if ($this->isOwnClientId($payload->getUpdated()->getClientUuid(), $salesChannel)) {
            return;
        }

        $this->execute($payload, $salesChannel, $context);
    }

    private function isOwnClientId(string $reportedClientId, SalesChannelEntity $salesChannel): bool
    {
        $apiKey = $this->getIZettleSalesChannel($salesChannel)->getApiKey();

        $ownClientId = $this->apiKeyDecoder->decode($apiKey)->getPayload()->getClientId();

        return $reportedClientId === $ownClientId;
    }
}
