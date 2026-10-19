#!/usr/bin/env bash
#
# Installs the package into a throwaway Laravel application and checks that a
# consumer who requires nothing else gets a working CMS.
#
# This is the test that would have caught the undeclared intervention/image in
# v1.0.0: nothing here requires the library, so if the package does not ask for
# it, it is not on disk. It is also where a new Laravel major is cleared —
# the whole run is "does a stranger's application work", which is the only
# question composer constraints really answer.
#
#   tests/install.sh              # the supported Laravel majors
#   tests/install.sh 13           # one of them
#   tests/install.sh 13 --reuse   # keep the built app and reinstall into it
#
# Expect several minutes per version on a cold run: most of it is Composer
# downloading a Laravel application and Filament.

set -uo pipefail

PACKAGE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PACKAGE_NAME="$(php -r 'echo json_decode(file_get_contents($argv[1]), true)["name"];' "$PACKAGE/composer.json")"

# The majors composer.json claims. Keep in step with illuminate/support.
VERSIONS=(12 13)

WORK_DIR="${TMPDIR:-/tmp}/laravel-cms-install-test"
REUSE=0
KEEP=0
SERVER_PID=""

ADMIN_EMAIL="install-test@example.com"
ADMIN_PASSWORD="install-test-password"

# ---------------------------------------------------------------------------

BOLD=""; RED=""; GREEN=""; DIM=""; OFF=""
if [ -t 1 ]; then
    BOLD=$'\e[1m'; RED=$'\e[31m'; GREEN=$'\e[32m'; DIM=$'\e[2m'; OFF=$'\e[0m'
fi

failures=0
current=""

section() { printf '\n%s%s%s\n' "$BOLD" "$1" "$OFF"; }
ok()      { printf '  %sok%s    %-44s %s\n' "$GREEN" "$OFF" "$1" "${2:-}"; }
fail()    { printf '  %sFAIL%s  %-44s %s\n' "$RED" "$OFF" "$1" "${2:-}"; failures=$((failures + 1)); }
note()    { printf '  %s%s%s\n' "$DIM" "$1" "$OFF"; }

# check <name> <command...> — runs it quietly, reports, never aborts the run.
check() {
    local name="$1"; shift
    local output

    if output=$("$@" 2>&1); then
        ok "$name"
    else
        fail "$name" "$(printf '%s' "$output" | tail -1)"
    fi
}

cleanup() {
    if [ -n "$SERVER_PID" ] && kill -0 "$SERVER_PID" 2>/dev/null; then
        kill "$SERVER_PID" 2>/dev/null
        wait "$SERVER_PID" 2>/dev/null
    fi
    SERVER_PID=""
}
trap cleanup EXIT INT TERM

# A port nothing is listening on, so two runs at once do not collide.
free_port() {
    local port
    for port in $(seq 8900 8999); do
        if ! (exec 3<>"/dev/tcp/127.0.0.1/$port") 2>/dev/null; then
            printf '%s' "$port"
            return 0
        fi
        exec 3>&- 2>/dev/null
    done

    printf '%s' 0
}

# ---------------------------------------------------------------------------

requested=()

while [ $# -gt 0 ]; do
    case "$1" in
        --reuse) REUSE=1; KEEP=1 ;;
        --keep) KEEP=1 ;;
        --work-dir) WORK_DIR="$2"; shift ;;
        -h|--help) sed -n '2,20p' "${BASH_SOURCE[0]}" | sed 's/^#\s\?//'; exit 0 ;;
        [0-9]*) requested+=("$1") ;;
        *) printf 'unknown argument: %s\n' "$1" >&2; exit 2 ;;
    esac
    shift
done

if [ ${#requested[@]} -gt 0 ]; then
    VERSIONS=("${requested[@]}")
fi

for tool in composer php curl; do
    if ! command -v "$tool" >/dev/null 2>&1; then
        printf '%s is required and is not on PATH.\n' "$tool" >&2
        exit 2
    fi
done

mkdir -p "$WORK_DIR"

printf '%sInstall test — %s%s\n' "$BOLD" "$PACKAGE_NAME" "$OFF"
note "package:   $PACKAGE"
note "work dir:  $WORK_DIR"
note "versions:  ${VERSIONS[*]}"

for version in "${VERSIONS[@]}"; do
    app="$WORK_DIR/laravel$version"
    before=$failures

    section "Laravel $version — a new application"

    if [ -d "$app" ] && [ "$REUSE" = "0" ]; then
        rm -rf "$app"
    fi

    if [ -d "$app/vendor" ]; then
        ok "reusing the application in $app"
    else
        rm -rf "$app"
        if composer create-project "laravel/laravel:^$version.0" "$app" \
            --no-interaction --prefer-dist --quiet >/dev/null 2>&1; then
            ok "created" "$(php "$app/artisan" --version 2>/dev/null)"
        else
            fail "could not create a Laravel $version application" \
                 "composer create-project failed — is laravel/laravel:^$version.0 installable?"
            continue
        fi
    fi

    section "Laravel $version — requiring the package"

    # A path repository symlinks the working tree, so the run tests the code as
    # it stands rather than the last commit.
    check "path repository registered" \
        composer --working-dir="$app" config repositories.cms path "$PACKAGE"

    if composer --working-dir="$app" require "$PACKAGE_NAME:@dev" \
        --no-interaction >/dev/null 2>&1; then
        ok "composer require resolved"
    else
        fail "composer require failed" "run it by hand in $app to see the conflict"
        continue
    fi

    # The point of the exercise: the application asks for the package and
    # nothing else, so every other library has to arrive through the package.
    if grep -q 'intervention/image' "$app/composer.json"; then
        fail "the application must not require intervention itself" \
             "remove it from $app/composer.json — the package has to bring it"
    else
        ok "the application requires only the package"
    fi

    if [ -d "$app/vendor/intervention/image" ]; then
        ok "intervention/image was installed by the package" \
           "$(composer --working-dir="$app" show intervention/image 2>/dev/null | awk '/^versions/ {print $4}')"
    else
        fail "intervention/image is absent" \
             "the package does not declare it, so ImageProcessor would be fatal here"
    fi

    section "Laravel $version — cms:install"

    # SQLite keeps the run self-contained; a fresh app already points at it.
    sed -i -e 's#^DB_CONNECTION=.*#DB_CONNECTION=sqlite#' "$app/.env"
    grep -q '^DB_CONNECTION=sqlite' "$app/.env" || printf '\nDB_CONNECTION=sqlite\n' >> "$app/.env"
    touch "$app/database/database.sqlite"

    if php "$app/artisan" cms:install --no-interaction --no-admin >"$WORK_DIR/install-$version.log" 2>&1; then
        migrations=$(grep -c 'DONE' "$WORK_DIR/install-$version.log")
        if [ "$migrations" -gt 0 ]; then
            ok "cms:install ran" "$migrations migrations"
        else
            # Only possible under --reuse, where the schema is already there.
            ok "cms:install ran" "nothing left to migrate"
        fi
    else
        fail "cms:install failed" "see $WORK_DIR/install-$version.log"
        continue
    fi

    check "cms:install is safe to re-run" \
        php "$app/artisan" cms:install --no-interaction --no-admin

    admin_output=$(php "$app/artisan" auth:admin \
        --firstname=Install --lastname=Test \
        --email="$ADMIN_EMAIL" --password="$ADMIN_PASSWORD" 2>&1)

    if [ $? -eq 0 ]; then
        ok "auth:admin created an administrator" "$ADMIN_EMAIL"
    elif printf '%s' "$admin_output" | grep -q 'already been taken'; then
        # Only under --reuse, where the previous run left this administrator
        # behind. Refusing the duplicate is the correct behaviour.
        ok "auth:admin kept the existing administrator" "$ADMIN_EMAIL"
    else
        fail "auth:admin failed" "$(printf '%s' "$admin_output" | tail -1)"
    fi

    # A short password must be refused rather than stored.
    if php "$app/artisan" auth:admin --firstname=Too --lastname=Short \
        --email="short-$ADMIN_EMAIL" --password=abc >/dev/null 2>&1; then
        fail "auth:admin accepted a 3-character password"
    else
        ok "auth:admin refuses a short password"
    fi

    if php "$app/artisan" route:list 2>/dev/null | grep -q 'cms.page'; then
        ok "the page route is registered" "no routes/web.php wiring needed"
    else
        fail "the package's page route is missing"
    fi

    section "Laravel $version — serving"

    port="$(free_port)"

    if [ "$port" = "0" ]; then
        fail "no free port between 8900 and 8999"
    else
        php "$app/artisan" serve --port="$port" >"$WORK_DIR/serve-$version.log" 2>&1 &
        SERVER_PID=$!

        # --retry-connrefused waits for the server to come up without sleeping
        # for a guessed number of seconds.
        for path in / /admin/login; do
            status=$(curl -s -o /dev/null -w '%{http_code}' \
                --retry 10 --retry-delay 1 --retry-connrefused \
                "http://127.0.0.1:$port$path" 2>/dev/null)

            if [ "$status" = "200" ]; then
                ok "GET $path" "200"
            else
                fail "GET $path" "expected 200, got ${status:-no response} — see $WORK_DIR/serve-$version.log"
            fi
        done

        # The panel's stylesheet, fetched exactly as the browser asks for it.
        # /admin/login answers 200 whether or not this file exists, so the
        # status code above cannot tell a styled panel from a bare one.
        stylesheet=$(curl -s "http://127.0.0.1:$port/admin/login" \
            | grep -oE "http://127.0.0.1:$port/css/filament[^\"?]*" | head -1)

        if [ -z "$stylesheet" ]; then
            fail "the login page links no Filament stylesheet"
        else
            css_status=$(curl -s -o /dev/null -w '%{http_code}' "$stylesheet")
            css_bytes=$(curl -s -o /dev/null -w '%{size_download}' "$stylesheet")

            if [ "$css_status" = "200" ] && [ "${css_bytes:-0}" -gt 1000 ]; then
                ok "the panel stylesheet loads" "$css_bytes bytes"
            else
                fail "the panel stylesheet is missing" \
                     "HTTP $css_status — run php artisan filament:assets; the panel renders unstyled"
            fi
        fi

        cleanup
    fi

    section "Laravel $version — from inside the application"

    if php "$PACKAGE/tests/fixtures/verify-install.php" "$app"; then
        ok "every in-application check passed"
    else
        fail "in-application checks failed" "see the FAIL lines above"
    fi

    if [ "$KEEP" = "0" ]; then
        rm -rf "$app"
        note "removed $app (pass --keep to inspect it)"
    else
        note "kept $app"
    fi

    if [ "$failures" = "$before" ]; then
        printf '\n%sLaravel %s: pass%s\n' "$GREEN" "$version" "$OFF"
    else
        printf '\n%sLaravel %s: %s failure(s)%s\n' "$RED" "$version" "$((failures - before))" "$OFF"
    fi
done

section "Result"

if [ "$failures" = "0" ]; then
    printf '%sPASS%s  the package installs and works on Laravel %s.\n' "$GREEN" "$OFF" \
        "$(IFS=', '; printf '%s' "${VERSIONS[*]}")"
    exit 0
fi

printf '%sFAIL%s  %s check(s) failed.\n' "$RED" "$OFF" "$failures"
exit 1
