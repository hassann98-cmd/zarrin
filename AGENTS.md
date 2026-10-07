# Required release rule for delivered changes

For every user-requested code change that is being delivered from this repository:

1. Start or confirm the sandbox download server on a fresh, unused port selected for the current session (bind to `0.0.0.0`); never revive a retired preview port or reuse an old download address. Pass the same port as `PORT` when starting the server and `THEME_DOWNLOAD_SERVER_PORT` to the release command.
2. Run `npm run release`. It increments the patch version, updates `package.json`, `package-lock.json`, `style.css`, the current-version README/docs markers, runs `npm run check`, builds the ZIP, atomically stages the same ZIP in `downloads/`, and verifies `/version`, inclusion in `/releases.json`, and both latest/versioned download routes byte-for-byte against the package.
3. If the release command fails, do not provide or describe a stale download link as current. Fix the cause, rerun it, and verify the server response.
4. Give the user the current sandbox preview host with the dynamic `/download/latest` route, not a fixed old preview host or an obsolete versioned filename.
5. State clearly that a sandbox download server is not the user's production server. Never claim production was updated unless it was actually deployed and verified.

`npm run package` remains available for reproducible packaging without a version bump, but a delivered code change must use `npm run release` so the patch version and sandbox download advance together.
