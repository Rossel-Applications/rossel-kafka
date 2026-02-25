<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Enum\MessageHeaders;

use Symfony\Component\HttpFoundation\Exception\BadRequestException;

enum MessageType
{
    case CANCEL_B2C_SUBSCRIPTION;
    case CHANGE_OFFER;
    case CREATE_OR_UPDATE_B2C_ERP_SUBSCRIPTION;
    case CREATE_OR_UPDATE_B2C_NON_ERP_SUBSCRIPTION;
    case CREATE_OR_UPDATE_B2C_PREFERENCES;
    case CREATE_OR_UPDATE_B2C_PROFILE;
    case CREATE_OR_UPDATE_MOVING_ADDRESS;
    case CREATE_OR_UPDATE_SEPA;
    case CREATE_OR_UPDATE_SUSPENSION;
    case CREATE_OR_UPDATE_TEMPORARY_DELIVERY_ADDRESS;
    case CREATE_OR_UPDATE_WALLET;
    case DELETE_MOVING_ADDRESS;
    case DELETE_SUSPENSION;
    case DELETE_TEMPORARY_DELIVERY_ADDRESS;
    case EXEC_ERROR;
    case EXEC_SUCCESS;
    case LINK_B2C_ERP_SUBSCRIBED_SSO;
    case LOG;
    case REQUEST_SYNC_B2C_ERP_OFFERS;
    case REQUEST_SYNC_B2C_ERP_SUBSCRIPTION;
    case SEND_B2C_EMAIL_NOTIFICATION;
    case SYNC_B2C_ERP_OFFERS;
    case SYNC_B2C_ERP_SUBSCRIBED_SSO;
    case SYNC_B2C_ERP_SUBSCRIPTION;
    case SYNC_B2C_ERP_SUBSCRIPTION_PAYMENT_METHODS;
    case SYNC_B2C_INHERITANCE;
    case SYNC_B2C_PROFILE;
    case UPDATE_INVOICE_ADDRESS;

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
