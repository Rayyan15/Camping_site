<?php

namespace App\Enums;

enum PaymentFailureReason: string
{
    /** The gateway request timed out, so the transaction may still exist on the gateway side. */
    case GatewayUnreachable = 'gateway_unreachable';
}
