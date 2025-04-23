#!/usr/bin/env bash

# Stop the script on any error or use of an undefined variable
set -eu

# Kafka bootstrap server address
BOOTSTRAP_SERVER="localhost:9092"

# List of topics to create
TOPICS=("SYNC_ERP" "CDP" "ERP" "SYNC_CDP")

# Loop through each topic and create it
for topic in "${TOPICS[@]}"; do
    echo "Creating topic: $topic"
    ./kafka-topics.sh --bootstrap-server "$BOOTSTRAP_SERVER" --create --topic "$topic"
done
