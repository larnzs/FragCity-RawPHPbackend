#!/usr/bin/env bash
# Finalize a clean FragCity node template before powering it off and copying it.
# DANGEROUS BY DESIGN: removes this template's SSH host keys and empties machine-id.
# Run ONLY inside the fresh VM whose hostname is fc-node-template.
set -Eeuo pipefail

fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

[[ "$EUID" -eq 0 ]] || fail "Run with: sudo bash prepare-golden-image.sh"
host="$(hostnamectl --static 2>/dev/null || hostname)"
[[ "$host" == "fc-node-template" ]] || fail "Expected hostname fc-node-template; detected $host."

[[ -d /etc/ssh ]] || fail "/etc/ssh is missing."
[[ -x /usr/bin/ssh-keygen ]] || fail "openssh-server/ssh-keygen is not installed."
[[ -x /usr/local/bin/wings ]] || fail "Wings binary is missing. Do not finalize before installing the compatible Wings version."
systemctl cat wings.service >/dev/null 2>&1 || fail "wings.service unit is missing."
sudo docker info >/dev/null 2>&1 || fail "Docker Engine is not healthy."

if [[ -e /var/www/pterodactyl/artisan ]]; then
  fail "Panel files are present on this machine. This is not a clean Node template."
fi

if [[ -e /etc/pterodactyl/config.yml ]]; then
  fail "/etc/pterodactyl/config.yml exists and may contain node-specific tokens. Stop and inspect securely; do not bake it into a template."
fi

if [[ -n "$(docker ps -aq 2>/dev/null || true)" ]]; then
  fail "Docker containers exist. Remove/resolve them deliberately before finalizing; this script will not delete them."
fi

if [[ -d /var/lib/pterodactyl/volumes ]] && [[ -n "$(find /var/lib/pterodactyl/volumes -mindepth 1 -print -quit 2>/dev/null)" ]]; then
  fail "Pterodactyl server volumes exist. This template must not include server/customer data."
fi

if [[ -d /home/steam ]] && [[ -n "$(find /home/steam -mindepth 1 -print -quit 2>/dev/null)" ]]; then
  fail "/home/steam contains files. The template must not contain CS2 or cs2-kus-addons payloads."
fi

if ! id fragcityadmin >/dev/null 2>&1; then
  fail "Expected local admin user fragcityadmin was not found; adjust the runbook before proceeding."
fi

echo "Preflight passed. Wings will remain disabled until a clone gets its own Node config."

# Stop/disable Wings so no clone can accidentally start with stale config.
systemctl disable --now wings.service >/dev/null 2>&1 || true

# First boot of a clone must create unique SSH host keys before ssh.service starts.
cat > /etc/systemd/system/fc-node-template-ssh-hostkeys.service <<'UNIT'
[Unit]
Description=Generate unique SSH host keys on first boot of a FragCity node clone
DefaultDependencies=no
After=local-fs.target
Before=ssh.service
ConditionPathExists=!/etc/ssh/ssh_host_ed25519_key

[Service]
Type=oneshot
ExecStart=/usr/bin/ssh-keygen -A
RemainAfterExit=yes

[Install]
WantedBy=multi-user.target
UNIT
chmod 0644 /etc/systemd/system/fc-node-template-ssh-hostkeys.service
systemctl daemon-reload
systemctl enable fc-node-template-ssh-hostkeys.service

# Remove only the known, temporary test image created by the Docker verification.
docker image rm hello-world:latest >/dev/null 2>&1 || true
apt-get clean

# Reset Linux identity for cloning. Do not boot the source template after this step.
find /etc/ssh -maxdepth 1 -type f -name 'ssh_host_*' -delete
truncate -s 0 /etc/machine-id
rm -f /var/lib/dbus/machine-id
ln -s /etc/machine-id /var/lib/dbus/machine-id

echo
echo "=== FINAL TEMPLATE CHECKS ==="
printf 'Hostname: %s\n' "$(hostnamectl --static)"
printf 'machine-id bytes: '
wc -c </etc/machine-id
if find /etc/ssh -maxdepth 1 -type f -name 'ssh_host_*' -print -quit | grep -q .; then
  fail "SSH host keys still exist; template is NOT ready."
fi
systemctl is-enabled fc-node-template-ssh-hostkeys.service
systemctl is-active docker
[[ ! -e /etc/pterodactyl/config.yml ]] || fail "Node config unexpectedly exists."
echo "READY: shut down now, then copy the entire powered-off VM folder."
echo "IMPORTANT: do not reboot or use the template source after finalization."
