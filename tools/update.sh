#!/usr/bin/env bash

set -euo pipefail

curl -L https://phar.phpunit.de/phpunit-11.5.56.phar -o tools/phpunit.phar
curl -L https://github.com/infection/infection/releases/download/0.32.6/infection.phar -o tools/infection.phar
