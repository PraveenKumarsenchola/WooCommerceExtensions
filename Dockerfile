# Use an existing Docker image as a base
FROM ubuntu:latest

# Install required packages
RUN apt-get update && apt-get install -y wget unzip

# Download and install Ngrok
RUN wget https://bin.equinox.io/c/4VmDzA7iaHb/ngrok-stable-linux-amd64.zip
RUN unzip ngrok-stable-linux-amd64.zip -d /usr/local/bin

