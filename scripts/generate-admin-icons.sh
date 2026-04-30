#!/bin/bash
#
# Generate Horde icons from Tabler Icons (MIT license)
# https://github.com/tabler/tabler-icons
#
# Requires: ImageMagick (convert), curl
#
# Usage:
#   ./generate-admin-icons.sh [size] [category]
#
# Categories:
#   admin   - Admin dashboard icons (output: themes/default/graphics/admin/)
#   actions - Common action icons (output: themes/default/graphics/actions/)
#   apps    - Application identity icons (output: themes/default/graphics/apps/)
#   status  - Status/notification icons (output: themes/default/graphics/status/)
#   nav     - Navigation icons (output: themes/default/graphics/nav/)
#   all     - Generate all categories (default)
#
# Default size: 48px
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BASE_URL="https://raw.githubusercontent.com/tabler/tabler-icons/main/icons/outline"
THEMES_DIR="${SCRIPT_DIR}/../themes/default/graphics"
SIZE="${1:-48}"
CATEGORY="${2:-all}"
TMPDIR="$(mktemp -d)"
STROKE_COLOR="#333333"

trap 'rm -rf "$TMPDIR"' EXIT

generate_icons() {
    local dest="$1"
    shift
    local -n icons_ref=$1

    mkdir -p "$dest"

    local failed=0
    for key in "${!icons_ref[@]}"; do
        local icon="${icons_ref[$key]}"
        local url="${BASE_URL}/${icon}.svg"
        local svgfile="${TMPDIR}/${key}.svg"
        local pngfile="${dest}/${key}.png"

        printf "  %-20s <- tabler/%s.svg ... " "$key" "$icon"

        if ! curl -sfL "$url" -o "$svgfile"; then
            echo "DOWNLOAD FAILED"
            failed=$((failed + 1))
            continue
        fi

        if [ ! -s "$svgfile" ]; then
            echo "EMPTY FILE"
            failed=$((failed + 1))
            continue
        fi

        # Replace currentColor with our stroke color for consistent rendering
        sed -i "s/currentColor/${STROKE_COLOR}/g" "$svgfile"

        if convert -background none -density 192 "$svgfile" -resize "${SIZE}x${SIZE}" "$pngfile" 2>/dev/null; then
            echo "OK"
        else
            echo "CONVERT FAILED"
            failed=$((failed + 1))
        fi
    done

    return $failed
}

# =============================================================================
# ADMIN DASHBOARD ICONS
# Used by: AdminDashboardController, lib/Api.php admin_list()
# Output: themes/default/graphics/admin/
# =============================================================================
declare -A ADMIN_ICONS=(
    [config]="settings"
    [user]="user"
    [group]="users-group"
    [perms]="shield-lock"
    [locked]="lock"
    [alarm]="bell"
    [data]="database"
    [cache]="server-bolt"
    [hashtable]="hash"
    [sessions]="users"
    [api-registry]="plug-connected"
    [php]="code"
    [sql]="sql"
    [shell]="terminal-2"
    [mobile]="device-mobile"
    [oauth]="key"
    [auth-status]="shield-check"
)

# =============================================================================
# COMMON ACTION ICONS
# Used by: toolbars, buttons, context menus across all apps
# Output: themes/default/graphics/actions/
# =============================================================================
declare -A ACTION_ICONS=(
    [add]="plus"
    [edit]="pencil"
    [delete]="trash"
    [search]="search"
    [download]="download"
    [upload]="upload"
    [import]="file-import"
    [export]="file-export"
    [refresh]="refresh"
    [close]="x"
    [copy]="copy"
    [cut]="cut"
    [print]="printer"
    [help]="help-circle"
    [info]="info-circle"
    [login]="login"
    [logout]="logout"
    [compose]="edit"
    [reply]="arrow-back-up"
    [replyall]="arrows-left"
    [forward]="arrow-forward-up"
    [attachment]="paperclip"
    [bookmark]="bookmark"
    [share]="share"
    [link]="link"
    [settings]="settings"
    [filter]="filter"
    [sort-asc]="sort-ascending"
    [sort-desc]="sort-descending"
    [expand]="chevron-down"
    [collapse]="chevron-up"
    [undo]="arrow-back"
    [redo]="arrow-forward"
    [move]="arrows-move"
    [tag]="tag"
)

# =============================================================================
# APPLICATION IDENTITY ICONS
# Used by: app switcher, portal tiles, responsive portal grid
# Output: themes/default/graphics/apps/
# =============================================================================
declare -A APP_ICONS=(
    [mail]="mail"
    [calendar]="calendar"
    [contacts]="address-book"
    [tasks]="list-check"
    [notes]="note"
    [files]="folder"
    [wiki]="notebook"
    [bookmarks]="bookmarks"
    [news]="news"
    [password]="lock-access"
    [passwd]="key"
    [content]="tags"
    [skeleton]="puzzle"
    [filters]="filter"
    [administration]="shield-cog"
    [webmail]="inbox"
    [timetracker]="clock"
)

# =============================================================================
# STATUS/NOTIFICATION ICONS
# Used by: alerts, notifications, indicators
# Output: themes/default/graphics/status/
# =============================================================================
declare -A STATUS_ICONS=(
    [error]="alert-circle"
    [warning]="alert-triangle"
    [success]="circle-check"
    [info]="info-circle"
    [message]="message"
    [alarm]="bell-ringing"
    [flagged]="flag"
    [spam]="flame"
    [read]="mail-opened"
    [unread]="mail"
    [private]="lock"
    [recurring]="repeat"
    [loading]="loader"
)

# =============================================================================
# NAVIGATION ICONS
# Used by: pagination, tree controls, breadcrumbs
# Output: themes/default/graphics/nav/
# =============================================================================
declare -A NAV_ICONS=(
    [first]="chevrons-left"
    [last]="chevrons-right"
    [prev]="chevron-left"
    [next]="chevron-right"
    [up]="chevron-up"
    [down]="chevron-down"
    [home]="home"
    [back]="arrow-left"
    [folder]="folder"
    [folder-open]="folder-open"
    [file]="file"
    [tree-plus]="square-plus"
    [tree-minus]="square-minus"
)

# =============================================================================
# MAIN
# =============================================================================

TOTAL_FAILED=0

run_category() {
    local name="$1"
    local dest="$2"
    local -n ref=$3

    echo ""
    echo "=== ${name} (${SIZE}px) ==="
    echo "Output: ${dest}"
    echo ""

    if generate_icons "$dest" "$3"; then
        :
    else
        TOTAL_FAILED=$((TOTAL_FAILED + $?))
    fi
}

case "$CATEGORY" in
    admin)
        run_category "Admin Dashboard" "${THEMES_DIR}/admin" ADMIN_ICONS
        ;;
    actions)
        run_category "Common Actions" "${THEMES_DIR}/actions" ACTION_ICONS
        ;;
    apps)
        run_category "Application Identity" "${THEMES_DIR}/apps" APP_ICONS
        ;;
    status)
        run_category "Status/Notifications" "${THEMES_DIR}/status" STATUS_ICONS
        ;;
    nav)
        run_category "Navigation" "${THEMES_DIR}/nav" NAV_ICONS
        ;;
    all)
        run_category "Admin Dashboard" "${THEMES_DIR}/admin" ADMIN_ICONS
        run_category "Common Actions" "${THEMES_DIR}/actions" ACTION_ICONS
        run_category "Application Identity" "${THEMES_DIR}/apps" APP_ICONS
        run_category "Status/Notifications" "${THEMES_DIR}/status" STATUS_ICONS
        run_category "Navigation" "${THEMES_DIR}/nav" NAV_ICONS
        ;;
    *)
        echo "Unknown category: $CATEGORY"
        echo "Valid: admin, actions, apps, status, nav, all"
        exit 1
        ;;
esac

echo ""
echo "=========================================="
echo "Done."
if [ $TOTAL_FAILED -gt 0 ]; then
    echo "WARNING: ${TOTAL_FAILED} icon(s) failed."
    exit 1
fi
