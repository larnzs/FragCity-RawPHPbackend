#!/usr/bin/env bash
# FragCity Node-template helper: install Docker Engine on a fresh Debian 13 amd64 VM.
# Run as the normal admin user, NOT with sudo in front: bash install-docker.sh
set -Eeuo pipefail

fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

if [[ "$EUID" -eq 0 ]]; then
  fail "Run this script as the normal admin user; it uses sudo for privileged operations."
fi

if [[ ! -r /etc/os-release ]]; then
  fail "/etc/os-release is missing."
fi
. /etc/os-release

[[ "$ID" == "debian" ]] || fail "This script is for Debian only; detected ID=$ID."
[[ "$VERSION_ID" == "13" && "$VERSION_CODENAME" == "trixie" ]] \
  || fail "Expected Debian 13/Trixie; detected $PRETTY_NAME."
[[ "$(dpkg --print-architecture)" == "amd64" ]] \
  || fail "Expected amd64; detected $(dpkg --print-architecture)."

printf 'Target OS: %s\n' "$PRETTY_NAME"
printf 'Architecture: %s\n' "$(dpkg --print-architecture)"
sudo -v

conflicts=()
for package in docker.io docker-compose docker-doc docker-buildx podman-docker containerd runc; do
  status="$(dpkg-query -W -f='${db:Status-Status}' "$package" 2>/dev/null || true)"
  if [[ "$status" == "installed" ]]; then
    conflicts+=("$package")
  fi
done
if (( ${#conflicts[@]} > 0 )); then
  printf 'Conflicting/legacy package(s) detected: %s\n' "${conflicts[*]}" >&2
  fail "Review these packages manually. This script will not uninstall or overwrite them."
fi

sudo apt-get update
sudo apt-get install -y ca-certificates curl

sudo install -m 0755 -d /etc/apt/keyrings
key_tmp="$(mktemp)"
source_tmp="$(mktemp)"
trap 'rm -f "$key_tmp" "$source_tmp"' EXIT

echo "Downloading the official Docker repository key..."
curl -fsSL --connect-timeout 15 --max-time 60 \
  https://download.docker.com/linux/debian/gpg -o "$key_tmp"

if sudo test -s /etc/apt/keyrings/docker.asc; then
  if ! sudo cmp -s "$key_tmp" /etc/apt/keyrings/docker.asc; then
    fail "Existing docker.asc differs from the current official key response; inspect it before replacing."
  fi
  echo "Existing Docker key matches the downloaded official key."
else
  sudo install -o root -g root -m 0644 "$key_tmp" /etc/apt/keyrings/docker.asc
fi

cat > "$source_tmp" <<EOF
Types: deb
URIs: https://download.docker.com/linux/debian
Suites: $VERSION_CODENAME
Components: stable
Architectures: $(dpkg --print-architecture)
Signed-By: /etc/apt/keyrings/docker.asc
EOF

if sudo test -e /etc/apt/sources.list.d/docker.sources; then
  if ! sudo cmp -s "$source_tmp" /etc/apt/sources.list.d/docker.sources; then
    echo "Existing Docker repository configuration:" >&2
    sudo cat /etc/apt/sources.list.d/docker.sources >&2
    fail "docker.sources already exists but differs; no overwrite was performed."
  fi
  echo "Existing Docker repository file matches the expected configuration."
else
  sudo install -o root -g root -m 0644 "$source_tmp" /etc/apt/sources.list.d/docker.sources
fi

sudo apt-get update
sudo apt-get install -y \
  docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo systemctl enable --now docker

echo
echo "=== DOCKER PACKAGE VERSIONS ==="
dpkg-query -W -f='${binary:Package} ${Version}\n' \
  docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

echo
echo "=== DOCKER ENGINE ==="
sudo docker --version
sudo systemctl is-active docker
sudo docker info --format 'Server={{.ServerVersion}} Containers={{.Containers}} Images={{.Images}}'

echo
echo "=== HELLO-WORLD TEST ==="
sudo docker run --rm hello-world

echo
echo "=== DISK ==="
df -h /
echo "SUCCESS: Docker Engine installed and hello-world test passed."
