/**
 * WebOTP is a progressive enhancement. Require the browser's explicit
 * OTPCredential signal so unsupported Credentials APIs never interfere with
 * the native autocomplete="one-time-code" fallback.
 */
export function supportsWebOtp(browserWindow) {
  try {
    return Boolean(
      browserWindow?.isSecureContext &&
        "OTPCredential" in browserWindow &&
        typeof browserWindow.navigator?.credentials?.get === "function",
    );
  } catch {
    return false;
  }
}
