#!/bin/sh

/startup/builder/script.php "$@" && /usr/bin/supervisord -c "/etc/supervisor/supervisord.conf"
