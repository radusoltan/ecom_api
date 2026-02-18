<?php

if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache has been cleared successfully!\n";
} else {
    echo "OPcache is not enabled.\n";
}
