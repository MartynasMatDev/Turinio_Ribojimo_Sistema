#!/bin/bash
# Turinio ribojimo sistemos paleidimas Docker konteineriuose
cd "$(dirname "$0")"
if docker compose version >/dev/null 2>&1; then DC="docker compose"; else DC="docker-compose"; fi
echo ">> Kuriami atvaizdai ir paleidžiami konteineriai..."
$DC up -d --build || { echo "Klaida: nepavyko paleisti. Ar veikia docker (sudo service docker start)?"; exit 1; }
echo ">> Laukiama, kol pasiruoš duomenų bazė (pirmą kartą iki ~1 min.)..."
for i in $(seq 1 60); do
  if curl -s http://localhost:8080/ | grep -q "Prisijungimas"; then
    echo ">> Sistema veikia:   http://localhost:8080"
    echo ">> phpMyAdmin:       http://localhost:8001  (stud / stud)"
    exit 0
  fi
  sleep 3
done
echo "Sistema dar neatsako. Patikrinkite: $DC ps ir $DC logs"
