<?php

return [

    /*
    |--------------------------------------------------------------------------
    | LLM Endpoint
    |--------------------------------------------------------------------------
    |
    | Set AI_SERVICE_URL to an OpenAI-compatible chat completions endpoint.
    | The full request path is <url>/chat/completions.
    |
    | Examples:
    |   AI_SERVICE_URL=https://api.openai.com/v1        (official OpenAI)
    |   AI_SERVICE_URL=http://localhost:8001            (local mock LLM)
    |
    */

    'url' => env('AI_SERVICE_URL', 'https://api.openai.com/v1'),

    'api_key' => env('OPENAI_API_KEY', env('AI_API_KEY', '')),

    'model' => env('AI_MODEL', 'gpt-4o-mini'),

    'temperature' => (float) env('AI_TEMPERATURE', 0.2),

    'max_tokens' => (int) env('AI_MAX_TOKENS', 4000),

    'timeout' => (int) env('AI_TIMEOUT', 90),

    /*
    |--------------------------------------------------------------------------
    | Retries
    |--------------------------------------------------------------------------
    |
    | Maximum number of LLM attempts before a generation fails. Each retry is
    | fed the parser/validator error so the model can repair its own output.
    |
    */

    'max_attempts' => (int) env('AI_MAX_ATTEMPTS', 3),

    /*
    |--------------------------------------------------------------------------
    | Response format
    |--------------------------------------------------------------------------
    |
    | Asks the provider for JSON output (OpenAI "json_object" mode). Some local
    | or third-party servers ignore or reject this field; set to false to omit it.
    |
    */

    'json_mode' => (bool) env('AI_JSON_MODE', true),

    /*
    |--------------------------------------------------------------------------
    | Document import (Part C)
    |--------------------------------------------------------------------------
    |
    | 'import_queue_threshold' - files larger than this (bytes) are parsed by a
    |   queued background job (status shown via polling); smaller files are
    |   parsed inline so the preview appears immediately.
    |
    | 'import_max_size' - maximum accepted upload size in bytes.
    |
    */

    'import_queue_threshold' => (int) env('AI_IMPORT_QUEUE_THRESHOLD', 512 * 1024),

    'import_max_size' => (int) env('AI_IMPORT_MAX_SIZE', 20 * 1024 * 1024),

];
