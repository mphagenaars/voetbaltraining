#!/bin/bash

# Voetbaltraining Deploy Script
#
# Gebruik:
#   ./deploy.sh --setup              Eenmalige setup: SSH-sleutel, sudo-rechten en sleutelcontrole
#   ./deploy.sh --rotate-key         Encryptiesleutel rouleren op de productieserver
#   ./deploy.sh "commit bericht"     Commit + push + deploy naar productie
#   ./deploy.sh                      Alleen push + deploy (geen nieuwe commit)

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONF_FILE="$SCRIPT_DIR/.deploy.conf"
SSH_KEY="$HOME/.ssh/voetbaltraining_deploy"

# Bekende gecompromitteerde sleutel (stond hardcoded in de git-history)
GECOMPROMITTEERDE_SLEUTEL="base64:q+Z0eEttl1l6jxN10mcGGRMPL9qjdMGHgjup4eCQo/U="

# ─── Kleuren ──────────────────────────────────────────────────────────────────

GROEN='\033[0;32m'
ROOD='\033[0;31m'
GEEL='\033[0;33m'
BLAUW='\033[0;36m'
VET='\033[1m'
RESET='\033[0m'

ok()           { echo -e "  ${GROEN}✓${RESET}  $1"; }
fout_msg()     { echo -e "  ${ROOD}✗${RESET}  $1"; }
info()         { echo -e "  ${BLAUW}→${RESET}  $1"; }
waarschuwing() { echo -e "  ${GEEL}!${RESET}  $1"; }
stap()         { echo -e "\n${VET}${BLAUW}[$1]${RESET}  $2"; }

# ─── Config laden ─────────────────────────────────────────────────────────────

laad_config() {
    if [ ! -f "$CONF_FILE" ]; then
        echo ""
        fout_msg ".deploy.conf niet gevonden."
        info "Maak het aan op basis van het voorbeeld:"
        echo ""
        echo "    cp .deploy.conf.example .deploy.conf"
        echo "    nano .deploy.conf"
        echo ""
        exit 1
    fi
    # shellcheck source=/dev/null
    source "$CONF_FILE"

    DEPLOY_USER="${DEPLOY_USER:?DEPLOY_USER is niet ingesteld in .deploy.conf}"
    DEPLOY_HOST="${DEPLOY_HOST:?DEPLOY_HOST is niet ingesteld in .deploy.conf}"
    DEPLOY_PATH="${DEPLOY_PATH:?DEPLOY_PATH is niet ingesteld in .deploy.conf}"
    DEPLOY_BRANCH="${DEPLOY_BRANCH:-main}"
}

# ─── SSH-helpers ──────────────────────────────────────────────────────────────

ssh_opts() {
    local OPTS="-o ConnectTimeout=10 -o StrictHostKeyChecking=accept-new"
    if [ -f "$SSH_KEY" ]; then
        OPTS="$OPTS -i $SSH_KEY -o IdentitiesOnly=yes"
    fi
    echo "$OPTS"
}

remote() {
    # shellcheck disable=SC2046
    ssh $(ssh_opts) "${DEPLOY_USER}@${DEPLOY_HOST}" "$@"
}

# Voert een commando interactief uit op de server (toont output direct)
remote_interactief() {
    # shellcheck disable=SC2046
    ssh $(ssh_opts) -t "${DEPLOY_USER}@${DEPLOY_HOST}" "$@"
}

# ─── Encryptiesleutel controleren op de server ────────────────────────────────

controleer_sleutel() {
    stap "4/4" "Encryptiesleutel controleren op de server..."

    VHOST_FILE="${DEPLOY_VHOST_FILE:-/etc/apache2/sites-available/voetbaltraining.conf}"
    CONFIG_OP_SERVER="${DEPLOY_PATH}/data/config.php"

    # Bestaat data/config.php op de server?
    if ! remote "test -f '${CONFIG_OP_SERVER}'" 2>/dev/null; then
        echo ""
        waarschuwing "data/config.php ontbreekt op de server."
        echo ""
        echo "  De encryptiesleutel is nodig voor de AI-module."
        echo "  Genereer een sleutel op de server:"
        echo ""
        echo -e "  ${GEEL}ssh ${DEPLOY_USER}@${DEPLOY_HOST}${RESET}"
        echo -e "  ${GEEL}cd ${DEPLOY_PATH}${RESET}"
        echo -e "  ${GEEL}php scripts/rotate_encryption_key.php${RESET}"
        echo ""
        return
    fi

    # Lees de huidige sleutelwaarde op de server
    SLEUTEL_OP_SERVER=$(remote "php -r \"\\\$c = require '${CONFIG_OP_SERVER}'; echo \\\$c['encryption_key'] ?? '';\"" 2>/dev/null || echo "")

    if [ -z "$SLEUTEL_OP_SERVER" ]; then
        waarschuwing "Kon sleutel niet lezen van server. Controleer data/config.php handmatig."
        return
    fi

    # Is het de bekende gecompromitteerde sleutel?
    if [ "$SLEUTEL_OP_SERVER" = "$GECOMPROMITTEERDE_SLEUTEL" ]; then
        echo ""
        echo -e "  ${ROOD}${VET}KRITIEK:${RESET} De server gebruikt nog de gecompromitteerde encryptiesleutel"
        echo "  die ooit in de git-repository heeft gestaan."
        echo ""
        waarschuwing "Alle API-sleutels in de database kunnen door derden worden ontsleuteld."
        echo ""
        read -rp "  Sleutel nu roteren? Dit herversleutelt alle API-sleutels. [j/n]: " ANTWOORD
        if [[ "$ANTWOORD" =~ ^[Jj]$ ]]; then
            echo ""
            remote_interactief "cd '${DEPLOY_PATH}' && echo 'j' | php scripts/rotate_encryption_key.php"
            echo ""

            # Voeg de nieuwe sleutel ook toe als SetEnv in de vhost (voor Apache env)
            NIEUWE_SLEUTEL=$(remote "php -r \"\\\$c = require '${CONFIG_OP_SERVER}'; echo \\\$c['encryption_key'] ?? '';\"" 2>/dev/null || echo "")
            if [ -n "$NIEUWE_SLEUTEL" ]; then
                zet_setenv_in_vhost "$NIEUWE_SLEUTEL" "$VHOST_FILE"
            fi

            ok "Sleutelrotatie voltooid."
        else
            waarschuwing "Rotatie overgeslagen. Voer zo snel mogelijk uit: ./deploy.sh --rotate-key"
        fi
    else
        ok "Encryptiesleutel aanwezig en niet gecompromitteerd."

        # Controleer of SetEnv ook klopt in de vhost (hoeft niet, maar is netter)
        if ! remote "sudo grep -q 'APP_ENCRYPTION_KEY' '${VHOST_FILE}' 2>/dev/null"; then
            info "SetEnv ontbreekt in de vhost — de sleutel staat alleen in data/config.php (prima)."
        fi
    fi

    # Stel bestandsrechten zeker
    remote "chmod 640 '${CONFIG_OP_SERVER}'" 2>/dev/null || true
}

zet_setenv_in_vhost() {
    local SLEUTEL="$1"
    local VHOST="$2"

    # Vervang bestaande (uitgecommentarieerde) SetEnv-regel, of voeg in na DocumentRoot
    if remote "sudo grep -q 'APP_ENCRYPTION_KEY' '${VHOST}'" 2>/dev/null; then
        remote "sudo sed -i \"s|.*APP_ENCRYPTION_KEY.*|    SetEnv APP_ENCRYPTION_KEY \\\"${SLEUTEL}\\\"|\" '${VHOST}'"
    else
        remote "sudo sed -i \"/DocumentRoot/a\\\\    SetEnv APP_ENCRYPTION_KEY \\\"${SLEUTEL}\\\"\" '${VHOST}'"
    fi
    remote "sudo systemctl reload apache2"
    ok "SetEnv bijgewerkt in ${VHOST}."
}

# ─── Setup modus ──────────────────────────────────────────────────────────────

setup() {
    laad_config

    echo ""
    echo -e "${VET}══════════════════════════════════════════════${RESET}"
    echo -e "${VET}   Voetbaltraining — Eenmalige deploy setup   ${RESET}"
    echo -e "${VET}══════════════════════════════════════════════${RESET}"
    echo ""
    echo "Server: ${DEPLOY_USER}@${DEPLOY_HOST}"
    echo "Map:    ${DEPLOY_PATH}"
    echo ""

    # Stap 1: SSH-sleutel aanmaken
    stap "1/4" "SSH-sleutel aanmaken..."
    if [ -f "$SSH_KEY" ]; then
        ok "Sleutel bestaat al: $SSH_KEY"
    else
        ssh-keygen -t ed25519 -f "$SSH_KEY" -N "" -C "voetbaltraining-deploy"
        ok "Sleutel aangemaakt: $SSH_KEY"
    fi

    # Stap 2: Publieke sleutel naar de server kopiëren
    stap "2/4" "Publieke sleutel installeren op ${DEPLOY_USER}@${DEPLOY_HOST}..."
    echo ""

    # Controleer of sleutelauth al werkt
    if ssh -i "$SSH_KEY" -o IdentitiesOnly=yes -o BatchMode=yes -o StrictHostKeyChecking=accept-new \
        "${DEPLOY_USER}@${DEPLOY_HOST}" "echo ok" >/dev/null 2>&1; then
        ok "SSH-sleutelauth werkt al."
    else
        info "Je wordt één keer gevraagd om het SSH-wachtwoord van ${DEPLOY_USER}@${DEPLOY_HOST}."
        echo ""
        if ! ssh-copy-id -i "${SSH_KEY}.pub" "${DEPLOY_USER}@${DEPLOY_HOST}"; then
            fout_msg "ssh-copy-id mislukt. Controleer of de server bereikbaar is."
            exit 1
        fi
        ok "Publieke sleutel geïnstalleerd. Wachtwoord niet meer nodig."
    fi

    # Stap 3: Sudo zonder wachtwoord voor update.sh
    stap "3/4" "Sudo-rechten instellen op de server..."
    echo ""

    SUDOERS_REGEL="${DEPLOY_USER} ALL=(ALL) NOPASSWD: ${DEPLOY_PATH}/update.sh"

    if remote "sudo grep -qF '${DEPLOY_PATH}/update.sh' /etc/sudoers /etc/sudoers.d/* 2>/dev/null"; then
        ok "Sudo zonder wachtwoord is al geconfigureerd."
    else
        read -rp "  Mag ik wachtwoordloos sudo instellen voor update.sh op de server? [j/n]: " ANTWOORD
        if [[ "$ANTWOORD" =~ ^[Jj]$ ]]; then
            remote_interactief "echo '${SUDOERS_REGEL}' | sudo tee /etc/sudoers.d/voetbaltraining-deploy > /dev/null && sudo chmod 440 /etc/sudoers.d/voetbaltraining-deploy"
            ok "Sudo-regel aangemaakt."
        else
            echo ""
            info "Voeg dit handmatig toe op de server:"
            echo ""
            echo "    echo '${SUDOERS_REGEL}' | sudo tee /etc/sudoers.d/voetbaltraining-deploy"
            echo "    sudo chmod 440 /etc/sudoers.d/voetbaltraining-deploy"
            echo ""
        fi
    fi

    # Stap 4: Encryptiesleutel
    controleer_sleutel

    echo ""
    echo -e "${GROEN}══════════════════════════════════════════════${RESET}"
    echo -e "${GROEN}   Setup voltooid!                            ${RESET}"
    echo -e "${GROEN}══════════════════════════════════════════════${RESET}"
    echo ""
    echo "Je kunt nu deployen met:"
    echo ""
    echo -e "  ${VET}./deploy.sh \"omschrijving van de wijziging\"${RESET}"
    echo ""
}

# ─── Sleutelrotatie modus ─────────────────────────────────────────────────────

roteer_sleutel() {
    laad_config

    echo ""
    echo -e "${VET}══════════════════════════════════════════════${RESET}"
    echo -e "${VET}   Voetbaltraining — Sleutelrotatie           ${RESET}"
    echo -e "${VET}   Server: ${DEPLOY_USER}@${DEPLOY_HOST}      ${RESET}"
    echo -e "${VET}══════════════════════════════════════════════${RESET}"
    echo ""

    if ! remote "echo ok" > /dev/null 2>&1; then
        fout_msg "Kan geen verbinding maken. Voer eerst ./deploy.sh --setup uit."
        exit 1
    fi

    info "Het rotatiescript wordt interactief op de server uitgevoerd."
    info "Je kunt de voortgang hieronder zien en bevestigen."
    echo ""

    remote_interactief "cd '${DEPLOY_PATH}' && php scripts/rotate_encryption_key.php"

    echo ""
    ok "Rotatie uitgevoerd."
}

# ─── Deploy modus ─────────────────────────────────────────────────────────────

deploy() {
    laad_config
    COMMIT_MSG="${1:-}"

    echo ""
    echo -e "${VET}══════════════════════════════════════════════${RESET}"
    echo -e "${VET}   Voetbaltraining Deploy                     ${RESET}"
    echo -e "${VET}   Server: ${DEPLOY_USER}@${DEPLOY_HOST}      ${RESET}"
    echo -e "${VET}══════════════════════════════════════════════${RESET}"

    # Stap 1: Commit (indien bericht opgegeven)
    stap "1/3" "Code voorbereiden..."
    if [ -n "$COMMIT_MSG" ]; then
        WIJZIGINGEN=$(git -C "$SCRIPT_DIR" status --porcelain --untracked-files=no)
        if [ -n "$WIJZIGINGEN" ]; then
            git -C "$SCRIPT_DIR" add -A
            git -C "$SCRIPT_DIR" commit -m "$COMMIT_MSG"
            ok "Commit aangemaakt: $COMMIT_MSG"
        else
            ok "Geen lokale wijzigingen, alleen pushen."
        fi
    else
        info "Geen commit bericht — huidige branch pushen."
    fi

    # Stap 2: Push naar remote
    stap "2/3" "Pushen naar GitHub (branch: ${DEPLOY_BRANCH})..."
    git -C "$SCRIPT_DIR" push origin "${DEPLOY_BRANCH}"
    ok "Gepusht naar origin/${DEPLOY_BRANCH}."

    # Stap 3: Update op de server
    stap "3/3" "Update uitvoeren op ${DEPLOY_HOST}..."

    if ! remote "echo ok" > /dev/null 2>&1; then
        fout_msg "Kan geen verbinding maken met ${DEPLOY_USER}@${DEPLOY_HOST}."
        waarschuwing "SSH-sleutel nog niet ingesteld? Voer eerst uit: ./deploy.sh --setup"
        exit 1
    fi

    remote "sudo ${DEPLOY_PATH}/update.sh ${DEPLOY_BRANCH}"

    echo ""
    echo -e "${GROEN}══════════════════════════════════════════════${RESET}"
    echo -e "${GROEN}   Deploy voltooid!                           ${RESET}"
    echo -e "${GROEN}══════════════════════════════════════════════${RESET}"
    echo ""
}

# ─── Hoofdmenu ────────────────────────────────────────────────────────────────

case "${1:-}" in
    --setup|-s)
        setup
        ;;
    --rotate-key|-r)
        roteer_sleutel
        ;;
    --help|-h)
        echo ""
        echo "Gebruik:"
        echo "  ./deploy.sh --setup              Eenmalige setup (SSH-sleutel, sudo, sleutelcontrole)"
        echo "  ./deploy.sh --rotate-key         Encryptiesleutel rouleren op de productieserver"
        echo "  ./deploy.sh \"commit bericht\"     Commit + push + deploy"
        echo "  ./deploy.sh                      Alleen push + deploy"
        echo ""
        ;;
    *)
        deploy "${1:-}"
        ;;
esac
