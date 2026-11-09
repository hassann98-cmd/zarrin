import test from "node:test";
import assert from "node:assert/strict";
import { compareVersions, resolveReleaseVersion } from "../scripts/release-version.mjs";

test("release defaults to the next patch without changing the current series", () => {
  assert.equal(resolveReleaseVersion("1.64.92"), "1.64.93");
});

test("release accepts an explicit forward version", () => {
  assert.equal(resolveReleaseVersion("1.64.92", "1.65.0"), "1.65.0");
});

test("release rejects an accidental version downgrade", () => {
  assert.throws(() => resolveReleaseVersion("1.64.92", "1.7.0"), /Refusing version downgrade/);
});

test("release accepts an explicitly authorized downgrade", () => {
  assert.equal(resolveReleaseVersion("1.64.92", "1.7.0", true), "1.7.0");
});

test("release version comparison follows SemVer numeric ordering", () => {
  assert.equal(compareVersions("1.7.0", "1.64.92"), -1);
  assert.equal(compareVersions("1.100.0", "1.99.99"), 1);
  assert.throws(() => compareVersions("1.7", "1.7.0"), /stable x.y.z/);
  assert.throws(() => resolveReleaseVersion("1.64.92", "1.64.92"), /already current/);
});
