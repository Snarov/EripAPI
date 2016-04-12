#!/bin/bash

kill $(cat api.pid &>/dev/null) &>/dev/null && rm api.pid &>/dev/null || echo "Остановка невозможна, так как в данный момент API не запущено"

