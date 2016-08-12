#!/bin/bash

if [ ! -f api.pid ]; then
    sh poll-sheduler.sh 5 `pwd` &>/dev/null & echo $! > api.pid || echo "Ошибка при запуске"
else
    echo "API уже запущен"
fi

