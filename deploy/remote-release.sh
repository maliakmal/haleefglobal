#!/usr/bin/env bash
set -Eeuo pipefail

release_name="${1:-}"
app_root="${APP_ROOT:-/var/www/haleefglobal}"
releases_root="${app_root}/releases"
shared_root="${app_root}/shared"
release_dir="${releases_root}/${release_name}"
current_link="${app_root}/current"
healthcheck_url="${HEALTHCHECK_URL:-http://127.0.0.1/health}"
healthcheck_host="${HEALTHCHECK_HOST:-www.haleefglobal.com}"

if [[ ! "${release_name}" =~ ^[0-9]+-[a-f0-9]{12}$ ]]; then
    echo "Invalid release name." >&2
    exit 1
fi
if [[ "${app_root}" != /var/www/* || ! -d "${release_dir}" ]]; then
    echo "Release directory is missing or unsafe." >&2
    exit 1
fi
if [[ ! -f "${shared_root}/.env" ]]; then
    echo "Shared environment file is missing: ${shared_root}/.env" >&2
    exit 1
fi

mkdir -p \
    "${shared_root}/storage/private" \
    "${shared_root}/storage/logs" \
    "${shared_root}/public-resources/haleef-global" \
    "${shared_root}/public-resources/haleef-learning" \
    "${shared_root}/backups"
chmod 2770 "${shared_root}/storage/private"
chmod 2775 "${shared_root}/storage/logs" "${shared_root}/public-resources/haleef-global" "${shared_root}/public-resources/haleef-learning"
chmod 2750 "${shared_root}/backups"

ln -sfn "${shared_root}/.env" "${release_dir}/.env"
rm -rf -- "${release_dir}/storage/private" "${release_dir}/storage/logs" "${release_dir}/storage/public-resources" "${release_dir}/storage/backups"
ln -s "${shared_root}/storage/private" "${release_dir}/storage/private"
ln -s "${shared_root}/storage/logs" "${release_dir}/storage/logs"
ln -s "${shared_root}/public-resources" "${release_dir}/storage/public-resources"
ln -s "${shared_root}/backups" "${release_dir}/storage/backups"

previous_release=""
if [[ -L "${current_link}" ]]; then
    previous_release="$(readlink -f "${current_link}")"
fi

cd "${release_dir}"
php bin/backup-database.php
php bin/migrate.php

temporary_link="${app_root}/.current-${release_name}"
ln -s "${release_dir}" "${temporary_link}"
mv -Tf "${temporary_link}" "${current_link}"

if ! curl --fail --silent --show-error --max-time 15 --retry 3 --retry-delay 2 -H "Host: ${healthcheck_host}" "${healthcheck_url}" >/dev/null; then
    echo "Health check failed. Rolling the application symlink back." >&2
    if [[ -n "${previous_release}" && -d "${previous_release}" ]]; then
        rollback_link="${app_root}/.rollback-${release_name}"
        ln -s "${previous_release}" "${rollback_link}"
        mv -Tf "${rollback_link}" "${current_link}"
    else
        rm -f -- "${current_link}"
    fi
    exit 1
fi

mapfile -t old_releases < <(find "${releases_root}" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' | sort -rn | tail -n +6 | cut -d' ' -f2-)
active_release="$(readlink -f "${current_link}")"
for old_release in "${old_releases[@]}"; do
    if [[ "${old_release}" == "${releases_root}/"* && "${old_release}" != "${active_release}" ]]; then
        rm -rf -- "${old_release}"
    fi
done

echo "Release ${release_name} is live."
