# Required release rule for delivered changes

For every user-requested code change that is being delivered from this repository:

1. Start or confirm the sandbox download server on port `4181` (bind to `0.0.0.0`) before releasing. Retired servers/previews on ports `4180`, `4179`, `4178`, `4177`, and `4176` must remain stopped so the user does not receive a stale link.
2. Run `npm run release`. It increments the patch version, updates `package.json`, `package-lock.json`, `style.css`, the current-version README/docs markers, runs `npm run check`, builds the ZIP, atomically stages the same ZIP in `downloads/`, and verifies `/version` plus `/download/latest` byte-for-byte against the package.
3. If the release command fails, do not provide or describe a stale download link as current. Fix the cause, rerun it, and verify the server response.
4. Give the user the current sandbox preview host with the dynamic `/download/latest` route, not a fixed old preview host or an obsolete versioned filename.
5. State clearly that a sandbox download server is not the user's production server. Never claim production was updated unless it was actually deployed and verified.
6. Publish a non-draft GitHub Release for every delivered version with tag `vX.Y.Z` targeting the delivered commit on the session branch. Attach the installable ZIP when GitHub's upload endpoint is reachable; if it is blocked, publish the release/source archive and state clearly that the binary asset could not be attached. Never recreate a version explicitly deleted by the user.

`npm run package` remains available for reproducible packaging without a version bump, but a delivered code change must use `npm run release` so the patch version and sandbox download advance together.
