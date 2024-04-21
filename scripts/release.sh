#!/usr/bin/env bash

LABEL=$1

pnpm build \
&& rm -rf ai-seo-wp \
&& rm -rf ../wpseoai-nextjs/public/ai-seo-wp.zip \
&& mkdir -p ./ai-seo-wp \
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
--exclude='ai-seo-wp' \
--exclude='ai-seo-wp.zip' \
. ./ai-seo-wp \
&& zip -r ../wpseoai-nextjs/public/ai-seo-wp.zip ./ai-seo-wp
