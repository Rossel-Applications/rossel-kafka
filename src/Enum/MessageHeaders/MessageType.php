<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Enum\MessageHeaders;

use Rossel\RosselKafka\Enum\Infrastructure\KafkaTopic;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

enum MessageType
{
    case LOG;
    case SYNC_B2C_ERP_SUBSCRIPTION;
    case SYNC_B2C_ERP_SUBSCRIPTION_PAYMENT_METHODS;
    case CREATE_OR_UPDATE_B2C_PROFILE;
    case CANCEL_B2C_SUBSCRIPTION;
    case CHANGE_OFFER;
    case CREATE_OR_UPDATE_B2C_ERP_SUBSCRIPTION;
    case CREATE_OR_UPDATE_MOVING_ADDRESS;
    case CREATE_OR_UPDATE_SEPA;
    case CREATE_OR_UPDATE_SUSPENSION;
    case CREATE_OR_UPDATE_TEMPORARY_DELIVERY_ADDRESS;
    case CREATE_OR_UPDATE_WALLET;
    case DELETE_MOVING_ADDRESS;
    case DELETE_SUSPENSION;
    case DELETE_TEMPORARY_DELIVERY_ADDRESS;
    case LINK_B2C_ERP_SUBSCRIBED_SSO;
    case REQUEST_SYNC_B2C_ERP_SUBSCRIPTION;
    case REQUEST_SYNC_B2C_ERP_OFFERS;
    case UPDATE_INVOICE_ADDRESS;
    case SYNC_B2C_PROFILE;

    /**
     * @return array<int, KafkaTopic>
     */
    public function getTopics(): array
    {
        return match ($this) {
            self::SYNC_B2C_ERP_SUBSCRIPTION,
            self::SYNC_B2C_ERP_SUBSCRIPTION_PAYMENT_METHODS => [
                KafkaTopic::SYNC_ERP,
            ],

            self::CREATE_OR_UPDATE_B2C_PROFILE => [
                KafkaTopic::CDP,
            ],

            self::CANCEL_B2C_SUBSCRIPTION,
            self::CHANGE_OFFER,
            self::CREATE_OR_UPDATE_B2C_ERP_SUBSCRIPTION,
            self::CREATE_OR_UPDATE_MOVING_ADDRESS,
            self::CREATE_OR_UPDATE_SEPA,
            self::CREATE_OR_UPDATE_SUSPENSION,
            self::CREATE_OR_UPDATE_TEMPORARY_DELIVERY_ADDRESS,
            self::CREATE_OR_UPDATE_WALLET,
            self::DELETE_MOVING_ADDRESS,
            self::DELETE_SUSPENSION,
            self::DELETE_TEMPORARY_DELIVERY_ADDRESS,
            self::LINK_B2C_ERP_SUBSCRIBED_SSO,
            self::REQUEST_SYNC_B2C_ERP_SUBSCRIPTION,
            self::REQUEST_SYNC_B2C_ERP_OFFERS,
            self::UPDATE_INVOICE_ADDRESS => [
                KafkaTopic::ERP,
            ],

            self::SYNC_B2C_PROFILE => [
                KafkaTopic::SYNC_CDP,
            ],
        };
    }

    public static function from(string $name): self
    {
        foreach (self::cases() as $case) {
            if ($case->name === $name) {
                return $case;
            }
        }

        throw new BadRequestException(\sprintf('%s case %s not found.', self::class, $name));
    }
}
