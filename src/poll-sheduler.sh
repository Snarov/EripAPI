#!/bin/bash

while :
do
    php $API_ROOT_DIR/poll.php
    sleep $1;
done
