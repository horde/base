#!/bin/bash
#
# Generate Horde icons from Lucide Icons (ISC license)
# https://github.com/lucide-icons/lucide
#
# Requires: ImageMagick (convert), curl
#
# Usage:
#   ./generate-lucide-icons.sh [size] [category]
#
# Categories:
#   admin   - Admin dashboard icons (output: themes/default/graphics/admin/)
#   actions - Common action icons (output: themes/default/graphics/actions/)
#   apps    - Application identity icons (output: themes/default/graphics/apps/)
#   status  - Status/notification icons (output: themes/default/graphics/status/)
#   nav     - Navigation icons (output: themes/default/graphics/nav/)
#   legacy  - Legacy root graphics (output: themes/default/graphics/)
#   all     - Generate all categories (default)
#
# Default size: 48px
#
# Lucide is a community fork of Feather Icons, ISC licensed.
# https://github.com/lucide-icons/lucide/blob/main/LICENSE
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
BASE_URL="https://raw.githubusercontent.com/lucide-icons/lucide/main/icons"
THEMES_DIR="${SCRIPT_DIR}/../themes/default/graphics"
SIZE="${1:-48}"
CATEGORY="${2:-all}"
TMPDIR="$(mktemp -d)"
STROKE_COLOR="#333333"

trap 'rm -rf "$TMPDIR"' EXIT

svg_to_png() {
    local svgfile="$1"
    local pngfile="$2"

    if command -v rsvg-convert >/dev/null 2>&1; then
        if rsvg-convert --dpi-x 192 --dpi-y 192 -w "$SIZE" -h "$SIZE" "$svgfile" -o "$pngfile" 2>/dev/null; then
            return 0
        fi
    fi

    convert -background none -density 192 "$svgfile" -resize "${SIZE}x${SIZE}" "$pngfile" 2>/dev/null
}

png_has_visible_pixels() {
    local pngfile="$1"
    local alpha

    alpha=$(convert "$pngfile" -alpha extract -format '%[fx:mean]' info: 2>/dev/null || echo 0)
    awk "BEGIN { exit !($alpha > 0.01) }"
}

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

        printf "  %-20s <- lucide/%s.svg ... " "$key" "$icon"

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

        if svg_to_png "$svgfile" "$pngfile" && png_has_visible_pixels "$pngfile"; then
            echo "OK"
        else
            echo "CONVERT FAILED"
            failed=$((failed + 1))
        fi
    done

    return $failed
}

generate_legacy_icons() {
    local dest="$1"
    local failed=0
    local close_icon="${THEMES_DIR}/actions/close.png"

    mkdir -p "$dest"

    # delete-small.png is the same Lucide "x" asset as actions/close.png but kept
    # at the graphics root for Horde_Themes::img('delete-small.png') call sites.
    printf "  %-20s <- actions/close.png ... " "delete-small"
    if [ -f "$close_icon" ]; then
        cp "$close_icon" "${dest}/delete-small.png"
        echo "OK"
    elif generate_icons "$dest" LEGACY_ICONS; then
        :
    else
        failed=$((failed + 1))
    fi

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
    [group]="users"
    [perms]="shield"
    [locked]="lock"
    [alarm]="bell"
    [data]="database"
    [cache]="zap"
    [hashtable]="hash"
    [sessions]="users-round"
    [api-registry]="plug"
    [php]="code"
    [sql]="database-zap"
    [shell]="terminal"
    [mobile]="smartphone"
    [oauth]="key-round"
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
    [delete]="trash-2"
    [search]="search"
    [download]="download"
    [upload]="upload"
    [import]="file-input"
    [export]="file-output"
    [refresh]="refresh-cw"
    [close]="x"
    [copy]="copy"
    [cut]="scissors"
    [print]="printer"
    [help]="circle-question-mark"
    [info]="info"
    [login]="log-in"
    [logout]="log-out"
    [compose]="square-pen"
    [reply]="reply"
    [replyall]="reply-all"
    [forward]="forward"
    [attachment]="paperclip"
    [bookmark]="bookmark"
    [share]="share-2"
    [link]="link"
    [settings]="settings"
    [filter]="list-filter"
    [sort-asc]="arrow-up-narrow-wide"
    [sort-desc]="arrow-down-wide-narrow"
    [expand]="chevron-down"
    [collapse]="chevron-up"
    [undo]="undo-2"
    [redo]="redo-2"
    [move]="move"
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
    [contacts]="contact"
    [tasks]="list-checks"
    [notes]="sticky-note"
    [files]="folder"
    [wiki]="book-open"
    [bookmarks]="bookmark"
    [news]="newspaper"
    [password]="key-round"
    [passwd]="key-round"
    [content]="tags"
    [skeleton]="puzzle"
    [filters]="funnel"
    [administration]="shield-half"
    [webmail]="inbox"
    [timetracker]="clock"
    [turba]="book-user"
    [trean]="bookmark"
    [tessera]="shield-check"
    [satisfiend]="webhook"
)

# =============================================================================
# STATUS/NOTIFICATION ICONS
# Used by: alerts, notifications, indicators
# Output: themes/default/graphics/status/
# =============================================================================
declare -A STATUS_ICONS=(
    [error]="circle-x"
    [warning]="triangle-alert"
    [success]="circle-check"
    [info]="info"
    [message]="message-square"
    [alarm]="bell-ring"
    [flagged]="flag"
    [spam]="flame"
    [read]="mail-open"
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
    [home]="house"
    [back]="arrow-left"
    [folder]="folder"
    [folder-open]="folder-open"
    [file]="file"
    [tree-plus]="square-plus"
    [tree-minus]="square-minus"
)

# =============================================================================
# LEGACY ROOT GRAPHICS
# Icons at themes/default/graphics/ (not in subdirectories) kept for BC with
# Horde_Themes::img('name.png') call sites across apps.
# Output: themes/default/graphics/
# =============================================================================
declare -A LEGACY_ICONS=(
    [delete-small]="x"
)

# =============================================================================
# COLORIZED VARIANTS
# Icons that need specific colors for dark backgrounds (topbar, etc.)
# Format: "output_name:lucide_name:#color"
# Output: themes/default/graphics/actions/
# =============================================================================
COLORIZED_ICONS=(
    "logout-orange:log-out:#e67e22"
    "login-orange:log-in:#e67e22"
    "settings-light:settings:#dddddd"
    "settings-white:settings:#ffffff"
)

generate_colorized() {
    local dest="$1"

    mkdir -p "$dest"

    local failed=0
    for entry in "${COLORIZED_ICONS[@]}"; do
        IFS=':' read -r key icon color <<< "$entry"
        local url="${BASE_URL}/${icon}.svg"
        local svgfile="${TMPDIR}/${key}.svg"
        local pngfile="${dest}/${key}.png"

        printf "  %-20s <- lucide/%s.svg [%s] ... " "$key" "$icon" "$color"

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

        sed -i "s/currentColor/${color}/g" "$svgfile"

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
    colorized)
        echo ""
        echo "=== Colorized Variants (${SIZE}px) ==="
        echo "Output: ${THEMES_DIR}/actions"
        echo ""
        generate_colorized "${THEMES_DIR}/actions"
        ;;
    legacy)
        echo ""
        echo "=== Legacy Root Graphics (${SIZE}px) ==="
        echo "Output: ${THEMES_DIR}"
        echo ""
        if generate_legacy_icons "${THEMES_DIR}"; then
            :
        else
            TOTAL_FAILED=$((TOTAL_FAILED + $?))
        fi
        ;;
    all)
        run_category "Admin Dashboard" "${THEMES_DIR}/admin" ADMIN_ICONS
        run_category "Common Actions" "${THEMES_DIR}/actions" ACTION_ICONS
        run_category "Application Identity" "${THEMES_DIR}/apps" APP_ICONS
        run_category "Status/Notifications" "${THEMES_DIR}/status" STATUS_ICONS
        run_category "Navigation" "${THEMES_DIR}/nav" NAV_ICONS
        echo ""
        echo "=== Legacy Root Graphics (${SIZE}px) ==="
        echo "Output: ${THEMES_DIR}"
        echo ""
        if generate_legacy_icons "${THEMES_DIR}"; then
            :
        else
            TOTAL_FAILED=$((TOTAL_FAILED + $?))
        fi
        echo ""
        echo "=== Colorized Variants (${SIZE}px) ==="
        echo "Output: ${THEMES_DIR}/actions"
        echo ""
        generate_colorized "${THEMES_DIR}/actions"
        ;;
    *)
        echo "Unknown category: $CATEGORY"
        echo "Valid: admin, actions, apps, status, nav, colorized, legacy, all"
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
