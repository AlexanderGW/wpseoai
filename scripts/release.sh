#!/usr/bin/env bash

LABEL=$1

rm -rf wpseoai \
&& rm -rf ../wpseoai-nextjs/public/wpseoai.zip \
&& mkdir -p ./wpseoai \
&& rsync -av \
--delete-before \
--exclude='.git' \
--exclude='.gitignore' \
--exclude='.idea' \
--exclude='*.cache' \
--exclude='*.config.js' \
--exclude='*.json' \
--exclude='*.xml' \
--exclude='*.yml' \
--exclude='*.yaml' \
--exclude='*.asset.php' \
--exclude='bin' \
--exclude='composer*' \
--exclude='scripts' \
--exclude='src' \
--exclude='tests' \
--exclude='tests/.env' \
--exclude='node_modules' \
--exclude='log' \
--exclude='vendor' \
--exclude='wpseoai' \
--exclude='wpseoai.zip' \
. ./wpseoai \
&& zip -r ../wpseoai-nextjs/public/wpseoai.zip ./wpseoai
