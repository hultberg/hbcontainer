#!/usr/bin/env sh

set -e

podman run \
  --interactive \
  --tty \
  --rm \
  --volume ./:/app \
  --workdir /app \
  docker.io/library/php:7.4-cli \
  vendor/bin/phpunit "$@"

