import React, { useState } from "react";
import { UserRound, ChevronRight } from "lucide-react";
import { getThemeSettings } from "../lib/theme-settings.js";
import { restRequest, siteUrl } from "../lib/api.js";
import { normalizeDigits, normalizePhone } from "../lib/input.js";

export default function AuthPage() {
  const settings = getThemeSettings();
  const smsEnabled = Boolean(settings.sms?.enabled);
  const canRegister = Boolean(settings.auth?.registrationEnabled);
  const [method, setMethod] = useState(smsEnabled ? "phone" : "username");
  const [mode, setMode] = useState("login");
  const [sent, setSent] = useState(false);
  const [phone, setPhone] = useState("");
  const [code, setCode] = useState("");
  const [login, setLogin] = useState("");
  const [username, setUsername] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");

  async function submit(event) {
    event.preventDefault();
    if (busy) return;
    setBusy(true);
    setError("");
    try {
      if (method === "phone") {
        const normalizedPhone = normalizePhone(phone);
        if (!normalizedPhone) throw new Error("شماره موبایل معتبر نیست.");
        if (!sent) {
          await restRequest(
            "auth/otp-request",
            { phone: normalizedPhone },
            { authenticated: false },
          );
          setSent(true);
          setCode("");
          return;
        }
        const normalizedCode = normalizeDigits(code).trim();
        if (!/^[0-9]{6}$/.test(normalizedCode))
          throw new Error("کد ۶ رقمی پیامک را وارد کنید.");
        await restRequest(
          "auth/otp-verify",
          { phone: normalizedPhone, code: normalizedCode },
          { authenticated: false },
        );
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
      window.location.assign(siteUrl("dashboard"));
    } catch (cause) {
      setError(
        cause instanceof Error ? cause.message : "ارتباط با سرور برقرار نشد.",
      );
    } finally {
      setBusy(false);
    }
  }

  function changeMethod(next) {
    setMethod(next);
    setError("");
    setPassword("");
  }

  return (
    <main className="jluxe-auth-screen" dir="rtl">
      <section className="jluxe-auth-card" aria-labelledby="jluxe-auth-title">
        <a className="jluxe-auth-back" href={siteUrl("home")}>
          <ChevronRight size={16} aria-hidden="true" /> بازگشت به فروشگاه
        </a>
        <div className="jluxe-auth-brand">
          <UserRound size={28} aria-hidden="true" />
        </div>
        <h1 id="jluxe-auth-title">
          {canRegister ? "ورود و عضویت" : "ورود به حساب کاربری"}
        </h1>
        {smsEnabled && (
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
        {method === "username" && canRegister && (
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
                    کد ۶ رقمی ارسال شد و تا دو دقیقه معتبر است.
                  </p>
                  <label htmlFor="jluxe-auth-code">کد تأیید</label>
                  <input
                    id="jluxe-auth-code"
                    type="text"
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    dir="ltr"
                    maxLength={6}
                    required
                    autoFocus
                    value={code}
                    disabled={busy}
                    onChange={(event) => setCode(event.target.value)}
                  />
                  <button
                    className="jluxe-auth-text-button"
                    type="button"
                    disabled={busy}
                    onClick={() => {
                      setSent(false);
                      setCode("");
                      setError("");
                    }}
                  >
                    تغییر شماره یا درخواست کد جدید
                  </button>
                </>
              )}
              {!canRegister && (
                <p>ورود پیامکی فقط برای حساب‌های موجود فعال است.</p>
              )}
            </>
          ) : (
            <>
              {mode === "register" && canRegister ? (
                <>
                  <label htmlFor="jluxe-auth-username">نام کاربری</label>
                  <input
                    id="jluxe-auth-username"
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
                  ? "تأیید و ورود"
                  : "دریافت کد ورود"
                : mode === "register" && canRegister
                  ? "ساخت حساب"
                  : "ورود"}
          </button>
        </form>
        <a className="jluxe-auth-recovery" href={siteUrl("lost_password")}>
          رمز عبور را فراموش کرده‌اید؟
        </a>
        {smsEnabled && (
          <p className="jluxe-auth-hint">
            برای اتصال شمارهٔ صورتحساب به حساب موجود، ابتدا با رمز وارد شوید و
            در «جزئیات حساب» شماره را تأیید کنید.
          </p>
        )}
      </section>
    </main>
  );
}
