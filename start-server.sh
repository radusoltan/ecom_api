#!/bin/bash
# Start PHP built-in server with output actively drained to prevent blocking.
# The built-in server writes debug/deprecation notices to its output synchronously,
# which blocks request processing when the pipe buffer fills up.
export APP_ENV=prod
export APP_DEBUG=0
cd /var/www/ecom_api
exec php -d apc.enable_cli=1 -S 127.0.0.1:8000 -t public 2>&1 | cat > /dev/null
