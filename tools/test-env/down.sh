#!/usr/bin/env bash
# Remove the harness containers and network. Downloads and site files in .site/ are kept.
docker rm -f hxtest-wp hxtest-fakehs hxtest-db >/dev/null 2>&1
docker network rm hxtest >/dev/null 2>&1
echo "down"
