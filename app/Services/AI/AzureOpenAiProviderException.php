<?php

namespace App\Services\AI;

use RuntimeException;

final class AzureOpenAiProviderException extends RuntimeException
{
    public function __construct(
        public readonly AzureOpenAiFailure $failure,
        public readonly ?int $httpStatus = null,
    ) {
        parent::__construct(match ($failure) {
            AzureOpenAiFailure::CONFIGURATION => 'Azure OpenAI is not configured correctly.',
            AzureOpenAiFailure::INVALID_CONTEXT => 'The assistant context could not be prepared safely.',
            AzureOpenAiFailure::AUTHENTICATION => 'Azure OpenAI rejected the configured credentials.',
            AzureOpenAiFailure::RATE_LIMIT => 'Azure OpenAI is temporarily rate limited.',
            AzureOpenAiFailure::REQUEST_REJECTED => 'Azure OpenAI rejected the request.',
            AzureOpenAiFailure::UPSTREAM => 'Azure OpenAI is temporarily unavailable.',
            AzureOpenAiFailure::TIMEOUT => 'The Azure OpenAI request timed out.',
            AzureOpenAiFailure::NETWORK => 'Azure OpenAI could not be reached.',
            AzureOpenAiFailure::MALFORMED_RESPONSE => 'Azure OpenAI returned an invalid response.',
            AzureOpenAiFailure::EMPTY_OUTPUT => 'Azure OpenAI returned no usable guidance.',
            AzureOpenAiFailure::REFUSAL => 'Azure OpenAI could not provide guidance for this context.',
        });
    }
}
