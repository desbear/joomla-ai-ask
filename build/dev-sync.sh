#!/usr/bin/env bash
# Copy the source into an installed Joomla site for quick testing (after a first normal install).
# Usage: build/dev-sync.sh /path/to/joomla
set -euo pipefail
cd "$(dirname "$0")/.."
SITE=${1:?usage: build/dev-sync.sh /path/to/joomla}

sync_plugin() { # group element srcdir
  rsync -a --delete "src/$3/" "$SITE/plugins/$1/$2/" --exclude language
  for tag in en-GB zh-TW; do
    cp src/$3/language/$tag/* "$SITE/administrator/language/$tag/"
  done
}
# AI Ask for Joomla (pkg_mcpask)
rsync -a --delete src/com_mcpask/administrator/ "$SITE/administrator/components/com_mcpask/" --exclude language
rsync -a --delete src/com_mcpask/site/ "$SITE/components/com_mcpask/" --exclude language
rsync -a --delete src/com_mcpask/media/ "$SITE/media/com_mcpask/"
cp src/com_mcpask/mcpask.xml "$SITE/administrator/components/com_mcpask/mcpask.xml"
rsync -a --delete src/mod_mcp_ask/ "$SITE/modules/mod_mcp_ask/" --exclude language --exclude media
rsync -a --delete src/mod_mcp_ask/media/ "$SITE/media/mod_mcp_ask/"
rsync -a --delete src/mod_mcp_ask_log/ "$SITE/administrator/modules/mod_mcp_ask_log/" --exclude language
for tag in en-GB zh-TW; do
  cp src/com_mcpask/administrator/language/$tag/* src/mod_mcp_ask_log/language/$tag/* "$SITE/administrator/language/$tag/"
  cp src/com_mcpask/site/language/$tag/* src/mod_mcp_ask/language/$tag/* "$SITE/language/$tag/"
done
sync_plugin content mcpask plg_content_mcpask
sync_plugin task mcpask plg_task_mcpask
sync_plugin mcpask rsform plg_mcpask_rsform
sync_plugin mcpask livechat plg_mcpask_livechat
sync_plugin mcpask imagegen plg_mcpask_imagegen
sync_plugin mcpask dropfiles plg_mcpask_dropfiles
sync_plugin mcp mcpask plg_mcp_mcpask
