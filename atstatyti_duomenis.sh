#!/bin/bash
# Ištrina DB skirsnį (volume) ir paleidžia iš naujo su pradiniais kontroliniais duomenimis
cd "$(dirname "$0")"
if docker compose version >/dev/null 2>&1; then DC="docker compose"; else DC="docker-compose"; fi
$DC down -v && ./paleisti.sh
