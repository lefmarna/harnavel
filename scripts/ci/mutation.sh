#!/usr/bin/env bash
# mutation.yml と同じ条件のミューテーションテストを、CircleCI の cimg/php とサイドカーの MariaDB で実行する。
set -euo pipefail

: "${BASE_BRANCH:?PR の base がありません}"

# mutation.yml の paths-ignore に当たる変更だけなら、ミュータントの生死は変わらない。
excludes=()
while IFS= read -r pattern; do
  excludes+=(":(exclude,glob)$pattern")
done < <(awk '
  /paths-ignore:/ { listing = 1; next }
  listing && /^[[:space:]]*- / { sub(/^[[:space:]]*- /, ""); gsub(/"/, ""); print; next }
  listing { exit }
' .github/workflows/mutation.yml)

if [ "${#excludes[@]}" -gt 0 ] &&
  git diff --quiet "refs/remotes/origin/$BASE_BRANCH" HEAD -- . "${excludes[@]}"; then
  echo "PHP のミュータントに影響する変更がないため、ミューテーションテストを省きます。"
  exit 0
fi

export COMPOSER_CACHE_DIR="$HOME/.cache/composer"
# cimg/php の install-php-extensions は PHP 8.5 で動かないため、pecl で入れる。
sudo pecl install pcov > /dev/null
php -m | grep -qx pcov

cp .env.example .env
composer install --no-interaction --prefer-dist --optimize-autoloader
php artisan key:generate
mkdir -p vendor/pestphp/pest-plugin-mutate/.temp/mutations

scripts/ci/wait-for-database.sh
env=$(php scripts/ci/workflow-env.php .github/workflows/mutation.yml "Run mutation testing")
(eval "$env" && vendor/bin/pest --mutate --covered-only --no-progress --min=100)
