<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Enum\Config\Broker;

enum TopicConfigKeys: string
{
    case ACCOUNT_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE = 'account_api_public_log_output_v1_json_delete';
    case AUTHENTICATION_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE = 'authentication_api_public_log_output_v1_json_delete';
    case PUBLIC_DEAD_LETTER_INOUT_V1_JSON_DELETE_D30 = 'public_dead_letter_inout_v1_json_delete_d30';
    case ERP_SUBSCRIPTION_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE = 'erp_subscription_api_public_log_output_v1_json_delete';
    case PUBLIC_INHERITANCE_OUTPUT_V1_JSON_DELETE = 'public_inheritance_output_v1_json_delete';
    case INHERITANCE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE = 'inheritance_api_public_log_output_v1_json_delete';
    case NOTIFICATION_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE = 'notification_api_public_log_output_v1_json_delete';
    case PUBLIC_NOTIFICATION_INPUT_V1_JSON_DELETE = 'public_notification_input_v1_json_delete';
    case OFFER_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE = 'offer_api_public_log_output_v1_json_delete';
    case PUBLIC_OFFER_OUTPUT_V1_JSON_DELETE = 'public_offer_output_v1_json_delete';
    case PROFILE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE = 'profile_api_public_log_output_v1_json_delete';
    case PURCHASE_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE = 'purchase_api_public_log_output_v1_json_delete';
    case PUBLIC_LOG_OUTPUT_V1_JSON_DELETE = 'public_log_output_v1_json_delete';
    case PUBLIC_OFFER_INPUT_V1_JSON_DELETE = 'public_offer_input_v1_json_delete';
    case PUBLIC_SUBSCRIPTION_INPUT_V1_JSON_DELETE = 'public_subscription_input_v1_json_delete';
    case PUBLIC_SUBSCRIPTION_OUTPUT_V1_JSON_DELETE = 'public_subscription_output_v1_json_delete';
    case PUBLIC_CONTACT_INPUT_V1_JSON_DELETE = 'public_contact_input_v1_json_delete';
    case INPUT_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE = 'input_api_public_log_output_v1_json_delete';
    case OUTPUT_API_PUBLIC_LOG_OUTPUT_V1_JSON_DELETE = 'output_api_public_log_output_v1_json_delete';
    case PUBLIC_PROFILE_OUTPUT_V1_JSON_DELETE = 'public_profile_output_v1_json_delete';
}
