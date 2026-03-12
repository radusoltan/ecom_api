<?php

// Auto-prepended file to suppress deprecation notices that flood
// PHP built-in server output and cause request timeouts.
set_error_handler(function (int $errno, string $errstr) {
    if (E_DEPRECATED === $errno || E_USER_DEPRECATED === $errno) {
        return true;
    }

    return false;
}, E_DEPRECATED | E_USER_DEPRECATED);
