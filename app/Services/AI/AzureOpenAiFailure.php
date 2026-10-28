<?php

namespace App\Services\AI;

enum AzureOpenAiFailure: string
{
    case CONFIGURATION = 'configuration';
    case INVALID_CONTEXT = 'invalid_context';
    case AUTHENTICATION = 'authentication';
    case RATE_LIMIT = 'rate_limit';
    case REQUEST_REJECTED = 'request_rejected';
    case UPSTREAM = 'upstream';
    case TIMEOUT = 'timeout';
    case NETWORK = 'network';
    case MALFORMED_RESPONSE = 'malformed_response';
    case EMPTY_OUTPUT = 'empty_output';
    case REFUSAL = 'refusal';
}
