#!/bin/bash
php -d memory_limit=256M artisan sync:coin --provider=binance "$@"