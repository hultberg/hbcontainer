#!/usr/bin/env sh

set -ex

volume_composer_auth_json=""
if [ -e "$HOME/.config/composer/auth.json" ] ; then
  volume_composer_auth_json="--volume $HOME/.config/composer/auth.json:/root/.composer/auth.json:ro"
fi

if [ ! -d "$HOME/.config/composer/cache" ] ; then
  mkdir -p "$HOME/.config/composer/cache"
fi

podman run \
  --interactive \
  --tty \
  --rm \
  --volume ./:/app \
  $volume_composer_auth_json --volume $HOME/.config/composer/cache:/root/.composer/cache/ \
  --workdir /app \
  docker.io/library/composer:2 \
  composer "$@"
