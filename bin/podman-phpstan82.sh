#!/usr/bin/env sh

set -e

podman run \
  --interactive \
  --tty \
  --rm \
  --volume ./:/app \
  --workdir /app \
  docker.io/library/php:8.2-cli \
  vendor/bin/phpstan analyse "$@"
