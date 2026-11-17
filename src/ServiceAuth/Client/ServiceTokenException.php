<?php

namespace Shekel\ShekelLib\ServiceAuth\Client;

use RuntimeException;

/**
 * The OP refused or failed a client_credentials request. The message never contains a secret or
 * a token.
 */
class ServiceTokenException extends RuntimeException
{
}
