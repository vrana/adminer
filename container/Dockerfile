FROM ubuntu:noble

ENV DEBIAN_FRONTEND=noninteractive

# システムの更新とDocker Composeのインストールに必要なパッケージのインストール
RUN --mount=type=cache,target=/var/lib/apt,sharing=locked \
    --mount=type=cache,target=/var/cache/apt,sharing=locked \
    apt-get update && \
    apt-get install -y --no-install-recommends \
    locales git wget unzip vim sudo nano \
    curl apt-transport-https ca-certificates gnupg lsb-release \
    && \
    # Dockerリポジトリの設定
    install -m 0755 -d /etc/apt/keyrings && \
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg && \
    chmod a+r /etc/apt/keyrings/docker.gpg && \
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" > /etc/apt/sources.list.d/docker.list && \
    # Node.js v20 LTS リポジトリの設定
    curl -fsSL https://deb.nodesource.com/setup_lts.x | bash - && \
    apt-get update && \
    apt-get install -y docker-ce docker-ce-cli containerd.io  \
    docker-buildx-plugin docker-compose-plugin nodejs  && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

RUN  locale-gen en_US.UTF-8 && \
    update-locale LANG=en_US.UTF-8 LC_ALL=en_US.UTF-8

COPY --from=ghcr.io/astral-sh/uv:latest /uv /uvx /bin/

# aws cli
RUN curl "https://awscli.amazonaws.com/awscli-exe-linux-$(uname -m).zip" -o "awscliv2.zip" && \
    unzip -q awscliv2.zip && \
    ./aws/install && \
    rm -rf aws awscliv2.zip

# github cli
RUN curl -sS https://webi.sh/gh | sh

RUN npm install -g  \
    @anthropic-ai/claude-code \
    aws-cdk aws-cdk-lib typescript && \
    npm cache clean --force

ARG USERNAME="ubuntu"

RUN usermod -aG users $USERNAME && \
    echo '%users ALL=(ALL:ALL) NOPASSWD: ALL' > /etc/sudoers.d/users && \
    echo "$USERNAME ALL=(ALL:ALL) NOPASSWD: ALL" > /etc/sudoers.d/$USERNAME && \
    chmod 0440 /etc/sudoers.d/$USERNAME

USER ${USERNAME}
WORKDIR /home/${USERNAME}/work
VOLUME  /home/${USERNAME}/work

COPY entrypoint.sh /usr/local/bin/entrypoint.sh

RUN git config --global credential.helper store

ENTRYPOINT [ "entrypoint.sh" ]

