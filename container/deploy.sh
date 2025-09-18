export GIT_USER=takemi-ohama
export GIT_REPO=adminer
export U_ID=$(id -u)
export G_ID=$(id -g)
export DOCKER_GID=$(grep docker /etc/group | cut -d: -f3)
export COMPOSE_PROJECT_NAME=$(basename "$(dirname "$PWD")")
docker compose stop -t0
docker compose up -d --scale dev=3
