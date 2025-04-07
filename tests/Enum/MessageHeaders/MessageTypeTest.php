<?php

namespace Enum\MessageHeaders;

use PHPUnit\Framework\TestCase;
use Rossel\RosselKafka\Enum\Infrastructure\KafkaTopic;
use Rossel\RosselKafka\Enum\MessageHeaders\MessageType;

final class MessageTypeTest extends TestCase
{
    /**
     * Test that SYNC_B2C_ERP_SUBSCRIPTION and SYNC_B2C_ERP_SUBSCRIPTION_PAYMENT_METHODS
     * return the expected KafkaTopic SYNC_ERP.
     */
    public function testGetTopicsReturnsSyncErpTopic(): void
    {
        $types = [
            MessageType::SYNC_B2C_ERP_SUBSCRIPTION,
            MessageType::SYNC_B2C_ERP_SUBSCRIPTION_PAYMENT_METHODS,
        ];

        foreach ($types as $type) {
            self::assertSame(
                [KafkaTopic::SYNC_ERP],
                $type->getTopics(),
                sprintf('Failed asserting that %s returns [KafkaTopic::SYNC_ERP]', $type->name)
            );
        }
    }

    /**
     * Test that CREATE_OR_UPDATE_B2C_PROFILE returns the expected KafkaTopic CDP.
     */
    public function testGetTopicsReturnsCdpTopic(): void
    {
        $type = MessageType::CREATE_OR_UPDATE_B2C_PROFILE;

        self::assertSame(
            [KafkaTopic::CDP],
            $type->getTopics(),
            'Failed asserting that CREATE_OR_UPDATE_B2C_PROFILE returns [KafkaTopic::CDP]'
        );
    }

    /**
     * Test that ERP related message types return the expected KafkaTopic ERP.
     */
    public function testGetTopicsReturnsErpTopic(): void
    {
        $types = [
            MessageType::CANCEL_B2C_SUBSCRIPTION,
            MessageType::CHANGE_OFFER,
            MessageType::CREATE_OR_UPDATE_B2C_ERP_SUBSCRIPTION,
            MessageType::CREATE_OR_UPDATE_MOVING_ADDRESS,
            MessageType::CREATE_OR_UPDATE_SEPA,
            MessageType::CREATE_OR_UPDATE_SUSPENSION,
            MessageType::CREATE_OR_UPDATE_TEMPORARY_DELIVERY_ADDRESS,
            MessageType::CREATE_OR_UPDATE_WALLET,
            MessageType::DELETE_MOVING_ADDRESS,
            MessageType::DELETE_SUSPENSION,
            MessageType::DELETE_TEMPORARY_DELIVERY_ADDRESS,
            MessageType::LINK_B2C_ERP_SUBSCRIBED_SSO,
            MessageType::REQUEST_SYNC_B2C_ERP_SUBSCRIPTION,
            MessageType::REQUEST_SYNC_B2C_ERP_OFFERS,
            MessageType::UPDATE_INVOICE_ADDRESS,
        ];

        foreach ($types as $type) {
            self::assertSame(
                [KafkaTopic::ERP],
                $type->getTopics(),
                sprintf('Failed asserting that %s returns [KafkaTopic::ERP]', $type->name)
            );
        }
    }

    /**
     * Test that SYNC_B2C_PROFILE returns the expected KafkaTopic SYNC_CDP.
     */
    public function testGetTopicsReturnsSyncCdpTopic(): void
    {
        $type = MessageType::SYNC_B2C_PROFILE;

        self::assertSame(
            [KafkaTopic::SYNC_CDP],
            $type->getTopics(),
            'Failed asserting that SYNC_B2C_PROFILE returns [KafkaTopic::SYNC_CDP]'
        );
    }
}
