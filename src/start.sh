#!/bin/bash

sh poll-sheduler.sh 5 &>/dev/null & echo $! > api.pid || echo "Ошибка при запуске"
