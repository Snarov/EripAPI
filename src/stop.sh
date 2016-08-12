#!/bin/bash

kill  $(cat api.pid 2>/dev/null) &>/dev/null  && rm api.pid &>/dev/null || echo "Остановка невозможна, так как в данный момент API не запущен"

