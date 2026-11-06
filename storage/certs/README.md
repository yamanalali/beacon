# TLS CA bundle

`cacert.pem` is the Mozilla CA store used by the OpenRouter HTTP client when
PHP's `curl.cainfo` / `openssl.cafile` are empty (common on Windows).

Refresh from https://curl.se/ca/cacert.pem when needed.
