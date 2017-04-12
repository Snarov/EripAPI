#!/bin/bash

PPP_NAME="erip_vpn"

while :
do
    #проверям, отвалился ли коннект с ЕРИП и поднимаем, если нужно
    if [ ! -d /sys/class/net/ppp1 ] ; then
	pppd call $PPP_NAME
    fi;
    
    php $2/poll.php
    sleep $1;
done
