#!/bin/bash
user=$1
repo=$2
[ -d "$repo" ] && (cd "$repo" && git pull) || git clone "https://github.com/$user/$repo.git"
shift 2
[ -f "$repo/init.sh" ] && (cd "$repo" && ./init.sh)
$@
