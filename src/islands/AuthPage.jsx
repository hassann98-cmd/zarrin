import React, { useCallback, useEffect, useRef, useState } from "react";
import { UserRound, ChevronRight } from "lucide-react";
import { getThemeSettings } from "../lib/theme-settings.js";
import { restRequest, siteUrl } from "../lib/api.js";
import { normalizeDigits, normalizePhone } from "../lib/input.js";
import { useDialog } from "../lib/use-dialog.js";

export function resolvePostAuthUrl() {
  const fallback = siteUrl("home");
  try {
    const query = new URLSearchParams(window.location.search);
    const requested = query.get("redirect_to") || query.get("redirect");
    if (!requested) return fallback;
    const home = new URL(siteUrl("home"), window.location.href);
    const destination = new URL(requested, home);
    const sitePath = home.pathname.replace(/\/+$/, "") || "/";
    const dashboard = new URL(siteUrl("dashboard"), home);
    const dashboardPath = dashboard.pathname.replace(/\/+$/, "") || "/";
    const insideSite =
      sitePath === "/" ||
      destination.pathname === sitePath ||
      destination.pathname.startsWith(`${sitePath}/`);
    const accountPath =
      dashboard.origin === home.origin &&
      dashboardPath !== sitePath &&
      (destination.pathname === dashboardPath ||
        destination.pathname.startsWith(`${dashboardPath}/`));
    if (
      destination.origin !== home.origin ||
      destination.username ||
      destination.password ||
      !insideSite ||
      accountPath
    ) {
      return fallback;
    }
    return destination.href;
  } catch {
    return fallback;
  }
}

export default function AuthPage() {
  const settings = getThemeSettings();
  const smsEnabled = Boolean(settings.sms?.enabled);
  // R70: حالت «ورود فقط با رمز پیامکی» — تبِ نام‌کاربری/رمز کلاً حذف می‌شود.
  const otpOnly = Boolean(settings.auth?.otpOnly) && smsEnabled;
  const canRegister = Boolean(settings.auth?.registrationEnabled);
  const [method, setMethod] = useState(smsEnabled ? "phone" : "username");
  const [mode, setMode] = useState("login");
  const [sent, setSent] = useState(false);
  const [listeningForOtp, setListeningForOtp] = useState(false);
  const [phone, setPhone] = useState("");
  const [code, setCode] = useState("");
  const [login, setLogin] = useState("");
  const [username, setUsername] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const otpInput = useRef(null);
  const authDialog = useRef(null);
  const isOverlay =
    typeof document !== "undefined" &&
    Boolean(document.body?.hasAttribute("data-jluxe-auth-overlay-open"));
  useDialog(isOverlay, authDialog, () => {
    if (typeof window !== "undefined") {
      window.dispatchEvent(new window.CustomEvent("jluxe:auth-close"));
    }
  });
  const verifyingOtp = useRef(false);
  const otpRequestReady = useRef(false);
  const pendingWebOtp = useRef("");

  const verifyOtp = useCallback(
    async (rawCode) => {
      const normalizedPhone = normalizePhone(phone);
      const normalizedCode = normalizeDigits(String(rawCode ?? ""))
        .replace(/[^0-9]/g, "")
        .slice(0, 6);
      if (!normalizedPhone) {
        setError("شماره موبایل معتبر نیست.");
        return;
      }
      if (!/^[0-9]{6}$/.test(normalizedCode)) {
        setError("کد ۶ رقمی پیامک را وارد کنید.");
        return;
      }
      if (verifyingOtp.current) return;
      verifyingOtp.current = true;
      setBusy(true);
      setError("");
      try {
        await restRequest(
          "auth/otp-verify",
          { phone: normalizedPhone, code: normalizedCode },
          { authenticated: false },
        );
        window.location.assign(resolvePostAuthUrl());
      } catch (cause) {
        setCode("");
        setError(
          cause instanceof Error ? cause.message : "ارتباط با سرور برقرار نشد.",
        );
        otpInput.current?.focus();
      } finally {
        verifyingOtp.current = false;
        setBusy(false);
      }
    },
    [phone],
  );

  useEffect(() => {
    if (
      method !== "phone" ||
      !listeningForOtp ||
      typeof window === "undefined"
    ) {
      return;
    }
    const credentials = window.navigator?.credentials;
    if (
      !window.isSecureContext ||
      !("OTPCredential" in window) ||
      typeof credentials?.get !== "function" ||
      typeof window.AbortController !== "function"
    ) {
      return;
    }

    const controller = new window.AbortController();
    credentials
      .get({ otp: { transport: ["sms"] }, signal: controller.signal })
      .then((credential) => {
        if (controller.signal.aborted || typeof credential?.code !== "string")
          return;
        const receivedCode = normalizeDigits(credential.code)
          .replace(/[^0-9]/g, "")
          .slice(0, 6);
        if (!/^[0-9]{6}$/.test(receivedCode)) return;
        setCode(receivedCode);
        if (otpRequestReady.current) void verifyOtp(receivedCode);
        else pendingWebOtp.current = receivedCode;
      })
      .catch(() => {
        // Unsupported SMS formats and denied browser prompts keep manual entry available.
      });
    return () => controller.abort();
  }, [method, listeningForOtp, verifyOtp]);

  useEffect(() => {
    if (method === "phone" && sent && !busy) otpInput.current?.focus();
  }, [method, sent, busy]);

  function handleOtpInput(event) {
    const input = event.currentTarget;
    const nextCode = normalizeDigits(input.value)
      .replace(/[^0-9]/g, "")
      .slice(0, 6);
    if (input.value !== nextCode) input.value = nextCode;
    setCode(nextCode);
    if (error) setError("");
    if (nextCode.length === 6) void verifyOtp(nextCode);
  }

  async function submit(event) {
    event.preventDefault();
    if (method === "phone" && sent) {
      await verifyOtp(code);
      return;
    }
    if (busy) return;
    setBusy(true);
    setError("");
    try {
      if (method === "phone") {
        const normalizedPhone = normalizePhone(phone);
        if (!normalizedPhone) throw new Error("شماره موبایل معتبر نیست.");
        otpRequestReady.current = false;
        pendingWebOtp.current = "";
        setListeningForOtp(true);
        await restRequest(
          "auth/otp-request",
          { phone: normalizedPhone },
          { authenticated: false },
        );
        otpRequestReady.current = true;
        setSent(true);
        const pendingCode = pendingWebOtp.current;
        pendingWebOtp.current = "";
        setCode(pendingCode);
        if (pendingCode) void verifyOtp(pendingCode);
        return;
      } else if (mode === "register" && canRegister) {
        await restRequest(
          "auth/register",
          { username, email, password },
          { authenticated: false },
        );
      } else {
        await restRequest(
          "auth/login",
          { login, password },
          { authenticated: false },
        );
      }
      window.location.assign(resolvePostAuthUrl());
    } catch (cause) {
      if (method === "phone" && !sent) {
        otpRequestReady.current = false;
        pendingWebOtp.current = "";
        setListeningForOtp(false);
      }
      setError(
        cause instanceof Error ? cause.message : "ارتباط با سرور برقرار نشد.",
      );
    } finally {
      if (!verifyingOtp.current) setBusy(false);
    }
  }

  function changeMethod(next) {
    setMethod(next);
    setSent(false);
    otpRequestReady.current = false;
    pendingWebOtp.current = "";
    setListeningForOtp(false);
    setCode("");
    setError("");
    setPassword("");
  }

  return (
    <main
      className="jluxe-auth-screen"
      dir="rtl"
      ref={isOverlay ? authDialog : undefined}
      role={isOverlay ? "dialog" : undefined}
      aria-modal={isOverlay ? "true" : undefined}
      aria-labelledby={isOverlay ? "jluxe-auth-title" : undefined}
      tabIndex={isOverlay ? -1 : undefined}
    >
      <section className="jluxe-auth-card" aria-labelledby="jluxe-auth-title">
        <a className="jluxe-auth-back" href={siteUrl("home")}>
          <ChevronRight size={16} aria-hidden="true" /> بازگشت به فروشگاه
        </a>
        <div className="jluxe-auth-brand">
          <UserRound size={28} aria-hidden="true" />
        </div>
        <h1 id="jluxe-auth-title">
          {smsEnabled
            ? "ورود و عضویت با موبایل"
            : canRegister
              ? "ورود و عضویت"
              : "ورود به حساب کاربری"}
        </h1>
        {smsEnabled && !otpOnly && (
          <div className="jluxe-auth-tabs" aria-label="روش ورود">
            <button
              type="button"
              aria-pressed={method === "phone"}
              disabled={busy}
              onClick={() => changeMethod("phone")}
            >
              شماره موبایل
            </button>
            <button
              type="button"
              aria-pressed={method === "username"}
              disabled={busy}
              onClick={() => changeMethod("username")}
            >
              نام کاربری
            </button>
          </div>
        )}
        {method === "username" && canRegister && !otpOnly && (
          <div className="jluxe-auth-tabs" aria-label="ورود یا ثبت‌نام">
            <button
              type="button"
              aria-pressed={mode === "login"}
              disabled={busy}
              onClick={() => {
                setMode("login");
                setError("");
              }}
            >
              ورود
            </button>
            <button
              type="button"
              aria-pressed={mode === "register"}
              disabled={busy}
              onClick={() => {
                setMode("register");
                setError("");
              }}
            >
              ثبت‌نام
            </button>
          </div>
        )}
        <form onSubmit={submit} aria-busy={busy}>
          {method === "phone" ? (
            <>
              <label htmlFor="jluxe-auth-phone">شماره موبایل</label>
              <input
                id="jluxe-auth-phone"
                data-dialog-initial-focus=""
                type="tel"
                autoComplete="tel"
                inputMode="tel"
                dir="ltr"
                required
                value={phone}
                disabled={busy || sent}
                onChange={(event) => setPhone(event.target.value)}
                placeholder="09121234567"
              />
              {sent && (
                <>
                  <p role="status">
                    کد ۶ رقمی ارسال شد و تا دو دقیقه معتبر است. اگر خودکار پر نشد،
                    آن را دستی وارد کنید؛ تأیید پس از کامل‌شدن شش رقم خودکار است.
                  </p>
                  <label htmlFor="jluxe-auth-code">کد تأیید</label>
                  <input
                    ref={otpInput}
                    id="jluxe-auth-code"
                    type="text"
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    dir="ltr"
                    required
                    autoFocus
                    value={code}
                    disabled={busy}
                    onInput={handleOtpInput}
                    onChange={handleOtpInput}
                  />
                  <button
                    className="jluxe-auth-text-button"
                    type="button"
                    disabled={busy}
                    onClick={() => {
                      setSent(false);
                      otpRequestReady.current = false;
                      pendingWebOtp.current = "";
                      setListeningForOtp(false);
                      setCode("");
                      setError("");
                    }}
                  >
                    تغییر شماره یا درخواست کد جدید
                  </button>
                </>
              )}
            </>
          ) : (
            <>
              {mode === "register" && canRegister ? (
                <>
                  <label htmlFor="jluxe-auth-username">نام کاربری</label>
                  <input
                    id="jluxe-auth-username"
                    data-dialog-initial-focus=""
                    autoComplete="username"
                    dir="ltr"
                    required
                    minLength={3}
                    value={username}
                    disabled={busy}
                    onChange={(event) => setUsername(event.target.value)}
                  />
                  <label htmlFor="jluxe-auth-email">ایمیل</label>
                  <input
                    id="jluxe-auth-email"
                    type="email"
                    autoComplete="email"
                    dir="ltr"
                    required
                    value={email}
                    disabled={busy}
                    onChange={(event) => setEmail(event.target.value)}
                  />
                </>
              ) : (
                <>
                  <label htmlFor="jluxe-auth-login">نام کاربری یا ایمیل</label>
                  <input
                    id="jluxe-auth-login"
                    data-dialog-initial-focus=""
                    autoComplete="username"
                    dir="ltr"
                    required
                    value={login}
                    disabled={busy}
                    onChange={(event) => setLogin(event.target.value)}
                  />
                </>
              )}
              <label htmlFor="jluxe-auth-password">
                رمز عبور
                {mode === "register" && canRegister
                  ? " (حداقل ۱۲ کاراکتر)"
                  : ""}
              </label>
              <input
                id="jluxe-auth-password"
                type="password"
                autoComplete={
                  mode === "register" ? "new-password" : "current-password"
                }
                dir="ltr"
                required
                minLength={mode === "register" && canRegister ? 12 : undefined}
                value={password}
                disabled={busy}
                onChange={(event) => setPassword(event.target.value)}
              />
            </>
          )}
          {error && (
            <p className="jluxe-auth-error" role="alert">
              {error}
            </p>
          )}
          <button className="jluxe-auth-submit" type="submit" disabled={busy}>
            {busy
              ? "در حال بررسی…"
              : method === "phone"
                ? sent
                  ? "تأیید کد"
                  : "دریافت کد ورود / عضویت"
                : mode === "register" && canRegister
                  ? "ساخت حساب"
                  : "ورود"}
          </button>
        </form>
        <a className="jluxe-auth-recovery" href={siteUrl("lost_password")}>
          رمز عبور را فراموش کرده‌اید؟
        </a>
      </section>
    </main>
  );
}
