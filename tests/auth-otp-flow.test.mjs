import test from "node:test";
import assert from "node:assert/strict";
import {
  restUrl,
  getSession,
  resetDedupeGet,
} from "../src/lib/api.js";

const captures = [];
function installFetch(fakeResponse) {
  const orig = globalThis.fetch;
  globalThis.fetch = async (input, init) => {
    captures.push({ input: String(input), init });
    return fakeResponse;
  };
  return () => {
    globalThis.fetch = orig;
    captures.length = 0;
  };
}

function settings(locationHref, restRoot, sessionUrl) {
  globalThis.window = {
    location: { href: locationHref },
    JLuxeThemeSettings: {
      urls: {
        home: new URL("./", locationHref).href,
        dashboard: new URL("my-account/", locationHref).href,
      },
      rest: { root: restRoot, sessionUrl },
      auth: { isLoggedIn: false },
    },
  };
}

test.afterEach(() => {
  resetDedupeGet();
  captures.length = 0;
});

test(
  "getSession calls the configured same-origin session endpoint with cookies",
  async () => {
    const dispose = installFetch({
      status: 200,
      ok: true,
      headers: { "content-type": "application/json" },
      json: async () => ({
        success: true,
        data: { auth: { isLoggedIn: true }, restNonce: "nonce" },
      }),
    });
    try {
      settings(
        "https://shop.test/store/auth/login/",
        "https://shop.test/store/wp-json/jluxe/v1/",
        "https://shop.test/store/wp-admin/admin-ajax.php",
      );
      const data = await getSession();
      assert.equal(data.auth.isLoggedIn, true);
      assert.equal(data.restNonce, "nonce");
      assert.equal(captures.length, 1);
      assert.equal(captures[0].input, "https://shop.test/store/wp-admin/admin-ajax.php");
      assert.match(String(captures[0].init.credentials), /same-origin/);
    } finally {
      dispose();
    }
  },
);

test(
  "restUrl builds the OTP verify endpoint on the configured REST root",
  () => {
    settings(
      "https://shop.test/store/auth/login/",
      "https://shop.test/store/wp-json/jluxe/v1/",
      "https://shop.test/store/wp-admin/admin-ajax.php",
    );
    assert.equal(
      restUrl("auth/otp-verify"),
      "https://shop.test/store/wp-json/jluxe/v1/auth/otp-verify",
    );
  },
);
