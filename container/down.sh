aws --profile carmo-dev s3 cp s3://nyle-devbase/carmo-screening/.env .env
export U_ID=$(id -u)
export G_ID=$(id -g)
export DOCKER_GID=$(grep docker /etc/group | cut -d: -f3)
export COMPOSE_PROJECT_NAME=$(basename "$(dirname "$PWD")")
docker compose down -t0
