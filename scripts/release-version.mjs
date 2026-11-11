const STABLE_SEMVER = /^(\d+)\.(\d+)\.(\d+)$/;

function parseVersion(version) {
  const match = STABLE_SEMVER.exec(String(version));
  if (!match) throw new Error(`Expected a stable x.y.z release version, got: ${version}`);
  return match.slice(1).map(Number);
}

export function compareVersions(left, right) {
  const a = parseVersion(left);
  const b = parseVersion(right);
  for (let index = 0; index < a.length; index += 1) {
    if (a[index] !== b[index]) return a[index] < b[index] ? -1 : 1;
  }
  return 0;
}

/**
 * Default releases advance the patch. An explicit version supports a minor/major
 * release; moving backward requires a separate, deliberate opt-in.
 */
export function resolveReleaseVersion(current, requested = "", allowDowngrade = false) {
  const [major, minor, patch] = parseVersion(current);
  if (!requested) return `${major}.${minor}.${patch + 1}`;

  parseVersion(requested);
  const order = compareVersions(requested, current);
  if (order === 0) throw new Error(`Release version ${requested} is already current.`);
  if (order < 0 && !allowDowngrade) {
    throw new Error(
      `Refusing version downgrade ${current} → ${requested}; set THEME_ALLOW_VERSION_DOWNGRADE=1 only when the downgrade is explicitly intended.`,
    );
  }
  return requested;
}
