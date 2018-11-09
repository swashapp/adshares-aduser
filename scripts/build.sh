#!/usr/bin/env bash

set -e

env | sort

if [ ! -v TRAVIS ]; then
  # Checkout repo and change directory

  # Install git
  git --version || apt-get install -y git

  git clone \
    --depth=1 \
    https://github.com/adshares/aduser.git \
    --branch ${ADUSER_INSTALLATION_BRANCH} \
    ${BUILD_PATH}/build

  cd ${BUILD_PATH}/build
fi

envsubst < .env.dist | tee .env

if [ ${ADUSER_APP_ENV} == 'dev' ]; then
    pipenv install --dev pipenv
elif [ ${ADUSER_APP_ENV} == 'deploy' ]; then
    pipenv install --deploy pipenv
else
    pipenv install pipenv
fi