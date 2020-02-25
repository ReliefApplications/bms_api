#!/bin/bash -ex
exit 0
FILE_NAME=$(basename "$0")
STAGE="${FILE_NAME%.*}"
WEBSITE_URL="http://www.${STAGE}.worldvision.wehost.asia"
SELENIUM_URL="http://10.10.10.120:4444/wd/hub"

echo "Execute sitespeed.io"
ansible localhost -m shell -a "sitespeed.io --browsertime.selenium.url ${SELENIUM_URL} --outputFolder build/logs ${WEBSITE_URL}"