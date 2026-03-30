<?php

declare(strict_types=1);

namespace Rossel\RosselKafka\Enum\Config\Broker;

enum BrokerConfigKeys: string
{
    case URL = 'url';
    case TOPICS = 'topics';
    case AUTHENTICATION = 'authentication';
    case SASL_USERNAME = 'sasl_username';
    case SASL_PASSWORD = 'sasl_password';
    case SASL_MECHANISM = 'sasl_mechanism';
    case SSL_CA_CERTIFICATE_URL = 'ssl_ca_certificate_url';
    case SSL_CA_CERTIFICATE_PATH = 'ssl_ca_certificate_path';
    case SSL_CLIENT_CERTIFICATE = 'ssl_client_certificate';
    case SSL_CLIENT_KEY = 'ssl_client_key';
    case SSL_CLIENT_KEY_PASSWORD = 'ssl_client_key_password';
}
