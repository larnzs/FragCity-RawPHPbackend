#!/usr/bin/env bash
# Run from the VMware console of a NEW CLONE, not on the golden source VM.
# Usage: sudo bash set-node-identity.sh fc-node-01 fragcityadmin
set -Eeuo pipefail

fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }
[[ "$EUID" -eq 0 ]] || fail "Run with sudo."
[[ "$#" -eq 2 ]] || fail "Usage: sudo bash set-node-identity.sh <new-hostname> <admin-username>"

new_host="$1"
admin_user="$2"
[[ "$(hostnamectl --static 2>/dev/null || hostname)" == "fc-node-template" ]] \
  || fail "Expected an uncustomized clone hostname fc-node-template. Do not run on the source or an already configured node."
[[ "$new_host" =~ ^[a-zA-Z0-9][a-zA-Z0-9.-]{0,62}$ ]] || fail "Invalid hostname."
id "$admin_user" >/dev/null 2>&1 || fail "Admin user does not exist: $admin_user"
[[ ! -e /var/www/pterodactyl/artisan ]] || fail "Panel files are present; this is not a node template clone."
[[ ! -e /etc/pterodactyl/config.yml ]] || fail "Node-specific Wings config already exists; refusing to continue."

hostnamectl set-hostname "$new_host"
if grep -qE '^127\.0\.1\.1[[:space:]]+' /etc/hosts; then
  sed -i -E "s/^127\.0\.1\.1[[:space:]].*/127.0.1.1 $new_host/" /etc/hosts
else
  printf '127.0.1.1 %s\n' "$new_host" >> /etc/hosts
fi

systemd-machine-id-setup
/usr/bin/ssh-keygen -A
chmod 0644 /etc/machine-id

echo
echo "Set a unique Linux password for $admin_user on this clone."
passwd "$admin_user"

echo
echo "=== CLONE IDENTITY ==="
hostnamectl --static
cat /etc/machine-id
echo
echo "=== DOCKER ==="
systemctl is-active docker
docker info --format 'Server={{.ServerVersion}}'
echo
echo "Identity setup complete. Set a unique DHCP reservation for this clone's new VMware MAC."
echo "Do not enable/start Wings until its Node-specific config is obtained from the Panel."
