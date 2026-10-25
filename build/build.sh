#!/usr/bin/env bash
# Build the packages into dist/, installable through Joomla's extension installer:
#   pkg_mcpask-<version>.zip  AI Ask for Joomla (answers visitors' questions from the site's own content)
#   pkg_askdesk-<version>.zip AI Ask Desk (live chat with people in AI Ask's window, translated both ways)
set -euo pipefail
cd "$(dirname "$0")/.."

TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
mkdir -p dist

# The extensions' own scripts and styles, smaller (esbuild, from board/'s tools): what visitors and the console
# load, on this site and on websites AI Ask is embedded in. Without esbuild they go as they are. Not the
# vendored ones (already small) nor the dashboard (built small)
ESBUILD=board/node_modules/.bin/esbuild
minify() {
  local file
  [ -x "$ESBUILD" ] || return 0
  find "$1" -path '*/media/*' \( -name '*.js' -o -name '*.css' \) ! -name '*.min.*' ! -path '*/vendor/*' ! -name 'board.js' ! -name 'board.css' ! -path '*/js/preview*' -print0 |
    while IFS= read -r -d '' file; do
      "$ESBUILD" "$file" --minify --log-level=error --charset=utf8 --outfile="$file.tmp" --allow-overwrite && mv "$file.tmp" "$file"
    done
}

# build <package> <manifest> <script> <language dir> <extension>...
build() {
  local name=$1 manifest=$2 script=$3 language=$4 version ext
  shift 4
  version=$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' "$manifest" | head -1)

  mkdir -p "$TMP/$name/packages" "$TMP/src"
  for ext in "$@"; do
    # A copy (in the build's own fresh folder), its own scripts and styles made smaller; the sources stay as they are
    cp -R "src/$ext" "$TMP/src/$ext"
    minify "$TMP/src/$ext"
    (cd "$TMP/src/$ext" && zip -qrX "$TMP/$name/packages/$ext.zip" . -x '.*' -x '*/.*')
  done
  cp "$manifest" "$TMP/$name/"
  cp "$script" "$TMP/$name/script.php"
  cp -R "$language" "$TMP/$name/language"

  rm -f "dist/$name-$version.zip"
  (cd "$TMP/$name" && zip -qrX "$OLDPWD/dist/$name-$version.zip" . -x '.*' -x '*/.*')
  echo "dist/$name-$version.zip"
}

# The browser fetches a module's script and styles again only when their version changes:
# the asset manifest must carry the module's version (its assets may say "auto", Joomla's media
# version, which changes with every install)
for module in mod_mcp_ask com_askdesk; do
  manifest=src/$module/$module.xml
  [ -f "$manifest" ] || manifest=src/$module/${module#com_}.xml
  want=$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' "$manifest" | head -1)
  if grep -q '"version"' "src/$module/media/joomla.asset.json" && grep '"version"' "src/$module/media/joomla.asset.json" | grep -v '"auto"' | grep -vq "\"$want\""; then
    echo "src/$module/media/joomla.asset.json: asset versions differ from $want" >&2
    exit 1
  fi
done

build pkg_mcpask src/pkg_mcpask/pkg_mcpask.xml src/pkg_mcpask/script.php src/pkg_mcpask/language \
  com_mcpask mod_mcp_ask mod_mcp_ask_log plg_content_mcpask plg_task_mcpask plg_easyblog_mcpask plg_mcpask_rsform plg_mcpask_livechat plg_mcpask_imagegen plg_mcpask_dropfiles plg_mcp_mcpask

# The dashboard (React, in board/) is built into com_askdesk's media: built again when its sources are newer
board=src/com_askdesk/media/js/board.js
if [ -n "$(find board/src board/package.json board/vite.config.js -newer "$board" 2>/dev/null | head -1)" ] || [ ! -f "$board" ]; then
  if command -v npm >/dev/null && [ -d board/node_modules ]; then
    (cd board && npm run --silent build >/dev/null)
  else
    echo "$board is older than board/src: run npm install and npm run build in board/" >&2
    exit 1
  fi
fi

# The viewer of documents (in preview/: PDF.js, docx-preview, SheetJS) likewise, built small into com_askdesk's media
viewer=src/com_askdesk/media/js/preview.js
if [ -n "$(find preview/src preview/package.json preview/build.mjs -newer "$viewer" 2>/dev/null | head -1)" ] || [ ! -f "$viewer" ]; then
  if command -v npm >/dev/null && [ -d preview/node_modules ]; then
    (cd preview && npm run --silent build >/dev/null)
  else
    echo "$viewer is older than preview/src: run npm install and npm run build in preview/" >&2
    exit 1
  fi
fi

build pkg_askdesk src/pkg_askdesk/pkg_askdesk.xml src/pkg_askdesk/script.php src/pkg_askdesk/language \
  com_askdesk plg_mcpask_askdesk
