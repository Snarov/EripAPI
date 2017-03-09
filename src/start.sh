#!/bin/bash

API_DIR="/var/webapi/eripapi"
PPP_NAME="erip_vpn"
PPP_GATEWAY="10.54.4.1"
PPP_REMOTE_HOST="10.54.10.2"

if [[ $EUID -ne 0 ]]; then
    echo "Только root может запускать API" >&2
    exit 1
fi


if [ -f "$API_DIR/api.pid" ] && ps -p `cat "$API_DIR/api.pid"` &>/dev/null; then
    echo "API уже запущен"
else
    pppd call $PPP_NAME
    
    sudo -u eripapi sh poll-sheduler.sh 5 `pwd`&>/dev/null & echo $! > "$API_DIR/api.pid" || echo "Ошибка при запуске"
fi
