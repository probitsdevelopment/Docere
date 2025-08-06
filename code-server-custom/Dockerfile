FROM codercom/code-server:latest

# Install OpenJDK 17, Maven, and dependencies (including zip)
RUN sudo apt update && \
    sudo apt install -y openjdk-17-jdk maven curl unzip zip git

# Set JAVA_HOME
ENV JAVA_HOME=/usr/lib/jvm/java-17-openjdk-amd64
ENV PATH="$JAVA_HOME/bin:$PATH"

# Optional: Install Spring Boot CLI using SDKMAN
RUN curl -s https://get.sdkman.io | bash && \
    bash -c "source $HOME/.sdkman/bin/sdkman-init.sh && sdk install springboot"


# Install Git
RUN sudo apt update && sudo apt install -y git

# Git config for the bot
RUN git config --global user.name "code-server-bot"
RUN git config --global user.email "bindu.1rr21ec014@gmail.com"

# GitHub credentials (use secrets in prod!)
COPY .git-credentials /home/coder/.git-credentials
RUN git config --global credential.helper store


ARG NODE_VERSION=v20.15.0
USER root

RUN apt-get update && apt-get install -y curl && \
    curl -fsSL https://nodejs.org/dist/$NODE_VERSION/node-$NODE_VERSION-linux-x64.tar.gz -o node.tar.gz && \
    tar -xzvf node.tar.gz -C /usr/local --strip-components=1 && \
    rm node.tar.gz



# ---- VSCode Settings (optional, as before) ----
RUN mkdir -p /home/coder/.local/share/code-server/User
COPY settings.json /home/coder/.local/share/code-server/User/settings.json

RUN chown -R coder:coder /home/coder/.local || true

USER coder
